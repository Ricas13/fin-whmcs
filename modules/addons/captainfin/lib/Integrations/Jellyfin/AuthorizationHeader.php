<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\Jellyfin;

final class AuthorizationHeader
{
    public const CLIENT_NAME = 'CAPTAiNFiN WHMCS';
    public const DEVICE_NAME = 'WHMCS';
    public const DEVICE_ID = 'captainfin-whmcs';
    public const CLIENT_VERSION = '0.2.0';

    private function __construct()
    {
    }

    public static function build(
        string $token = '',
        string $clientName = self::CLIENT_NAME,
        string $deviceName = self::DEVICE_NAME,
        string $deviceId = self::DEVICE_ID,
        string $clientVersion = self::CLIENT_VERSION
    ): string {
        return implode(', ', [
            'MediaBrowser Client="' . self::encodeComponent($clientName) . '"',
            'Device="' . self::encodeComponent($deviceName) . '"',
            'DeviceId="' . self::encodeComponent($deviceId) . '"',
            'Version="' . self::encodeComponent($clientVersion) . '"',
            'Token="' . self::encodeComponent($token) . '"',
        ]);
    }

    /**
     * Match JavaScript encodeURIComponent, which is what Jellyfin's official
     * TypeScript SDK uses when constructing the MediaBrowser authorization
     * header. Keeping all values encoded also prevents header injection.
     */
    private static function encodeComponent(string $value): string
    {
        return strtr(rawurlencode($value), [
            '%21' => '!',
            '%27' => "'",
            '%28' => '(',
            '%29' => ')',
            '%2A' => '*',
        ]);
    }
}
