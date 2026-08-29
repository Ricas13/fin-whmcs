<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Reconciliation;

use CaptainFin\Whmcs\Domain\OperationState;
use CaptainFin\Whmcs\Infrastructure\Database\OperationRepository;
use DateTimeImmutable;

final class Reconciler
{
    private const MAX_ATTEMPTS = 20;

    private OperationRepository $operations;
    private ServiceStateRepository $services;
    private WhmcsModuleCommandRunner $runner;
    private ReconciliationLock $lock;

    public function __construct(
        ?OperationRepository $operations = null,
        ?ServiceStateRepository $services = null,
        ?WhmcsModuleCommandRunner $runner = null,
        ?ReconciliationLock $lock = null
    ) {
        $this->operations = $operations ?? new OperationRepository();
        $this->services = $services ?? new ServiceStateRepository();
        $this->runner = $runner ?? new WhmcsModuleCommandRunner();
        $this->lock = $lock ?? new ReconciliationLock();
    }

    public function run(int $limit = 20): array
    {
        return $this->lock->run(fn (): array => $this->runLocked($limit));
    }

    private function runLocked(int $limit): array
    {
        $summary = [
            'skipped' => false,
            'inspected' => 0,
            'recovered' => 0,
            'superseded' => 0,
            'manual_attention' => 0,
            'retry_scheduled' => 0,
            'unchanged' => 0,
        ];

        foreach ($this->operations->dueForReconciliation($limit) as $candidate) {
            $summary['inspected']++;
            $operation = $this->operations->findById((int) $candidate->id);

            if ($operation === null || OperationState::isTerminal((string) $operation->state)) {
                $summary['unchanged']++;
                continue;
            }

            $latest = $this->operations->latestForService((int) $operation->service_id);
            if ($latest !== null && (int) $latest->id !== (int) $operation->id) {
                $this->operations->markSuperseded(
                    (int) $operation->id,
                    sprintf('Superseded by newer CAPTAiNFiN operation #%d.', (int) $latest->id)
                );
                $summary['superseded']++;
                continue;
            }

            if ((int) $operation->attempts >= self::MAX_ATTEMPTS) {
                $this->operations->markManualAttention(
                    (int) $operation->id,
                    sprintf('Automatic reconciliation stopped after %d recorded attempts.', (int) $operation->attempts)
                );
                $summary['manual_attention']++;
                continue;
            }

            $operationType = trim((string) $operation->operation_type);
            if ($operationType === 'change_password') {
                $this->operations->markManualAttention(
                    (int) $operation->id,
                    'Automatic password-operation replay is unsafe because the intended password is not stored in the durable journal.'
                );
                $summary['manual_attention']++;
                continue;
            }

            $service = $this->services->find((int) $operation->service_id);
            if ($service === null) {
                $this->operations->markManualAttention(
                    (int) $operation->id,
                    'The WHMCS service no longer exists; automatic remote recovery cannot safely reconstruct its credentials or server assignment.'
                );
                $summary['manual_attention']++;
                continue;
            }

            if (!$this->services->usesCaptainFin($service)) {
                $this->operations->markManualAttention(
                    (int) $operation->id,
                    'The WHMCS service is no longer assigned to the CAPTAiNFiN module; automatic replay was blocked.'
                );
                $summary['manual_attention']++;
                continue;
            }

            // If billing/admin state has already ended the service, never replay
            // stale access-granting work. Ask WHMCS to run CAPTAiNFiN termination
            // instead so durable remote identity can still be cleaned up.
            $dispatchType = $operationType;
            if ($this->services->isEnded($service) && $operationType !== 'terminate') {
                $dispatchType = 'terminate';
            }

            // A manually suspended service with an abandoned create is ambiguous:
            // replaying create would temporarily grant active access. A normal
            // CAPTAiNFiN suspend action would have produced a newer operation and
            // superseded this create already, so stop rather than guessing.
            if ($this->services->isSuspended($service) && $operationType === 'create') {
                $this->operations->markManualAttention(
                    (int) $operation->id,
                    'WHMCS currently marks the service Suspended while its create operation is unresolved; automatic creation was blocked to avoid granting active access.'
                );
                $summary['manual_attention']++;
                continue;
            }

            $result = $this->runner->run($dispatchType, (int) $operation->service_id);

            $latestAfter = $this->operations->latestForService((int) $operation->service_id);
            if ($latestAfter !== null && (int) $latestAfter->id !== (int) $operation->id) {
                $this->operations->markSuperseded(
                    (int) $operation->id,
                    sprintf(
                        'Superseded during reconciliation by newer CAPTAiNFiN operation #%d (%s).',
                        (int) $latestAfter->id,
                        (string) $latestAfter->operation_type
                    )
                );
                $summary['superseded']++;

                if ((string) $latestAfter->state === OperationState::LOCAL_APPLIED) {
                    $summary['recovered']++;
                } elseif ((string) $latestAfter->state === OperationState::MANUAL_ATTENTION) {
                    $summary['manual_attention']++;
                }
                continue;
            }

            $after = $this->operations->findById((int) $operation->id);
            if ($after === null) {
                $summary['unchanged']++;
                continue;
            }

            if ((string) $after->state === OperationState::LOCAL_APPLIED) {
                $summary['recovered']++;
                continue;
            }
            if ((string) $after->state === OperationState::MANUAL_ATTENTION) {
                $summary['manual_attention']++;
                continue;
            }

            if (!empty($result['unsupported'])) {
                $this->operations->markManualAttention((int) $operation->id, (string) $result['message']);
                $summary['manual_attention']++;
                continue;
            }

            if (empty($result['success'])) {
                // The module normally records its own provider/API failure. If
                // WHMCS failed before reaching the module and no retry schedule
                // exists, make the infrastructure failure durable with backoff.
                if ((string) $after->state !== OperationState::FAILED || $after->retry_after === null) {
                    $this->operations->markFailed(
                        (int) $operation->id,
                        (string) ($result['message'] ?? 'WHMCS module replay failed.'),
                        (new DateTimeImmutable())->modify('+5 minutes')
                    );
                }
                $summary['retry_scheduled']++;
                continue;
            }

            // A WHMCS success response without journal convergence is not proof
            // of local/remote convergence. Keep it retryable instead of hiding it.
            $this->operations->markFailed(
                (int) $operation->id,
                'WHMCS reported module replay success but the durable CAPTAiNFiN operation did not converge.',
                (new DateTimeImmutable())->modify('+5 minutes')
            );
            $summary['retry_scheduled']++;
        }

        return $summary;
    }
}
