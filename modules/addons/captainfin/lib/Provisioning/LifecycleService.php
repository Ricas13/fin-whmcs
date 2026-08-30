<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Provisioning;

use CaptainFin\Whmcs\Commercial\EditionGate;
use CaptainFin\Whmcs\Domain\OperationState;
use CaptainFin\Whmcs\Infrastructure\Database\OperationRepository;
use CaptainFin\Whmcs\Infrastructure\Database\ProductPolicyRepository;
use CaptainFin\Whmcs\Integrations\MediaServer\MediaServerType;
use DateTimeImmutable;

final class LifecycleService
{
    private OperationRepository $operations;
    private ProductPolicyRepository $policies;
    private MediaServerLifecycle $mediaServer;
    private LifecycleLock $lock;
    private LifecycleFailureClassifier $failureClassifier;
    private EditionGate $editionGate;

    public function __construct(
        ?OperationRepository $operations = null,
        ?MediaServerLifecycle $mediaServer = null,
        ?LifecycleLock $lock = null,
        ?LifecycleFailureClassifier $failureClassifier = null,
        ?ProductPolicyRepository $policies = null,
        ?EditionGate $editionGate = null
    ) {
        $this->operations = $operations ?? new OperationRepository();
        $this->policies = $policies ?? new ProductPolicyRepository();
        $this->mediaServer = $mediaServer ?? new MediaServerLifecycle($this->operations);
        $this->lock = $lock ?? new LifecycleLock();
        $this->failureClassifier = $failureClassifier ?? new LifecycleFailureClassifier();
        $this->editionGate = $editionGate ?? new EditionGate();
    }

    public function execute(string $operationType, array $params): string
    {
        $serviceId = (int) ($params['serviceid'] ?? 0);
        if ($serviceId <= 0) {
            return 'CAPTAiNFiN: missing WHMCS service id.';
        }

        try {
            return $this->lock->run(
                $serviceId,
                fn (): string => $this->executeLocked($operationType, $params, $serviceId)
            );
        } catch (LifecycleBusyException $error) {
            return $error->getMessage();
        } catch (\Throwable $error) {
            return 'CAPTAiNFiN lifecycle lock error: ' . $error->getMessage();
        }
    }

    private function executeLocked(string $operationType, array $params, int $serviceId): string
    {
        $operation = null;

        try {
            $provider = MediaServerType::fromWhmcs($params);

            // Commercial editions gate access-granting/mutating operations before
            // a durable provider operation is created. Suspend/terminate remain
            // available after a downgrade so we never strand remote access.
            $this->editionGate->assertLifecycleAllowed($operationType, $provider);

            $target = $this->targetSnapshot($operationType, $params);
            $targetJson = $this->encode($target);
            $targetHash = hash('sha256', $targetJson);
            $operationKey = hash('sha256', sprintf('v3|%d|%s|%s', $serviceId, $operationType, $targetHash));

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

            // Persist normalized desired state before any provider mutation so
            // sampler/reconciliation jobs never execute against unknown policy.
            $this->policies->upsertFromWhmcsParams($params);

            // Re-observe and converge even when the same operation key was
            // previously completed. Duplicate WHMCS callbacks therefore remain
            // idempotent while also repairing remote drift on Jellyfin or Emby.
            $this->mediaServer->execute($operationType, $params, $operation);

            return 'success';
        } catch (\Throwable $error) {
            if ($operation === null) {
                return $error instanceof ManualAttentionException || $error instanceof \InvalidArgumentException
                    ? 'CAPTAiNFiN requires manual attention: ' . $error->getMessage()
                    : 'CAPTAiNFiN lifecycle error: ' . $error->getMessage();
            }

            $disposition = $this->failureClassifier->classify($error);
            if ($disposition['action'] === LifecycleFailureClassifier::MANUAL_ATTENTION) {
                $this->operations->markManualAttention((int) $operation->id, $error->getMessage());

                return sprintf(
                    'CAPTAiNFiN operation requires manual attention (operation #%d): %s',
                    (int) $operation->id,
                    $error->getMessage()
                );
            }

            $delaySeconds = max(1, (int) ($disposition['delay_seconds'] ?? 300));
            $this->operations->markFailed(
                (int) $operation->id,
                $error->getMessage(),
                (new DateTimeImmutable())->modify('+' . $delaySeconds . ' seconds')
            );

            return sprintf(
                'CAPTAiNFiN operation #%d failed and is scheduled for reconciliation: %s',
                (int) $operation->id,
                $error->getMessage()
            );
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
            'media_server_type' => MediaServerType::fromWhmcs($params),
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
