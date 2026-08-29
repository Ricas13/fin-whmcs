<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\Jellyfin;

final class JellyfinClient
{
    private HttpClient $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    public function systemInfo(): array
    {
        $result = $this->http->request('/System/Info', 'GET', null, 5);

        if (!is_array($result)) {
            throw new JellyfinException('Jellyfin returned an invalid system information response.');
        }

        return $result;
    }

    public function listUsers(): array
    {
        $result = $this->http->request('/Users');

        if (!is_array($result)) {
            throw new JellyfinException('Jellyfin returned an invalid user list.');
        }

        return array_values(array_filter($result, 'is_array'));
    }

    public function findUserByName(string $username): ?array
    {
        $needle = $this->nameKey($username);
        $matches = array_values(array_filter(
            $this->listUsers(),
            fn (array $user): bool => $this->nameKey((string) ($user['Name'] ?? '')) === $needle
        ));

        if (count($matches) > 1) {
            throw new JellyfinException('Jellyfin returned multiple users with the same username.');
        }

        return $matches[0] ?? null;
    }

    public function getUser(string $userId): ?array
    {
        try {
            $result = $this->http->request('/Users/' . rawurlencode($userId));
        } catch (JellyfinException $error) {
            if ($error->isNotFound()) {
                return null;
            }
            throw $error;
        }

        if (!is_array($result)) {
            throw new JellyfinException('Jellyfin returned an invalid user response.');
        }

        return $result;
    }

    public function createUser(string $username, string $password): array
    {
        $result = $this->http->request('/Users/New', 'POST', [
            'Name' => $username,
            'Password' => $password,
        ]);

        if (!is_array($result) || trim((string) ($result['Id'] ?? '')) === '') {
            throw new JellyfinException('Jellyfin did not return a user ID after account creation.');
        }

        return $result;
    }

    public function renameUser(string $userId, string $username): void
    {
        $this->http->request('/Users/' . rawurlencode($userId), 'POST', [
            'Id' => $userId,
            'Name' => $username,
        ]);
    }

    public function setPolicy(string $userId, array $policy): void
    {
        $this->http->request('/Users/' . rawurlencode($userId) . '/Policy', 'POST', $policy);
    }

    public function setPassword(string $userId, string $password): void
    {
        $userId = trim($userId);
        if ($userId === '') {
            throw new \InvalidArgumentException('Jellyfin user ID is required for password changes.');
        }

        // Jellyfin 10.11 and 12 both expose this route. The historical
        // /Users/{userId}/Password form is retained only for backwards
        // compatibility in v12, so use the canonical endpoint now.
        $this->http->request('/Users/Password?userId=' . rawurlencode($userId), 'POST', [
            'NewPw' => $password,
            'ResetPassword' => false,
        ]);
    }

    public function deleteUser(string $userId): void
    {
        try {
            $this->http->request('/Users/' . rawurlencode($userId), 'DELETE');
        } catch (JellyfinException $error) {
            if ($error->isNotFound()) {
                return;
            }
            throw $error;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function listSessions(): array
    {
        $result = $this->http->request('/Sessions', 'GET', null, 7);
        if (!is_array($result)) {
            throw new JellyfinException('Jellyfin returned an invalid session list.');
        }
        return array_values(array_filter($result, 'is_array'));
    }

    public function stopSession(string $sessionId): void
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '' || !preg_match('/^[A-Za-z0-9._:-]{1,191}$/', $sessionId)) {
            throw new \InvalidArgumentException('Invalid Jellyfin session ID.');
        }
        $this->http->request('/Sessions/' . rawurlencode($sessionId) . '/Playing/Stop', 'POST');
    }

    public function listLibraries(): array
    {
        $result = $this->http->request('/Library/VirtualFolders', 'GET', null, 7);

        if (!is_array($result)) {
            throw new JellyfinException('Jellyfin did not return a valid library list.');
        }

        $libraries = [];
        foreach ($result as $folder) {
            if (!is_array($folder)) {
                continue;
            }
            $id = trim((string) ($folder['ItemId'] ?? ''));
            $name = trim((string) ($folder['Name'] ?? ''));
            if ($id !== '' && $name !== '') {
                $libraries[] = ['id' => $id, 'name' => $name];
            }
        }

        return $libraries;
    }

    public function resolveLibraryNames(array $requestedNames): array
    {
        $catalog = $this->listLibraries();
        $byName = [];
        foreach ($catalog as $library) {
            $byName[$this->nameKey($library['name'])] = $library['id'];
        }

        $enabled = [];
        $missing = [];
        foreach ($this->normaliseNames($requestedNames) as $name) {
            $key = $this->nameKey($name);
            if (isset($byName[$key])) {
                $enabled[] = $byName[$key];
            } else {
                $missing[] = $name;
            }
        }

        return [
            'enabledFolders' => array_values(array_unique($enabled)),
            'missing' => $missing,
            'catalog' => $catalog,
        ];
    }

    private function normaliseNames(array $names): array
    {
        $normalised = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $normalised[$this->nameKey($name)] = $name;
            }
        }

        return array_values($normalised);
    }

    private function nameKey(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }
}
