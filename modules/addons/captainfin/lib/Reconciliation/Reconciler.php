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
    private ReconciliationPlanner $planner;

    public function __construct(
        ?OperationRepository $operations = null,
        ?ServiceStateRepository $services = null,
        ?WhmcsModuleCommandRunner $runner = null,
        ?ReconciliationLock $lock = null,
        ?ReconciliationPlanner $planner = null
    ) {
        $this->operations = $operations ?? new OperationRepository();
        $this->services = $services ?? new ServiceStateRepository();
        $this->runner = $runner ?? new WhmcsModuleCommandRunner();
        $this->lock = $lock ?? new ReconciliationLock();
        $this->planner = $planner ?? new ReconciliationPlanner();
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
            $service = $this->services->find((int) $operation->service_id);
            $plan = $this->planner->plan($operation, $latest, $service, self::MAX_ATTEMPTS);

            if ($plan['action'] === ReconciliationPlanner::SUPERSEDE) {
                $this->operations->markSuperseded((int) $operation->id, (string) $plan['reason']);
                $summary['superseded']++;
                continue;
            }

            if ($plan['action'] === ReconciliationPlanner::MANUAL_ATTENTION) {
                $this->operations->markManualAttention((int) $operation->id, (string) $plan['reason']);
                $summary['manual_attention']++;
                continue;
            }

            $dispatchType = (string) $plan['dispatch_type'];
            $result = $this->runner->run($dispatchType, (int) $operation->service_id);

            // Re-entering through WHMCS may create a newer operation if the
            // current product/service configuration changed since the stale
            // operation was recorded. Newer intent always wins.
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
