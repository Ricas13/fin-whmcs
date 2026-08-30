<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\Emby;

use CaptainFin\Whmcs\Integrations\MediaServer\MediaServerClient;

final class EmbyClient implements MediaServerClient
{
    private HttpClient $http;

    public function __construct(HttpClient $http)
    {
        $this->http = $http;
    }

    public function providerName(): string
    {
        return 'emby';
    }

    public function systemInfo(): array
    {
        $result = $this->http->request('/System/Info', 'GET', null, 5);
        if (!is_array($result)) {
            throw new EmbyException('Emby returned an invalid system information response.');
        }
        return $result;
    }

    public function listUsers(): array
    {
        $result = $this->http->request('/Users/Query?Limit=10000', 'GET', null, 7);
        if (!is_array($result)) {
            throw new EmbyException('Emby returned an invalid user list.');
        }
        $items = isset($result['Items']) && is_array($result['Items']) ? $result['Items'] : $result;
        return array_values(array_filter($items, 'is_array'));
    }

    public function findUserByName(string $username): ?array
    {
        $needle = $this->nameKey($username);
        $matches = array_values(array_filter(
            $this->listUsers(),
            fn (array $user): bool => $this->nameKey((string) ($user['Name'] ?? '')) === $needle
        ));
        if (count($matches) > 1) {
            throw new EmbyException('Emby returned multiple users with the same username.');
        }
        return $matches[0] ?? null;
    }

    public function getUser(string $userId): ?array
    {
        try {
            $result = $this->http->request('/Users/' . rawurlencode($userId));
        } catch (EmbyException $error) {
            if ($error->isNotFound()) {
                return null;
            }
            throw $error;
        }
        if (!is_array($result)) {
            throw new EmbyException('Emby returned an invalid user response.');
        }
        return $result;
    }

    public function createUser(string $username, string $password): array
    {
        $result = $this->http->request('/Users/New', 'POST', ['Name' => $username]);
        if (!is_array($result) || trim((string) ($result['Id'] ?? '')) === '') {
            throw new EmbyException('Emby did not return a user ID after account creation.');
        }

        $userId = trim((string) $result['Id']);
        try {
            $this->setPassword($userId, $password);
        } catch (EmbyException $passwordError) {
            // Emby's create-user request does not accept a password. Never leave
            // a passwordless bootstrap account behind if the second mutation
            // fails: compensate by deleting the known ID. If rollback itself is
            // uncertain, surface an ambiguous error so reconciliation does not
            // assume either outcome.
            try {
                $this->deleteUser($userId);
            } catch (\Throwable $rollbackError) {
                throw new EmbyException(
                    'Emby user creation succeeded but bootstrap password failed and rollback could not be proven.',
                    $passwordError->statusCode(),
                    true,
                    true,
                    $passwordError,
                );
            }
            throw $passwordError;
        }

        return $result;
    }

    public function renameUser(string $userId, string $username): void
    {
        $userId = trim($userId);
        $username = trim($username);
        if ($userId === '' || $username === '') {
            throw new \InvalidArgumentException('Emby user ID and username are required for rename.');
        }

        $current = $this->getUser($userId);
        if ($current === null) {
            throw new EmbyException('Emby user disappeared before rename.');
        }
        if (!isset($current['Configuration']) || !is_array($current['Configuration'])) {
            throw new EmbyException('Emby user response is missing configuration required for a safe rename.');
        }

        $current['Id'] = $userId;
        $current['Name'] = $username;
        $this->http->request('/Users/' . rawurlencode($userId), 'POST', $current);
    }

    public function setPolicy(string $userId, array $policy): void
    {
        $this->http->request('/Users/' . rawurlencode($userId) . '/Policy', 'POST', $policy);
    }

    public function setPassword(string $userId, string $password): void
    {
        $userId = trim($userId);
        if ($userId === '') {
            throw new \InvalidArgumentException('Emby user ID is required for password changes.');
        }
        $this->http->request('/Users/' . rawurlencode($userId) . '/Password', 'POST', [
            'Id' => $userId,
            'NewPw' => $password,
            'ResetPassword' => false,
        ]);
    }

    public function deleteUser(string $userId): void
    {
        try {
            $this->http->request('/Users/' . rawurlencode($userId), 'DELETE');
        } catch (EmbyException $error) {
            if ($error->isNotFound()) {
                return;
            }
            throw $error;
        }
    }

    public function listSessions(): array
    {
        $result = $this->http->request('/Sessions', 'GET', null, 7);
        if (!is_array($result)) {
            throw new EmbyException('Emby returned an invalid session list.');
        }
        return array_values(array_filter($result, 'is_array'));
    }

    public function stopSession(string $sessionId): void
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '' || !preg_match('/^[A-Za-z0-9._:-]{1,191}$/', $sessionId)) {
            throw new \InvalidArgumentException('Invalid Emby session ID.');
        }
        $this->http->request(
            '/Sessions/' . rawurlencode($sessionId) . '/Playing/Stop',
            'POST',
            ['Command' => 'Stop']
        );
    }

    public function listLibraries(): array
    {
        $result = $this->http->request('/Library/VirtualFolders/Query?Limit=10000', 'GET', null, 7);
        if (!is_array($result)) {
            throw new EmbyException('Emby did not return a valid library list.');
        }
        $items = isset($result['Items']) && is_array($result['Items']) ? $result['Items'] : $result;

        $libraries = [];
        foreach ($items as $folder) {
            if (!is_array($folder)) {
                continue;
            }
            $id = trim((string) ($folder['ItemId'] ?? $folder['Id'] ?? ''));
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
