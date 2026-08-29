<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Policy;

final class LibraryPolicy
{
    /** @param string[] $catalog @param string[] $configured @param array<string,bool> $overrides */
    public static function entitlement(
        array $catalog,
        string $mode = 'all',
        array $configured = [],
        array $overrides = []
    ): array {
        $mode = in_array($mode, ['all', 'include', 'exclude'], true) ? $mode : 'all';
        $configuredKeys = array_fill_keys(array_map([self::class, 'key'], self::names($configured)), true);
        $overrideMap = [];
        foreach ($overrides as $name => $granted) {
            $overrideMap[self::key((string) $name)] = (bool) $granted;
        }

        $rows = [];
        foreach (self::names($catalog) as $name) {
            $key = self::key($name);
            $planGrant = match ($mode) {
                'include' => isset($configuredKeys[$key]),
                'exclude' => !isset($configuredKeys[$key]),
                default => true,
            };
            $hasOverride = array_key_exists($key, $overrideMap);
            $rows[] = [
                'name' => $name,
                'plan' => $planGrant,
                'override' => $hasOverride ? $overrideMap[$key] : null,
                'effective' => $hasOverride ? $overrideMap[$key] : $planGrant,
            ];
        }

        return $rows;
    }

    /** @param array<int,array{name:string,effective:bool}> $entitlementRows @param string[]|null $selection */
    public static function visible(array $entitlementRows, ?array $selection): array
    {
        $entitled = array_values(array_map(
            static fn (array $row): string => (string) $row['name'],
            array_filter($entitlementRows, static fn (array $row): bool => (bool) ($row['effective'] ?? false))
        ));

        if ($selection === null) {
            return $entitled;
        }

        $selected = array_fill_keys(array_map([self::class, 'key'], self::names($selection)), true);
        return array_values(array_filter($entitled, static fn (string $name): bool => isset($selected[self::key($name)])));
    }

    /** @param string[] $visible @param array<int,array{id:string,name:string}> $serverLibraries */
    public static function resolve(array $visible, array $serverLibraries): array
    {
        $byName = [];
        foreach ($serverLibraries as $library) {
            $id = trim((string) ($library['id'] ?? ''));
            $name = trim((string) ($library['name'] ?? ''));
            if ($id !== '' && $name !== '') {
                $byName[self::key($name)] = $id;
            }
        }

        $enabled = [];
        $missing = [];
        foreach (self::names($visible) as $name) {
            $id = $byName[self::key($name)] ?? null;
            if ($id === null) {
                $missing[] = $name;
            } else {
                $enabled[] = $id;
            }
        }

        return ['enabledFolders' => array_values(array_unique($enabled)), 'missing' => $missing];
    }

    /** @param string[] $names */
    public static function names(array $names): array
    {
        $unique = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $unique[self::key($name)] = $name;
            }
        }
        return array_values($unique);
    }

    private static function key(string $name): string
    {
        return mb_strtolower(trim($name), 'UTF-8');
    }

    private function __construct()
    {
    }
}
