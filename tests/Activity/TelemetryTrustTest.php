<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Activity;

use CaptainFin\Whmcs\Activity\TelemetryTrust;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class TelemetryTrustTest extends TestCase
{
    public function testUnrelatedOfflineServerDoesNotBlockRelevantPlan(): void
    {
        $now = new DateTimeImmutable('2026-08-29T22:00:00+00:00');
        $servers = [
            ['id' => 1, 'enabled' => true, 'last_poll_status' => 'ok', 'last_successful_poll_at' => '2026-08-29T21:59:40+00:00'],
            ['id' => 2, 'enabled' => true, 'last_poll_status' => 'failed', 'last_successful_poll_at' => '2026-08-29T21:00:00+00:00'],
        ];

        $result = TelemetryTrust::evaluate($servers, [1], $now, new DateTimeImmutable('2026-08-29T21:59:30+00:00'));
        self::assertTrue($result['ready']);
    }

    public function testRelevantFailedPollFailsClosedForDestructivePolicy(): void
    {
        $now = new DateTimeImmutable('2026-08-29T22:00:00+00:00');
        $servers = [['id' => 1, 'enabled' => true, 'last_poll_status' => 'failed', 'last_successful_poll_at' => '2026-08-29T21:59:50+00:00']];

        $result = TelemetryTrust::evaluate($servers, [1], $now, new DateTimeImmutable('2026-08-29T21:59:30+00:00'));
        self::assertFalse($result['ready']);
        self::assertSame([1], $result['untrusted_server_ids']);
    }

    public function testStaleWorkerBlocksEvenFreshServerPoll(): void
    {
        $now = new DateTimeImmutable('2026-08-29T22:00:00+00:00');
        $servers = [['id' => 1, 'enabled' => true, 'last_poll_status' => 'ok', 'last_successful_poll_at' => '2026-08-29T21:59:55+00:00']];

        $result = TelemetryTrust::evaluate($servers, [1], $now, new DateTimeImmutable('2026-08-29T21:55:00+00:00'));
        self::assertFalse($result['ready']);
        self::assertSame('worker_stale', $result['reason']);
    }
}
