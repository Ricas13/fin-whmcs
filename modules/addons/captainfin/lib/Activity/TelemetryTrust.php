<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Activity;

use DateTimeImmutable;

final class TelemetryTrust
{
    /**
     * @param array<int,array<string,mixed>|object> $servers
     * @param int[] $requiredServerIds
     */
    public static function evaluate(
        array $servers,
        array $requiredServerIds,
        DateTimeImmutable $now,
        ?DateTimeImmutable $workerHeartbeat,
        int $workerMaxAgeSeconds = 120,
        int $pollIntervalSeconds = 30,
        int $pollSlackSeconds = 20
    ): array {
        if ($workerHeartbeat === null || ($now->getTimestamp() - $workerHeartbeat->getTimestamp()) > $workerMaxAgeSeconds) {
            return ['ready' => false, 'reason' => 'worker_stale', 'untrusted_server_ids' => array_values($requiredServerIds)];
        }

        $byId = [];
        foreach ($servers as $server) {
            $s = is_object($server) ? get_object_vars($server) : $server;
            $id = (int) ($s['id'] ?? 0);
            if ($id > 0) {
                $byId[$id] = $s;
            }
        }

        $maxPollAge = max(1, $pollIntervalSeconds + $pollSlackSeconds);
        $untrusted = [];
        foreach (array_values(array_unique(array_map('intval', $requiredServerIds))) as $serverId) {
            $server = $byId[$serverId] ?? null;
            if ($server === null) {
                $untrusted[] = $serverId;
                continue;
            }

            if (!self::bool($server['enabled'] ?? true)) {
                $untrusted[] = $serverId;
                continue;
            }

            $status = strtolower(trim((string) ($server['last_poll_status'] ?? $server['health_status'] ?? '')));
            if (!in_array($status, ['ok', 'healthy', 'success'], true)) {
                $untrusted[] = $serverId;
                continue;
            }

            $lastPoll = self::date($server['last_successful_poll_at'] ?? $server['last_poll_at'] ?? null);
            if ($lastPoll === null || ($now->getTimestamp() - $lastPoll->getTimestamp()) > $maxPollAge) {
                $untrusted[] = $serverId;
            }
        }

        return [
            'ready' => $untrusted === [],
            'reason' => $untrusted === [] ? 'ready' : 'server_telemetry_stale',
            'untrusted_server_ids' => $untrusted,
        ];
    }

    private static function date(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return new DateTimeImmutable($value->format(DATE_ATOM));
        }
        if (is_string($value) && trim($value) !== '') {
            try {
                return new DateTimeImmutable($value);
            } catch (\Throwable) {
                return null;
            }
        }
        return null;
    }

    private static function bool(mixed $value): bool
    {
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function __construct()
    {
    }
}
