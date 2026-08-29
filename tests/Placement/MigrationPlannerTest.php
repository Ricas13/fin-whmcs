<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Placement;

use CaptainFin\Whmcs\Placement\MigrationPlanner;
use PHPUnit\Framework\TestCase;

final class MigrationPlannerTest extends TestCase
{
    public function testMigrationRequiresEligibleDistinctTargetAndFreeUsername(): void
    {
        $source = ['id' => 1, 'name' => 'old'];
        $target = ['id' => 2, 'name' => 'new', 'enabled' => true, 'allow_new_users' => true, 'health_status' => 'healthy'];

        $plan = MigrationPlanner::plan($source, $target, 'ricardo', true);
        self::assertSame(1, $plan['source_server_id']);
        self::assertSame(2, $plan['target_server_id']);
        self::assertContains('persist_target_remote_identity', $plan['steps']);
        self::assertContains('disable_source', $plan['steps']);
    }

    public function testExistingUsernameOnTargetStopsMigrationInsteadOfAdoptingIt(): void
    {
        $this->expectException(\RuntimeException::class);
        MigrationPlanner::plan(
            ['id' => 1, 'name' => 'old'],
            ['id' => 2, 'name' => 'new', 'enabled' => true, 'allow_new_users' => true, 'health_status' => 'healthy'],
            'ricardo',
            false
        );
    }
}
