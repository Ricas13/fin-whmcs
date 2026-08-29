<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Reconciliation;

final class WhmcsModuleCommandRunner
{
    /** @var callable(string,array):array */
    private $api;

    public function __construct(?callable $api = null)
    {
        $this->api = $api ?? static function (string $command, array $params): array {
            if (!function_exists('localAPI')) {
                throw new \RuntimeException('WHMCS localAPI() is unavailable.');
            }

            $result = localAPI($command, $params);
            return is_array($result) ? $result : ['result' => 'error', 'message' => 'WHMCS localAPI returned an invalid response.'];
        };
    }

    public function run(string $operationType, int $serviceId): array
    {
        if ($serviceId <= 0) {
            throw new \InvalidArgumentException('WHMCS service id is required for reconciliation.');
        }

        $command = match ($operationType) {
            'create' => 'ModuleCreate',
            'suspend' => 'ModuleSuspend',
            'unsuspend' => 'ModuleUnsuspend',
            'terminate' => 'ModuleTerminate',
            'change_package' => 'ModuleChangePackage',
            'change_password' => null,
            default => null,
        };

        if ($command === null) {
            return [
                'success' => false,
                'unsupported' => true,
                'message' => $operationType === 'change_password'
                    ? 'Automatic password-operation replay is unsafe because the intended password is not stored in the durable journal.'
                    : 'Unsupported CAPTAiNFiN reconciliation operation: ' . $operationType,
            ];
        }

        try {
            $result = ($this->api)($command, ['serviceid' => $serviceId]);
        } catch (\Throwable $error) {
            return [
                'success' => false,
                'unsupported' => false,
                'message' => 'WHMCS module replay failed: ' . $error->getMessage(),
            ];
        }

        $success = strtolower(trim((string) ($result['result'] ?? ''))) === 'success';
        if ($success) {
            return [
                'success' => true,
                'unsupported' => false,
                'message' => '',
                'command' => $command,
            ];
        }

        $message = trim((string) ($result['message'] ?? $result['error'] ?? 'WHMCS module command failed.'));

        return [
            'success' => false,
            'unsupported' => false,
            'message' => $message !== '' ? $message : 'WHMCS module command failed.',
            'command' => $command,
        ];
    }
}
