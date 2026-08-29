<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Reconciliation;

final class ReconciliationPlanner
{
    public const DISPATCH = 'dispatch';
    public const SUPERSEDE = 'supersede';
    public const MANUAL_ATTENTION = 'manual_attention';

    public function plan(object $operation, ?object $latest, ?object $service, int $maxAttempts = 20): array
    {
        if ($latest !== null && (int) $latest->id !== (int) $operation->id) {
            return [
                'action' => self::SUPERSEDE,
                'dispatch_type' => null,
                'reason' => sprintf('Superseded by newer CAPTAiNFiN operation #%d.', (int) $latest->id),
            ];
        }

        if ((int) ($operation->attempts ?? 0) >= $maxAttempts) {
            return [
                'action' => self::MANUAL_ATTENTION,
                'dispatch_type' => null,
                'reason' => sprintf('Automatic reconciliation stopped after %d recorded attempts.', (int) $operation->attempts),
            ];
        }

        $operationType = trim((string) ($operation->operation_type ?? ''));
        if ($operationType === 'change_password') {
            return [
                'action' => self::MANUAL_ATTENTION,
                'dispatch_type' => null,
                'reason' => 'Automatic password-operation replay is unsafe because the intended password is not stored in the durable journal.',
            ];
        }

        if ($service === null) {
            return [
                'action' => self::MANUAL_ATTENTION,
                'dispatch_type' => null,
                'reason' => 'The WHMCS service no longer exists; automatic remote recovery cannot safely reconstruct its credentials or server assignment.',
            ];
        }

        if (strtolower(trim((string) ($service->servertype ?? ''))) !== 'captainfin') {
            return [
                'action' => self::MANUAL_ATTENTION,
                'dispatch_type' => null,
                'reason' => 'The WHMCS service is no longer assigned to the CAPTAiNFiN module; automatic replay was blocked.',
            ];
        }

        $status = strtolower(trim((string) ($service->domainstatus ?? '')));
        if ($status === 'suspended' && $operationType === 'create') {
            return [
                'action' => self::MANUAL_ATTENTION,
                'dispatch_type' => null,
                'reason' => 'WHMCS currently marks the service Suspended while its create operation is unresolved; automatic creation was blocked to avoid granting active access.',
            ];
        }

        if (in_array($status, ['cancelled', 'terminated'], true) && $operationType !== 'terminate') {
            return [
                'action' => self::DISPATCH,
                'dispatch_type' => 'terminate',
                'reason' => 'The WHMCS service is ended, so stale lifecycle intent is converted to cleanup.',
            ];
        }

        return [
            'action' => self::DISPATCH,
            'dispatch_type' => $operationType,
            'reason' => '',
        ];
    }
}
