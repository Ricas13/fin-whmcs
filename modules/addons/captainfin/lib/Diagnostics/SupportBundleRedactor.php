<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Diagnostics;

final class SupportBundleRedactor
{
    private const SECRET_KEYS = [
        'password', 'passwd', 'secret', 'token', 'api_key', 'apikey', 'authorization',
        'serverpassword', 'discord_token', 'webhook_secret', 'access_token', 'refresh_token',
    ];

    public static function redact(mixed $value): mixed
    {
        if (is_array($value)) {
            $result = [];
            foreach ($value as $key => $item) {
                $name = strtolower((string) $key);
                $result[$key] = self::isSecretKey($name) ? '[REDACTED]' : self::redact($item);
            }
            return $result;
        }
        if (is_object($value)) {
            return (object) self::redact(get_object_vars($value));
        }
        if (is_string($value)) {
            return self::redactText($value);
        }
        return $value;
    }

    public static function redactText(string $text): string
    {
        $patterns = [
            '/(Authorization\s*:\s*)([^\r\n]+)/i' => '$1[REDACTED]',
            '/(Bearer\s+)[A-Za-z0-9._~+\/-]+/i' => '$1[REDACTED]',
            '/([?&](?:token|api[_-]?key|secret|password)=)[^&\s]+/i' => '$1[REDACTED]',
            '/(MediaBrowser\s+Token=")[^"]+(\")/i' => '$1[REDACTED]$2',
        ];
        return preg_replace(array_keys($patterns), array_values($patterns), $text) ?? $text;
    }

    private static function isSecretKey(string $key): bool
    {
        $normalised = str_replace(['-', ' '], '_', $key);
        foreach (self::SECRET_KEYS as $secret) {
            if ($normalised === $secret || str_ends_with($normalised, '_' . $secret)) {
                return true;
            }
        }
        return false;
    }

    private function __construct()
    {
    }
}
