<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\Jellyfin;

final class ServerConfig
{
    private int $serverId;
    private string $baseUrl;
    private string $apiKey;

    private function __construct(int $serverId, string $baseUrl, string $apiKey)
    {
        $this->serverId = $serverId;
        $this->baseUrl = $baseUrl;
        $this->apiKey = $apiKey;
    }

    public static function fromWhmcs(array $params): self
    {
        $hostname = trim((string) ($params['serverhostname'] ?? ''));
        $apiKey = trim((string) ($params['serverpassword'] ?? ''));
        $serverId = (int) ($params['serverid'] ?? 0);

        if ($hostname === '') {
            throw new \InvalidArgumentException('Jellyfin server hostname is required.');
        }
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Enter the Jellyfin API key in the WHMCS server Password field.');
        }
        if (preg_match('/[\r\n]/', $apiKey)) {
            throw new \InvalidArgumentException('Jellyfin API key contains invalid characters.');
        }

        if (preg_match('#^https?://#i', $hostname)) {
            $baseUrl = $hostname;
        } else {
            if (str_contains($hostname, '/') || str_contains($hostname, '@')) {
                throw new \InvalidArgumentException('Jellyfin server hostname must be a hostname or a full http/https URL.');
            }

            $secure = filter_var($params['serversecure'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $scheme = $secure ? 'https' : 'http';
            $port = (int) ($params['serverport'] ?? 0);
            $baseUrl = sprintf('%s://%s', $scheme, $hostname);

            if ($port > 0) {
                $baseUrl .= ':' . $port;
            }
        }

        return new self($serverId, self::normalizeBaseUrl($baseUrl), $apiKey);
    }

    public static function normalizeBaseUrl(string $value): string
    {
        $value = trim($value);
        $parts = parse_url($value);

        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('Enter a valid Jellyfin http/https URL.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Only http and https Jellyfin URLs are allowed.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || isset($parts['query'])) {
            throw new \InvalidArgumentException('Jellyfin URLs may not contain credentials, query strings or fragments.');
        }

        $host = (string) $parts['host'];
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

        if ($host === '') {
            throw new \InvalidArgumentException('Jellyfin URL hostname is required.');
        }

        $formattedHost = $host;
        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            $formattedHost = '[' . $host . ']';
        }

        return sprintf('%s://%s%s%s', $scheme, $formattedHost, $port, $path);
    }

    public function serverId(): int
    {
        return $this->serverId;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function apiKey(): string
    {
        return $this->apiKey;
    }

    public function publicSummary(): array
    {
        return [
            'server_id' => $this->serverId,
            'base_url' => $this->baseUrl,
        ];
    }
}
