<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Provisioning;

use CaptainFin\Whmcs\Provisioning\CompositeEntitlementPlanner;
use PHPUnit\Framework\TestCase;

final class CompositeEntitlementPlannerTest extends TestCase
{
    public function testActiveServiceEnablesConfiguredIntegrations(): void
    {
        $desired = CompositeEntitlementPlanner::desired([
            'jellyseerr_access' => true,
            'stremio_access' => true,
            'discord_role_id' => '123456789012345678',
        ], true);

        self::assertTrue($desired['jellyfin']['enabled']);
        self::assertTrue($desired['jellyseerr']['enabled']);
        self::assertTrue($desired['stremio']['enabled']);
        self::assertTrue($desired['discord']['enabled']);
    }

    public function testSuspensionDisablesAllAccessEvenWhenProductOptionsRemainEnabled(): void
    {
        $desired = CompositeEntitlementPlanner::desired([
            'jellyseerr_access' => true,
            'stremio_access' => true,
            'discord_role_id' => '123456789012345678',
        ], false);

        self::assertFalse($desired['jellyfin']['enabled']);
        self::assertFalse($desired['jellyseerr']['enabled']);
        self::assertFalse($desired['stremio']['enabled']);
        self::assertFalse($desired['discord']['enabled']);
    }
}
