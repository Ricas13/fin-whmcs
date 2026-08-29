<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Diagnostics;

use CaptainFin\Whmcs\Diagnostics\HealthReport;
use PHPUnit\Framework\TestCase;

final class HealthReportTest extends TestCase
{
    public function testFailedCheckDominatesAndSecretsAreRedacted(): void
    {
        $report = HealthReport::fromChecks([
            ['name' => 'Jellyfin', 'status' => 'healthy', 'latency_ms' => 10],
            ['name' => 'Discord', 'status' => 'failed', 'detail' => 'Authorization: Bot secret-token'],
        ]);

        self::assertSame('failed', $report['overall']);
        self::assertStringNotContainsString('secret-token', $report['checks'][1]['detail']);
    }
}
