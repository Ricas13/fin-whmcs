<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations;

final class DesiredStateDiff
{
    /**
     * @param array<string,mixed> $observed
     * @param array<string,mixed> $desired
     * @param string[]|null $fields
     * @return array<string,array{observed:mixed,desired:mixed}>
     */
    public static function between(array $observed, array $desired, ?array $fields = null): array
    {
        $keys = $fields ?? array_values(array_unique(array_merge(array_keys($observed), array_keys($desired))));
        $diff = [];
        foreach ($keys as $key) {
            $left = $observed[$key] ?? null;
            $right = $desired[$key] ?? null;
            if (self::canonical($left) !== self::canonical($right)) {
                $diff[$key] = ['observed' => $left, 'desired' => $right];
            }
        }
        return $diff;
    }

    private static function canonical(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            $items = array_map([self::class, 'canonical'], $value);
            usort($items, static fn ($a, $b): int => strcmp(json_encode($a) ?: '', json_encode($b) ?: ''));
            return $items;
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::canonical($item);
        }
        return $value;
    }

    private function __construct()
    {
    }
}
