<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Policy;

final class ProductPolicy
{
    /** @return array<string,mixed> */
    public static function fromWhmcsParams(array $params): array
    {
        return [
            'plan_class' => self::text($params['configoption1'] ?? 'premium', 'premium'),
            'libraries' => self::csv($params['configoption2'] ?? ''),
            'user_selectable_libraries' => self::bool($params['configoption3'] ?? false),
            'max_streams' => self::nonNegativeInt($params['configoption4'] ?? 0, 50),
            'max_transcodes' => self::nonNegativeInt($params['configoption5'] ?? 0, 50),
            'four_k_transcode_policy' => self::enum($params['configoption6'] ?? 'allow', ['allow', 'block'], 'allow'),
            'network_policy' => self::enum($params['configoption7'] ?? 'allow', ['allow', 'household', 'strict_single_ip'], 'allow'),
            'max_ips' => self::nonNegativeInt($params['configoption8'] ?? 0, 50),
            'jellyseerr_access' => self::bool($params['configoption9'] ?? true),
            'stremio_access' => self::bool($params['configoption10'] ?? false),
            'discord_role_id' => self::nullableText($params['configoption11'] ?? null),
            'inactivity_days' => self::nonNegativeInt($params['configoption12'] ?? 0, 3650),
            'allow_downloads' => self::bool($params['configoption13'] ?? false),
            'allow_video_transcoding' => self::bool($params['configoption14'] ?? false),
            'allow_audio_transcoding' => self::bool($params['configoption15'] ?? false),
            'allow_remuxing' => self::bool($params['configoption16'] ?? false),
            'allow_live_tv' => self::bool($params['configoption17'] ?? false),
            'allow_live_tv_management' => self::bool($params['configoption18'] ?? false),
            'allow_remote_access' => self::bool($params['configoption19'] ?? false),
            'allow_subtitle_editing' => self::bool($params['configoption20'] ?? true),
        ];
    }

    private static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    private static function nonNegativeInt(mixed $value, int $max): int
    {
        return min($max, max(0, (int) $value));
    }

    private static function enum(mixed $value, array $allowed, string $default): string
    {
        $value = strtolower(trim((string) $value));
        return in_array($value, $allowed, true) ? $value : $default;
    }

    private static function csv(mixed $value): array
    {
        $parts = is_array($value) ? $value : (preg_split('/\s*,\s*/', trim((string) $value)) ?: []);
        $result = [];
        foreach ($parts as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $result[mb_strtolower($item, 'UTF-8')] = $item;
            }
        }
        return array_values($result);
    }

    private static function text(mixed $value, string $default): string
    {
        $value = trim((string) $value);
        return $value !== '' ? mb_substr($value, 0, 64) : $default;
    }

    private static function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value !== '' ? mb_substr($value, 0, 191) : null;
    }

    private function __construct()
    {
    }
}
