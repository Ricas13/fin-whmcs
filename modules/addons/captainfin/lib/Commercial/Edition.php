<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Commercial;

use CaptainFin\Whmcs\Integrations\MediaServer\MediaServerType;

final class Edition
{
    public const JELLYFIN = 'jellyfin';
    public const EMBY = 'emby';
    public const SUITE = 'suite';

    private string $id;
    private bool $development;
    private string $sku;

    private function __construct(string $id, bool $development, string $sku)
    {
        if (!in_array($id, [self::JELLYFIN, self::EMBY, self::SUITE], true)) {
            throw new \InvalidArgumentException('Unknown CAPTAiNFiN commercial edition: ' . $id);
        }

        $this->id = $id;
        $this->development = $development;
        $this->sku = $sku !== '' ? $sku : self::defaultSku($id);
    }

    public static function current(): self
    {
        $path = dirname(__DIR__, 2) . '/edition.json';
        if (!is_file($path)) {
            throw new \RuntimeException('CAPTAiNFiN edition manifest is missing. Reinstall the module package.');
        }

        $raw = file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            throw new \RuntimeException('CAPTAiNFiN edition manifest is invalid. Reinstall the module package.');
        }

        return self::fromId(
            mb_strtolower(trim((string) ($decoded['edition'] ?? '')), 'UTF-8'),
            (bool) ($decoded['development'] ?? false),
            trim((string) ($decoded['sku'] ?? ''))
        );
    }

    public static function fromId(string $id, bool $development = false, string $sku = ''): self
    {
        return new self(mb_strtolower(trim($id), 'UTF-8'), $development, $sku);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function sku(): string
    {
        return $this->sku;
    }

    public function isDevelopment(): bool
    {
        return $this->development;
    }

    public function allowsProvider(string $provider): bool
    {
        $provider = mb_strtolower(trim($provider), 'UTF-8');

        if ($this->id === self::SUITE) {
            return in_array($provider, [MediaServerType::JELLYFIN, MediaServerType::EMBY], true);
        }

        return $provider === $this->id;
    }

    public function displayName(): string
    {
        return match ($this->id) {
            self::JELLYFIN => 'CAPTAiNFiN for Jellyfin',
            self::EMBY => 'CAPTAiNFiN for Emby',
            default => 'CAPTAiNFiN Media Suite',
        };
    }

    public function shortLabel(): string
    {
        return match ($this->id) {
            self::JELLYFIN => 'Jellyfin',
            self::EMBY => 'Emby',
            default => 'Jellyfin + Emby',
        };
    }

    public static function defaultSku(string $id): string
    {
        return match ($id) {
            self::JELLYFIN => 'captainfin-jellyfin',
            self::EMBY => 'captainfin-emby',
            self::SUITE => 'captainfin-media-suite',
            default => throw new \InvalidArgumentException('Unknown CAPTAiNFiN commercial edition: ' . $id),
        };
    }
}
