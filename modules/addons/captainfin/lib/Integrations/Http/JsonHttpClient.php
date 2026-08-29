<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\Http;

final class JsonHttpClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly array $defaultHeaders = [],
        private readonly int $connectTimeoutSeconds = 3,
        private readonly int $timeoutSeconds = 10
    ) {
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('CAPTAiNFiN requires the PHP cURL extension.');
        }
        $parts = parse_url($baseUrl);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Integration base URL must be a valid http/https URL.');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('Integration base URL may not contain credentials, query strings or fragments.');
        }
    }

    public function request(string $path, string $method = 'GET', ?array $body = null, array $headers = []): mixed
    {
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
            throw new \InvalidArgumentException('Integration API path must be origin-relative.');
        }

        $url = rtrim($this->baseUrl, '/') . $path;
        $base = parse_url($this->baseUrl);
        $target = parse_url($url);
        if (!is_array($base) || !is_array($target) || strtolower((string) ($base['host'] ?? '')) !== strtolower((string) ($target['host'] ?? ''))) {
            throw new \InvalidArgumentException('Integration API path escaped configured origin.');
        }

        $method = strtoupper($method);
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('Unable to initialise cURL.');
        }

        $headerLines = [];
        foreach (array_merge($this->defaultHeaders, $headers) as $name => $value) {
            if (preg_match('/[\r\n]/', (string) $name . (string) $value)) {
                throw new \InvalidArgumentException('Invalid integration HTTP header.');
            }
            $headerLines[] = (string) $name . ': ' . (string) $value;
        }
        if ($body !== null) {
            $headerLines[] = 'Content-Type: application/json';
            $encoded = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                throw new \RuntimeException('Unable to encode integration request body.');
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_CONNECTTIMEOUT => max(1, $this->connectTimeoutSeconds),
            CURLOPT_TIMEOUT => max(1, $this->timeoutSeconds),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADER => false,
        ]);

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            $ambiguous = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
            throw new HttpException(
                sprintf('Integration %s %s failed: %s', $method, $path, $error !== '' ? $error : 'transport error'),
                null,
                true,
                $ambiguous
            );
        }

        $decoded = null;
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $decoded = $raw;
            }
        }

        if ($status < 200 || $status >= 300) {
            $retryable = $status === 408 || $status === 429 || $status >= 500;
            $ambiguous = $retryable && in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
            throw new HttpException(
                sprintf('Integration %s %s returned HTTP %d', $method, $path, $status),
                $status,
                $retryable,
                $ambiguous
            );
        }

        return $decoded ?? [];
    }
}
