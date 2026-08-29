<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Activity;

use CaptainFin\Whmcs\Activity\SessionObservationMapper;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SessionObservationMapperTest extends TestCase
{
    public function testMapsPlayingSessionWithTranscodeAndNetworkData(): void
    {
        $now = new DateTimeImmutable('2026-08-29T22:00:00Z');
        $first = new DateTimeImmutable('2026-08-29T21:55:00Z');
        $mapped = SessionObservationMapper::map([
            'Id' => 'session-1',
            'UserId' => 'user-1',
            'UserName' => 'Ricardo',
            'RemoteEndPoint' => '203.0.113.10:5555',
            'Client' => 'Android TV',
            'DeviceName' => 'Shield',
            'NowPlayingItem' => ['Id' => 'item-1', 'Name' => 'Movie', 'Height' => 2160],
            'TranscodingInfo' => ['VideoCodec' => 'h264'],
        ], $now, ['session-1' => $first]);

        self::assertNotNull($mapped);
        self::assertTrue($mapped['is_transcoding']);
        self::assertSame(2160, $mapped['source_height']);
        self::assertSame('203.0.113.10', $mapped['network_key']);
        self::assertSame($first->format(DATE_ATOM), $mapped['started_at']);
    }

    public function testIgnoresIdleOrUnownedSessions(): void
    {
        $now = new DateTimeImmutable('2026-08-29T22:00:00Z');
        self::assertNull(SessionObservationMapper::map(['Id' => 'idle', 'UserId' => 'u1'], $now));
        self::assertNull(SessionObservationMapper::map(['Id' => 'playing', 'NowPlayingItem' => ['Id' => 'x']], $now));
    }
}
