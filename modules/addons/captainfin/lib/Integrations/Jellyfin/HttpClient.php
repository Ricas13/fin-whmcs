<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\Jellyfin;

final class HttpClient
{
    private ServerConfig $config;

    public function __construct(ServerConfig $config)
    {
        $this->config = $config;
    }

    public function request(
        string $endpoint,
        string $method = 'GET',
        ?array $body = null,
        int $timeoutSeconds = 10
    ): mixed {
        if (!function_exists('curl_init')) {
            throw new JellyfinException('CAPTAiNFiN requires the PHP cURL extension.');
        }
        if (!str_starts_with($endpoint, '/') || str_starts_with($endpoint, '//')) {
            throw new \InvalidArgumentException('Invalid Jellyfin API endpoint.');
        }

        $method = strtoupper(trim($method));
        if ($method === '') {
            throw new \InvalidArgumentException('HTTP method is required.');
        }

        $timeoutSeconds = max(1, min(60, $timeoutSeconds));
        $url = rtrim($this->config->baseUrl(), '/') . $endpoint;
        $headers = [
            'Authorization: ' . AuthorizationHeader::build($this->config->apiKey()),
            'Accept: application/json',
        ];

        $encodedBody = null;
        if ($body !== null) {
            $encodedBody = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($encodedBody === false) {
                throw new \RuntimeException('Unable to encode Jellyfin request body.');
            }
            $headers[] = 'Content-Type: application/json';
        }

        $curl = curl_init();
        if ($curl === false) {
            throw new JellyfinException('Unable to initialise cURL.');
        }

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOSIGNAL => true,
            CURLOPT_USERAGENT => 'CAPTAiNFiN-WHMCS/' . AuthorizationHeader::CLIENT_VERSION,
        ];

        if (defined('CURLOPT_PROTOCOLS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTP | CURLPROTO_HTTPS;
        }
        if ($encodedBody !== null) {
            $options[CURLOPT_POSTFIELDS] = $encodedBody;
        }

        curl_setopt_array($curl, $options);

        $responseBody = curl_exec($curl);
        $curlErrorNumber = curl_errno($curl);
        $curlError = curl_error($curl);
        $statusCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if ($responseBody === false) {
            $mutation = !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);
            $timedOut = $curlErrorNumber === CURLE_OPERATION_TIMEDOUT;
            $message = $timedOut
                ? sprintf('Jellyfin %s %s timed out after %ds.', $method, $endpoint, $timeoutSeconds)
                : sprintf('Jellyfin %s %s request failed: %s', $method, $endpoint, $curlError ?: 'network error');

            throw new JellyfinException(
                $message,
                null,
                true,
                $mutation,
            );
        }

        $decoded = $this->decodeResponse((string) $responseBody);

        if ($statusCode < 200 || $statusCode >= 300) {
            $retryable = $statusCode === 408 || $statusCode === 429 || $statusCode >= 500;
            $mutation = !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true);
            $ambiguous = $mutation && $statusCode >= 500;

            throw new JellyfinException(
                sprintf('Jellyfin %s %s returned HTTP %d.', $method, $endpoint, $statusCode),
                $statusCode,
                $retryable,
                $ambiguous,
            );
        }

        return $decoded;
    }

    private function decodeResponse(string $body): mixed
    {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $body;
    }
}
