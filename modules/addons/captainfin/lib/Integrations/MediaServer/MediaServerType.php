<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\MediaServer;

final class MediaServerType
{
    public const JELLYFIN = 'jellyfin';
    public const EMBY = 'emby';

    private function __construct()
    {
    }

    public static function fromWhmcs(array $params): string
    {
        // WHMCS provisioning modules do not expose arbitrary per-server fields.
        // CAPTAiNFiN therefore uses the standard Server Username field as a
        // non-secret provider selector. Empty remains Jellyfin for backwards
        // compatibility with existing CAPTAiNFiN server definitions.
        $value = mb_strtolower(trim((string) ($params['serverusername'] ?? '')), 'UTF-8');
        if ($value === '' || $value === self::JELLYFIN) {
            return self::JELLYFIN;
        }
        if ($value === self::EMBY) {
            return self::EMBY;
        }

        throw new \InvalidArgumentException('CAPTAiNFiN server Username must be "jellyfin" or "emby".');
    }
}
