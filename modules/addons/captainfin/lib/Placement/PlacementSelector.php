<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Placement;

final class PlacementSelector
{
    /**
     * @param array<int,array<string,mixed>|object> $servers
     * @return array<string,mixed>|object|null
     */
    public static function select(array $servers, string $strategy = 'balanced'): array|object|null
    {
        $eligible = array_values(array_filter($servers, [self::class, 'eligible']));
        if ($eligible === []) {
            return null;
        }

        usort($eligible, static function ($a, $b) use ($strategy): int {
            $left = self::normalise($a);
            $right = self::normalise($b);

            $leftScore = self::score($left, $strategy);
            $rightScore = self::score($right, $strategy);

            foreach ($leftScore as $index => $value) {
                $comparison = $value <=> $rightScore[$index];
                if ($comparison !== 0) {
                    return $comparison;
                }
            }

            return strcmp((string) $left['name'], (string) $right['name']);
        });

        return $eligible[0];
    }

    /** @param array<string,mixed>|object $server */
    public static function eligible(array|object $server): bool
    {
        $s = self::normalise($server);
        if (!$s['enabled'] || !$s['allow_new_users']) {
            return false;
        }
        if (!in_array($s['health_status'], ['healthy', 'degraded'], true)) {
            return false;
        }
        if ($s['placement_mode'] !== 'active') {
            return false;
        }
        if ($s['max_users'] > 0 && $s['assigned_users'] >= $s['max_users']) {
            return false;
        }

        return true;
    }

    /** @param array<string,mixed> $server @return array<int,int|float|string> */
    private static function score(array $server, string $strategy): array
    {
        $priority = (int) $server['priority'];
        $users = (int) $server['assigned_users'];
        $streams = (int) $server['active_streams'];
        $weight = max(1, (int) $server['placement_weight']);
        $capacity = max(1, (int) ($server['max_users'] ?: 1));
        $utilisation = $server['max_users'] > 0 ? $users / $capacity : $users / $weight;

        return match ($strategy) {
            'priority' => [$priority, $users, $streams],
            'least_users' => [$users, $streams, $priority],
            'least_streams' => [$streams, $users, $priority],
            'weighted' => [$utilisation / $weight, $streams, $priority],
            default => [$utilisation, $streams, $priority],
        };
    }

    /** @param array<string,mixed>|object $server @return array<string,mixed> */
    private static function normalise(array|object $server): array
    {
        $s = is_object($server) ? get_object_vars($server) : $server;

        return [
            'id' => (int) ($s['id'] ?? 0),
            'name' => trim((string) ($s['name'] ?? '')),
            'enabled' => self::bool($s['enabled'] ?? true),
            'allow_new_users' => self::bool($s['allow_new_users'] ?? true),
            'health_status' => strtolower(trim((string) ($s['health_status'] ?? 'healthy'))),
            'placement_mode' => strtolower(trim((string) ($s['placement_mode'] ?? 'active'))),
            'priority' => (int) ($s['priority'] ?? 100),
            'assigned_users' => max(0, (int) ($s['assigned_users'] ?? 0)),
            'active_streams' => max(0, (int) ($s['active_streams'] ?? 0)),
            'max_users' => max(0, (int) ($s['max_users'] ?? 0)),
            'placement_weight' => max(1, (int) ($s['placement_weight'] ?? 100)),
        ];
    }

    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function __construct()
    {
    }
}
