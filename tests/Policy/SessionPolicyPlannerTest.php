<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Policy;

use CaptainFin\Whmcs\Policy\SessionPolicyPlanner;
use PHPUnit\Framework\TestCase;

final class SessionPolicyPlannerTest extends TestCase
{
    public function testConcurrentStreamLimitKeepsOldestSessions(): void
    {
        $sessions = [
            ['id' => 'new', 'started_at' => '2026-08-29T10:02:00Z'],
            ['id' => 'old', 'started_at' => '2026-08-29T10:00:00Z'],
            ['id' => 'mid', 'started_at' => '2026-08-29T10:01:00Z'],
        ];

        $plan = SessionPolicyPlanner::plan($sessions, ['max_streams' => 2]);
        self::assertSame(['new'], $plan['terminate_session_ids']);
        self::assertSame('stream_limit', $plan['reasons']['new']);
    }

    public function testTranscodeAnd4kRulesAreIndependentFromDirectPlay(): void
    {
        $sessions = [
            ['id' => 'direct', 'started_at' => '2026-08-29T10:00:00Z', 'is_transcoding' => false, 'source_height' => 2160],
            ['id' => 'tx1', 'started_at' => '2026-08-29T10:01:00Z', 'is_transcoding' => true, 'source_height' => 1080],
            ['id' => 'tx4k', 'started_at' => '2026-08-29T10:02:00Z', 'is_transcoding' => true, 'source_height' => 2160],
        ];

        $plan = SessionPolicyPlanner::plan($sessions, ['max_transcodes' => 1, 'block_4k_transcode' => true]);
        self::assertContains('tx4k', $plan['terminate_session_ids']);
        self::assertNotContains('direct', $plan['terminate_session_ids']);
        self::assertSame('4k_transcode_blocked', $plan['reasons']['tx4k']);
    }

    public function testStrictSingleIpKeepsOldestKnownNetworkAndDoesNotPunishUnknownIp(): void
    {
        $sessions = [
            ['id' => 'home1', 'started_at' => '2026-08-29T10:00:00Z', 'ip' => '203.0.113.10'],
            ['id' => 'unknown', 'started_at' => '2026-08-29T10:00:30Z', 'ip' => ''],
            ['id' => 'away1', 'started_at' => '2026-08-29T10:01:00Z', 'ip' => '198.51.100.5'],
            ['id' => 'away2', 'started_at' => '2026-08-29T10:02:00Z', 'ip' => '198.51.100.5'],
        ];

        $plan = SessionPolicyPlanner::plan($sessions, ['network_policy' => 'strict_single_ip']);
        self::assertSame(['away1', 'away2'], $plan['terminate_session_ids']);
        self::assertNotContains('unknown', $plan['terminate_session_ids']);
    }
}
