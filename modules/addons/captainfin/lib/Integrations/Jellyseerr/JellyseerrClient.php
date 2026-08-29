<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\Jellyseerr;

use CaptainFin\Whmcs\Integrations\DesiredStateDiff;
use CaptainFin\Whmcs\Integrations\Http\HttpException;
use CaptainFin\Whmcs\Integrations\Http\JsonHttpClient;
use CaptainFin\Whmcs\Integrations\IntegrationResult;

final class JellyseerrClient
{
    private JsonHttpClient $http;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '' || preg_match('/[\r\n]/', $apiKey)) {
            throw new \InvalidArgumentException('Jellyseerr/Seerr API key is required.');
        }
        $this->http = new JsonHttpClient(rtrim($baseUrl, '/') . '/api/v1', [
            'X-Api-Key' => $apiKey,
            'Accept' => 'application/json',
        ]);
    }

    public function status(): array
    {
        $status = $this->http->request('/status');
        if (!is_array($status)) {
            throw new \RuntimeException('Jellyseerr/Seerr returned an invalid status response.');
        }
        return $status;
    }

    public function capabilities(): array
    {
        $status = $this->status();
        $management = true;
        $reason = null;
        try {
            $this->http->request('/user?take=1&skip=0');
        } catch (HttpException $error) {
            if (in_array($error->status(), [401, 403], true)) {
                $management = false;
                $reason = 'Configured API key can reach Seerr but this version/auth configuration does not permit user-management endpoints with API-key authentication.';
            } else {
                throw $error;
            }
        }

        return [
            'status' => $status,
            'user_management' => $management,
            'user_management_reason' => $reason,
        ];
    }

    public function importJellyfinUser(string $jellyfinUserId): array
    {
        $jellyfinUserId = self::jellyfinId($jellyfinUserId);
        $result = $this->http->request('/user/import-from-jellyfin', 'POST', [
            'jellyfinUserIds' => [$jellyfinUserId],
        ]);

        if (!is_array($result)) {
            throw new \RuntimeException('Jellyseerr/Seerr returned an invalid import response.');
        }

        $user = self::extractImportedUser($result, $jellyfinUserId);
        if ($user === null) {
            $user = $this->findByJellyfinUserId($jellyfinUserId);
        }
        if ($user === null) {
            throw new \RuntimeException('Jellyseerr/Seerr user import returned without an observable imported user.');
        }
        return $user;
    }

    public function findByJellyfinUserId(string $jellyfinUserId): ?array
    {
        $needle = self::normaliseGuid(self::jellyfinId($jellyfinUserId));
        foreach ($this->listUsers() as $user) {
            $remote = self::normaliseGuid((string) ($user['jellyfinUserId'] ?? $user['jellyfin_user_id'] ?? ''));
            if ($remote !== '' && $remote === $needle) {
                return $user;
            }
        }
        return null;
    }

    /** @return array<int,array<string,mixed>> */
    public function listUsers(): array
    {
        $users = [];
        $take = 100;
        $skip = 0;
        for ($page = 0; $page < 100; $page++) {
            $response = $this->http->request('/user?take=' . $take . '&skip=' . $skip);
            if (!is_array($response)) {
                throw new \RuntimeException('Jellyseerr/Seerr returned an invalid user list.');
            }
            $results = is_array($response['results'] ?? null) ? $response['results'] : (array_is_list($response) ? $response : []);
            foreach ($results as $user) {
                if (is_array($user)) {
                    $users[] = $user;
                }
            }
            $total = (int) ($response['pageInfo']['results'] ?? count($users));
            $skip += count($results);
            if ($results === [] || $skip >= $total) {
                break;
            }
        }
        return $users;
    }

    public function updatePermissions(int $userId, int $permissions): array
    {
        if ($userId <= 0 || $permissions < 0) {
            throw new \InvalidArgumentException('Invalid Jellyseerr/Seerr user or permission mask.');
        }
        $observed = $this->getUser($userId);
        $diff = DesiredStateDiff::between($observed, ['permissions' => $permissions], ['permissions']);
        if ($diff === []) {
            return IntegrationResult::unchanged($observed);
        }

        $this->http->request('/user/' . $userId, 'PUT', ['permissions' => $permissions]);
        $after = $this->getUser($userId);
        if ((int) ($after['permissions'] ?? -1) !== $permissions) {
            throw new \RuntimeException('Jellyseerr/Seerr permission update returned without observed convergence.');
        }
        return IntegrationResult::changed($after);
    }

    public function deleteUser(int $userId): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Invalid Jellyseerr/Seerr user ID.');
        }
        try {
            $this->getUser($userId);
        } catch (HttpException $error) {
            if ($error->status() === 404) {
                return IntegrationResult::unchanged(['exists' => false]);
            }
            throw $error;
        }

        $this->http->request('/user/' . $userId, 'DELETE');
        try {
            $this->getUser($userId);
        } catch (HttpException $error) {
            if ($error->status() === 404) {
                return IntegrationResult::changed(['exists' => false]);
            }
            throw $error;
        }
        throw new \RuntimeException('Jellyseerr/Seerr user deletion returned without observed absence.');
    }

    public function getUser(int $userId): array
    {
        $user = $this->http->request('/user/' . $userId);
        if (!is_array($user)) {
            throw new \RuntimeException('Jellyseerr/Seerr returned an invalid user response.');
        }
        return $user;
    }

    private static function extractImportedUser(array $response, string $jellyfinUserId): ?array
    {
        $candidates = array_is_list($response) ? $response : ($response['results'] ?? $response['users'] ?? []);
        if (!is_array($candidates)) {
            return null;
        }
        $needle = self::normaliseGuid($jellyfinUserId);
        foreach ($candidates as $candidate) {
            if (!is_array($candidate)) {
                continue;
            }
            $remote = self::normaliseGuid((string) ($candidate['jellyfinUserId'] ?? $candidate['jellyfin_user_id'] ?? ''));
            if ($remote === $needle || count($candidates) === 1) {
                return $candidate;
            }
        }
        return null;
    }

    private static function jellyfinId(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^[A-Fa-f0-9-]{16,64}$/', $value)) {
            throw new \InvalidArgumentException('Invalid Jellyfin user ID for Jellyseerr/Seerr import.');
        }
        return $value;
    }

    private static function normaliseGuid(string $value): string
    {
        return strtolower(str_replace('-', '', trim($value)));
    }
}
