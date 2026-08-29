<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Provisioning;

use CaptainFin\Whmcs\Domain\OperationState;
use CaptainFin\Whmcs\Infrastructure\Database\OperationRepository;

final class LifecycleService
{
    private OperationRepository $operations;

    public function __construct(?OperationRepository $operations = null)
    {
        $this->operations = $operations ?? new OperationRepository();
    }

    /**
     * Bootstrap lifecycle entrypoint.
     *
     * It deliberately records intent and returns a truthful failure until the
     * remote adapters/reconciler are connected. The module must never report
     * success simply because WHMCS invoked the expected handler.
     */
    public function execute(string $operationType, array $params): string
    {
        $serviceId = (int) ($params['serviceid'] ?? 0);

        if ($serviceId <= 0) {
            return 'CAPTAiNFiN: missing WHMCS service id.';
        }

        $target = $this->targetSnapshot($operationType, $params);
        $targetJson = $this->encode($target);
        $targetHash = hash('sha256', $targetJson);
        $operationKey = hash('sha256', sprintf('v1|%d|%s|%s', $serviceId, $operationType, $targetHash));

        try {
            $operation = $this->operations->findOrCreate(
                $operationKey,
                $serviceId,
                $operationType,
                $targetHash,
                $target
            );

            if ($operation->state === OperationState::LOCAL_APPLIED) {
                return 'success';
            }

            if ($operation->state === OperationState::MANUAL_ATTENTION) {
                return sprintf(
                    'CAPTAiNFiN operation requires manual attention (operation #%d): %s',
                    (int) $operation->id,
                    (string) ($operation->last_error ?? 'no detail recorded')
                );
            }

            $message = sprintf(
                'CAPTAiNFiN bootstrap is installed, but the %s remote adapter is not implemented yet (operation #%d).',
                $operationType,
                (int) $operation->id
            );

            $this->operations->markFailed((int) $operation->id, $message);

            return $message;
        } catch (\Throwable $error) {
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
            'username' => (string) ($params['username'] ?? ''),
            'module_options' => $moduleOptions,
            'configurable_options' => is_array($params['configoptions'] ?? null)
                ? $params['configoptions']
                : [],
        ];

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
