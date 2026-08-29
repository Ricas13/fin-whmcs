<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Placement;

use CaptainFin\Whmcs\Placement\PlacementSelector;
use PHPUnit\Framework\TestCase;

final class PlacementSelectorTest extends TestCase
{
    public function testExcludesOfflineDrainingAndFullServers(): void
    {
        $servers = [
            ['id' => 1, 'name' => 'offline', 'health_status' => 'offline', 'assigned_users' => 0],
            ['id' => 2, 'name' => 'draining', 'placement_mode' => 'drain', 'assigned_users' => 0],
            ['id' => 3, 'name' => 'full', 'max_users' => 10, 'assigned_users' => 10],
            ['id' => 4, 'name' => 'healthy', 'assigned_users' => 3],
        ];

        $selected = PlacementSelector::select($servers, 'least_users');
        self::assertSame(4, $selected['id']);
    }

    public function testLeastUsersAndLeastStreamsAreDeterministic(): void
    {
        $servers = [
            ['id' => 1, 'name' => 'a', 'assigned_users' => 8, 'active_streams' => 0],
            ['id' => 2, 'name' => 'b', 'assigned_users' => 2, 'active_streams' => 4],
        ];

        self::assertSame(2, PlacementSelector::select($servers, 'least_users')['id']);
        self::assertSame(1, PlacementSelector::select($servers, 'least_streams')['id']);
    }

    public function testBalancedUsesConfiguredCapacity(): void
    {
        $servers = [
            ['id' => 1, 'name' => 'small', 'assigned_users' => 9, 'max_users' => 10],
            ['id' => 2, 'name' => 'large', 'assigned_users' => 20, 'max_users' => 100],
        ];

        self::assertSame(2, PlacementSelector::select($servers, 'balanced')['id']);
    }
}
