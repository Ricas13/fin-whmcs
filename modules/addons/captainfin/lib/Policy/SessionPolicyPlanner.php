<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Policy;

final class SessionPolicyPlanner
{
    /**
     * @param array<int,array<string,mixed>> $sessions oldest or newest input order is irrelevant
     * @param array<string,mixed> $policy
     */
    public static function plan(array $sessions, array $policy): array
    {
        $normalised = array_values(array_filter(array_map([self::class, 'session'], $sessions)));
        usort($normalised, static fn (array $a, array $b): int => [$a['started_at'], $a['id']] <=> [$b['started_at'], $b['id']]);

        $terminate = [];
        $reasons = [];

        $block4k = self::bool($policy['block_4k_transcode'] ?? (($policy['four_k_transcode_policy'] ?? '') === 'block'));
        if ($block4k) {
            foreach ($normalised as $session) {
                if ($session['is_transcoding'] && $session['source_height'] >= 2160) {
                    self::mark($terminate, $reasons, $session['id'], '4k_transcode_blocked');
                }
            }
        }

        $maxStreams = max(0, (int) ($policy['max_streams'] ?? $policy['streams'] ?? 0));
        self::enforceCount($normalised, $terminate, $reasons, $maxStreams, 'stream_limit', static fn (array $session): bool => true);

        $maxTranscodes = max(0, (int) ($policy['max_transcodes'] ?? 0));
        self::enforceCount($normalised, $terminate, $reasons, $maxTranscodes, 'transcode_limit', static fn (array $session): bool => $session['is_transcoding']);

        $networkPolicy = strtolower(trim((string) ($policy['network_policy'] ?? 'allow')));
        $maxIps = max(0, (int) ($policy['max_ips'] ?? 0));
        if ($networkPolicy === 'strict_single_ip') {
            $maxIps = 1;
        }
        if ($networkPolicy === 'household' && $maxIps === 0) {
            $maxIps = 1;
        }
        if ($maxIps > 0) {
            self::enforceNetworks($normalised, $terminate, $reasons, $maxIps);
        }

        $ids = array_keys($terminate);
        sort($ids, SORT_STRING);

        return [
            'terminate_session_ids' => $ids,
            'reasons' => $reasons,
            'inspected' => count($normalised),
            'allowed' => count($normalised) - count($ids),
        ];
    }

    /**
     * Keep the oldest currently-allowed sessions and terminate newer excess.
     * A session already selected for a stronger rule does not consume capacity.
     */
    private static function enforceCount(array $sessions, array &$terminate, array &$reasons, int $limit, string $reason, callable $predicate): void
    {
        if ($limit <= 0) {
            return;
        }
        $kept = 0;
        foreach ($sessions as $session) {
            if (isset($terminate[$session['id']]) || !$predicate($session)) {
                continue;
            }
            if ($kept < $limit) {
                $kept++;
                continue;
            }
            self::mark($terminate, $reasons, $session['id'], $reason);
        }
    }

    private static function enforceNetworks(array $sessions, array &$terminate, array &$reasons, int $maxIps): void
    {
        $allowedNetworks = [];
        foreach ($sessions as $session) {
            if (isset($terminate[$session['id']])) {
                continue;
            }
            $network = $session['network_key'];
            if ($network === '') {
                // Unknown network identity must not trigger destructive policy.
                continue;
            }
            if (isset($allowedNetworks[$network])) {
                continue;
            }
            if (count($allowedNetworks) < $maxIps) {
                $allowedNetworks[$network] = true;
                continue;
            }
            self::mark($terminate, $reasons, $session['id'], 'network_limit');
        }

        // Once a network is rejected, all later sessions from the same network
        // must be rejected too even though it was never added to allowedNetworks.
        $rejectedNetworks = [];
        foreach ($sessions as $session) {
            if (($reasons[$session['id']] ?? null) === 'network_limit' && $session['network_key'] !== '') {
                $rejectedNetworks[$session['network_key']] = true;
            }
        }
        foreach ($sessions as $session) {
            if (!isset($terminate[$session['id']]) && isset($rejectedNetworks[$session['network_key']])) {
                self::mark($terminate, $reasons, $session['id'], 'network_limit');
            }
        }
    }

    private static function mark(array &$terminate, array &$reasons, string $sessionId, string $reason): void
    {
        if (!isset($terminate[$sessionId])) {
            $terminate[$sessionId] = true;
            $reasons[$sessionId] = $reason;
        }
    }

    /** @param array<string,mixed> $session */
    private static function session(array $session): ?array
    {
        $id = trim((string) ($session['id'] ?? $session['session_id'] ?? ''));
        if ($id === '') {
            return null;
        }
        return [
            'id' => $id,
            'started_at' => trim((string) ($session['started_at'] ?? $session['start_time'] ?? '9999-12-31T23:59:59Z')),
            'is_transcoding' => self::bool($session['is_transcoding'] ?? false),
            'source_height' => max(0, (int) ($session['source_height'] ?? $session['height'] ?? 0)),
            'network_key' => trim((string) ($session['network_key'] ?? $session['ip'] ?? '')),
        ];
    }

    private static function bool(mixed $value): bool
    {
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function __construct()
    {
    }
}
