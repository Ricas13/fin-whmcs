<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Activity;

use DateTimeImmutable;

final class SessionObservationMapper
{
    /**
     * @param array<string,mixed> $session
     * @param array<string,DateTimeImmutable> $firstSeenBySession
     * @return array<string,mixed>|null
     */
    public static function map(array $session, DateTimeImmutable $observedAt, array $firstSeenBySession = []): ?array
    {
        $id = trim((string) ($session['Id'] ?? $session['id'] ?? ''));
        $userId = trim((string) ($session['UserId'] ?? $session['user_id'] ?? ''));
        $playing = is_array($session['NowPlayingItem'] ?? null) ? $session['NowPlayingItem'] : null;
        if ($id === '' || $userId === '' || $playing === null) {
            return null;
        }

        $firstSeen = $firstSeenBySession[$id] ?? $observedAt;
        $transcoding = is_array($session['TranscodingInfo'] ?? null);
        $height = max(0, (int) ($playing['Height'] ?? 0));

        return [
            'id' => $id,
            'session_id' => $id,
            'user_id' => $userId,
            'username' => trim((string) ($session['UserName'] ?? '')) ?: null,
            'item_id' => trim((string) ($playing['Id'] ?? '')) ?: null,
            'item_name' => trim((string) ($playing['Name'] ?? '')) ?: null,
            'source_height' => $height,
            'is_transcoding' => $transcoding,
            'network_key' => self::normaliseEndpoint((string) ($session['RemoteEndPoint'] ?? '')),
            'client' => trim((string) ($session['Client'] ?? '')) ?: null,
            'device_name' => trim((string) ($session['DeviceName'] ?? '')) ?: null,
            'started_at' => $firstSeen->format(DATE_ATOM),
            'observed_at' => $observedAt,
            'source' => 'jellyfin_session_poll',
        ];
    }

    private static function normaliseEndpoint(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (str_starts_with($value, '[') && ($end = strpos($value, ']')) !== false) {
            return substr($value, 1, $end - 1);
        }
        if (substr_count($value, ':') === 1) {
            return trim(explode(':', $value, 2)[0]);
        }
        return $value;
    }

    private function __construct()
    {
    }
}
