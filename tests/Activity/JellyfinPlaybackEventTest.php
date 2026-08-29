<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Activity;

use CaptainFin\Whmcs\Activity\JellyfinPlaybackEvent;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class JellyfinPlaybackEventTest extends TestCase
{
    public function testParsesStartAndNormalisesIpv4Endpoint(): void
    {
        $event = JellyfinPlaybackEvent::parse([
            'NotificationType' => 'PlaybackStart',
            'UserId' => 'u1',
            'SessionId' => 's1',
            'ItemId' => 'i1',
            'RemoteEndPoint' => '203.0.113.5:4567',
        ], new DateTimeImmutable('2026-08-29T22:00:00Z'));

        self::assertSame('start', $event['type']);
        self::assertSame('203.0.113.5', $event['remote_endpoint']);
        self::assertSame('jellyfin_webhook', $event['source']);
    }

    public function testIgnoresUnrelatedOrUnidentifiableEvents(): void
    {
        $now = new DateTimeImmutable('2026-08-29T22:00:00Z');
        self::assertNull(JellyfinPlaybackEvent::parse(['NotificationType' => 'ItemAdded'], $now));
        self::assertNull(JellyfinPlaybackEvent::parse(['NotificationType' => 'PlaybackStop', 'UserId' => 'u1'], $now));
    }
}
