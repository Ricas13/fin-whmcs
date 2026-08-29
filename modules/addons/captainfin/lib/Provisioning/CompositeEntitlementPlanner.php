<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Provisioning;

final class CompositeEntitlementPlanner
{
    /** @param array<string,mixed> $servicePolicy */
    public static function desired(array $servicePolicy, bool $active): array
    {
        return [
            'jellyfin' => ['enabled' => $active],
            'jellyseerr' => [
                'enabled' => $active && self::bool($servicePolicy['jellyseerr_access'] ?? false),
                'permissions' => self::list($servicePolicy['jellyseerr_permissions'] ?? []),
            ],
            'stremio' => [
                'enabled' => $active && self::bool($servicePolicy['stremio_access'] ?? false),
            ],
            'discord' => [
                'enabled' => $active && trim((string) ($servicePolicy['discord_role_id'] ?? '')) !== '',
                'role_id' => trim((string) ($servicePolicy['discord_role_id'] ?? '')) ?: null,
            ],
        ];
    }

    private static function list(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/\s*,\s*/', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_unique(array_filter(array_map(static fn ($v): string => trim((string) $v), $value))));
    }

    private static function bool(mixed $value): bool
    {
        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function __construct()
    {
    }
}
