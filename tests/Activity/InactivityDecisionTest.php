<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Activity;

use CaptainFin\Whmcs\Activity\InactivityDecision;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class InactivityDecisionTest extends TestCase
{
    public function testUntrustedTelemetryAlwaysSkipsDestructiveEnforcement(): void
    {
        $result = InactivityDecision::evaluate(
            30,
            new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            new DateTimeImmutable('2026-08-29T00:00:00+00:00'),
            ['ready' => false]
        );

        self::assertFalse($result['enforce']);
        self::assertSame('telemetry_untrusted', $result['reason']);
    }

    public function testNoObservationBaselineDoesNotPretendCustomerIsInactive(): void
    {
        $result = InactivityDecision::evaluate(30, null, new DateTimeImmutable('2026-08-29T00:00:00+00:00'), ['ready' => true]);
        self::assertFalse($result['enforce']);
        self::assertSame('no_observation_baseline', $result['reason']);
    }

    public function testTrustedOldObservationCanEnforce(): void
    {
        $result = InactivityDecision::evaluate(
            30,
            new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
            new DateTimeImmutable('2026-08-29T00:00:00+00:00'),
            ['ready' => true]
        );
        self::assertTrue($result['enforce']);
    }
}
