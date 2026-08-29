<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\Jellyseerr;

final class Compatibility
{
    public static function normaliseJellyfinUserId(string $value): string
    {
        return strtolower(str_replace('-', '', trim($value)));
    }

    public static function importBody(string $jellyfinUserId): array
    {
        $value = trim($jellyfinUserId);
        if (!preg_match('/^[A-Fa-f0-9-]{16,64}$/', $value)) {
            throw new \InvalidArgumentException('Invalid Jellyfin user ID for Jellyseerr/Seerr import.');
        }
        return ['jellyfinUserIds' => [$value]];
    }

    private function __construct()
    {
    }
}
