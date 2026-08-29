<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Provisioning;

use CaptainFin\Whmcs\Domain\OperationState;
use CaptainFin\Whmcs\Infrastructure\Database\OperationRepository;
use CaptainFin\Whmcs\Integrations\Jellyfin\JellyfinException;
use DateTimeImmutable;

final class LifecycleService
{
    private OperationRepository $operations;
    private JellyfinLifecycle $jellyfin;

    public function __construct(
        ?OperationRepository $operations = null,
        ?JellyfinLifecycle $jellyfin = null
    ) {
        $this->operations = $operations ?? new OperationRepository();
        $this->jellyfin = $jellyfin ?? new JellyfinLifecycle($this->operations);
    }

    public function execute(string $operationType, array $params): string
    {
        $serviceId = (int) ($params['serviceid'] ?? 0);
        if ($serviceId <= 0) {
            return 'CAPTAiNFiN: missing WHMCS service id.';
        }

        $operation = null;

        try {
            $target = $this->targetSnapshot($operationType, $params);
            $targetJson = $this->encode($target);
            $targetHash = hash('sha256', $targetJson);
            $operationKey = hash('sha256', sprintf('v2|%d|%s|%s', $serviceId, $operationType, $targetHash));

            $operation = $this->operations->findOrCreate(
                $operationKey,
                $serviceId,
                $operationType,
                $targetHash,
                $target
            );

            if ($operation->state === OperationState::MANUAL_ATTENTION) {
                return sprintf(
                    'CAPTAiNFiN operation requires manual attention (operation #%d): %s',
                    (int) $operation->id,
                    (string) ($operation->last_error ?? 'no detail recorded')
                );
            }

            // Even a previously converged operation is observed/applied again.
            // This keeps duplicate WHMCS callbacks idempotent while also letting
            // them repair remote drift instead of trusting stale local success.
            $this->jellyfin->execute($operationType, $params, $operation);

            return 'success';
        } catch (ManualAttentionException $error) {
            if ($operation !== null) {
                $this->operations->markManualAttention((int) $operation->id, $error->getMessage());
                return sprintf(
                    'CAPTAiNFiN operation requires manual attention (operation #%d): %s',
                    (int) $operation->id,
                    $error->getMessage()
                );
            }

            return 'CAPTAiNFiN requires manual attention: ' . $error->getMessage();
        } catch (\Throwable $error) {
            if ($operation !== null) {
                $retryAfter = $error instanceof JellyfinException && $error->isRetryable()
                    ? (new DateTimeImmutable())->modify('+1 minute')
                    : null;
                $this->operations->markFailed((int) $operation->id, $error->getMessage(), $retryAfter);

                return sprintf(
                    'CAPTAiNFiN operation #%d failed: %s',
                    (int) $operation->id,
                    $error->getMessage()
                );
            }

            return 'CAPTAiNFiN lifecycle error: ' . $error->getMessage();
        }
    }

    private function targetSnapshot(string $operationType, array $params): array
    {
        $moduleOptions = [];

        for ($index = 1; $index <= 24; $index++) {
            $key = 'configoption' . $index;
            if (array_key_exists($key, $params)) {
                $moduleOptions[$key] = $params[$key];
            }
        }

        $target = [
            'operation' => $operationType,
            'service_id' => (int) ($params['serviceid'] ?? 0),
            'client_id' => (int) ($params['userid'] ?? 0),
            'product_id' => (int) ($params['pid'] ?? 0),
            'server_id' => (int) ($params['serverid'] ?? 0),
            'server_endpoint_fingerprint' => hash('sha256', implode('|', [
                (string) ($params['serverhostname'] ?? ''),
                (string) ($params['serverport'] ?? ''),
                filter_var($params['serversecure'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'tls' : 'plain',
            ])),
            'username' => (string) ($params['username'] ?? ''),
            'module_options' => $moduleOptions,
            'configurable_options' => is_array($params['configoptions'] ?? null)
                ? $params['configoptions']
                : [],
        ];

        if ($operationType === 'change_password') {
            $target['password_fingerprint'] = hash_hmac(
                'sha256',
                (string) ($params['password'] ?? ''),
                (string) ($params['serverpassword'] ?? 'captainfin-password-fingerprint')
            );
        }

        return $this->sortRecursively($target);
    }

    private function sortRecursively(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursively($item);
            }
        }

        ksort($value);

        return $value;
    }

    private function encode(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException('Unable to encode lifecycle target.');
        }

        return $json;
    }
}
