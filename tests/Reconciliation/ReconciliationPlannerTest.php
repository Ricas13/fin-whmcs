<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Reconciliation;

use CaptainFin\Whmcs\Reconciliation\ReconciliationPlanner;
use PHPUnit\Framework\TestCase;

final class ReconciliationPlannerTest extends TestCase
{
    private ReconciliationPlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new ReconciliationPlanner();
    }

    public function testNewerOperationSupersedesStaleIntent(): void
    {
        $plan = $this->planner->plan(
            $this->operation(10, 'suspend'),
            $this->operation(11, 'unsuspend'),
            $this->service('Active')
        );

        self::assertSame(ReconciliationPlanner::SUPERSEDE, $plan['action']);
        self::assertStringContainsString('#11', $plan['reason']);
    }

    public function testEndedServiceConvertsStaleGrantIntoCleanup(): void
    {
        $operation = $this->operation(10, 'create');
        $plan = $this->planner->plan($operation, $operation, $this->service('Terminated'));

        self::assertSame(ReconciliationPlanner::DISPATCH, $plan['action']);
        self::assertSame('terminate', $plan['dispatch_type']);
    }

    public function testEndedTerminationRemainsTermination(): void
    {
        $operation = $this->operation(10, 'terminate');
        $plan = $this->planner->plan($operation, $operation, $this->service('Cancelled'));

        self::assertSame(ReconciliationPlanner::DISPATCH, $plan['action']);
        self::assertSame('terminate', $plan['dispatch_type']);
    }

    public function testSuspendedUnresolvedCreateDoesNotGrantAccess(): void
    {
        $operation = $this->operation(10, 'create');
        $plan = $this->planner->plan($operation, $operation, $this->service('Suspended'));

        self::assertSame(ReconciliationPlanner::MANUAL_ATTENTION, $plan['action']);
        self::assertNull($plan['dispatch_type']);
    }

    public function testDifferentWhmcsModuleBlocksReplay(): void
    {
        $operation = $this->operation(10, 'suspend');
        $service = $this->service('Active');
        $service->servertype = 'cpanel';

        $plan = $this->planner->plan($operation, $operation, $service);

        self::assertSame(ReconciliationPlanner::MANUAL_ATTENTION, $plan['action']);
        self::assertStringContainsString('no longer assigned', $plan['reason']);
    }

    public function testMissingWhmcsServiceBlocksReplay(): void
    {
        $operation = $this->operation(10, 'terminate');
        $plan = $this->planner->plan($operation, $operation, null);

        self::assertSame(ReconciliationPlanner::MANUAL_ATTENTION, $plan['action']);
    }

    public function testPasswordReplayIsNeverAutomatic(): void
    {
        $operation = $this->operation(10, 'change_password');
        $plan = $this->planner->plan($operation, $operation, $this->service('Active'));

        self::assertSame(ReconciliationPlanner::MANUAL_ATTENTION, $plan['action']);
        self::assertStringContainsString('password', strtolower($plan['reason']));
    }

    public function testAttemptBudgetStopsInfiniteRetry(): void
    {
        $operation = $this->operation(10, 'create', 20);
        $plan = $this->planner->plan($operation, $operation, $this->service('Active'), 20);

        self::assertSame(ReconciliationPlanner::MANUAL_ATTENTION, $plan['action']);
        self::assertStringContainsString('20', $plan['reason']);
    }

    public function testNormalLatestOperationIsDispatchedUnchanged(): void
    {
        $operation = $this->operation(10, 'change_package');
        $plan = $this->planner->plan($operation, $operation, $this->service('Active'));

        self::assertSame(ReconciliationPlanner::DISPATCH, $plan['action']);
        self::assertSame('change_package', $plan['dispatch_type']);
    }

    private function operation(int $id, string $type, int $attempts = 0): object
    {
        return (object) [
            'id' => $id,
            'service_id' => 123,
            'operation_type' => $type,
            'attempts' => $attempts,
        ];
    }

    private function service(string $status): object
    {
        return (object) [
            'id' => 123,
            'domainstatus' => $status,
            'servertype' => 'captainfin',
        ];
    }
}
