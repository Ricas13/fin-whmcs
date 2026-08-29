<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Activity;

use DateTimeImmutable;

final class JellyfinPlaybackEvent
{
    /** @param array<string,mixed> $payload */
    public static function parse(array $payload, DateTimeImmutable $receivedAt): ?array
    {
        $typeRaw = strtolower(trim((string) ($payload['NotificationType'] ?? $payload['notification_type'] ?? $payload['event'] ?? '')));
        $type = match ($typeRaw) {
            'playbackstart', 'playback_start', 'start', 'playing' => 'start',
            'playbackstop', 'playback_stop', 'stop', 'stopped' => 'stop',
            default => null,
        };
        if ($type === null) {
            return null;
        }

        $userId = trim((string) ($payload['UserId'] ?? $payload['user_id'] ?? ''));
        $sessionId = trim((string) ($payload['SessionId'] ?? $payload['session_id'] ?? ''));
        if ($userId === '' || $sessionId === '') {
            return null;
        }

        return [
            'type' => $type,
            'user_id' => $userId,
            'session_id' => $sessionId,
            'item_id' => trim((string) ($payload['ItemId'] ?? $payload['item_id'] ?? '')) ?: null,
            'client' => trim((string) ($payload['Client'] ?? $payload['client'] ?? '')) ?: null,
            'device_name' => trim((string) ($payload['DeviceName'] ?? $payload['device_name'] ?? '')) ?: null,
            'remote_endpoint' => self::normaliseEndpoint((string) ($payload['RemoteEndPoint'] ?? $payload['remote_endpoint'] ?? '')),
            'observed_at' => $receivedAt,
            'source' => 'jellyfin_webhook',
        ];
    }

    private static function normaliseEndpoint(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, '[') && str_contains($value, ']')) {
            return substr($value, 1, (int) strpos($value, ']') - 1) ?: null;
        }
        if (substr_count($value, ':') === 1) {
            [$host] = explode(':', $value, 2);
            return trim($host) ?: null;
        }
        return $value;
    }

    private function __construct()
    {
    }
}
