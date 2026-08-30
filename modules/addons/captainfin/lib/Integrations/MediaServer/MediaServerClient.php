<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\MediaServer;

interface MediaServerClient
{
    public function providerName(): string;

    public function systemInfo(): array;

    /** @return array<int,array<string,mixed>> */
    public function listUsers(): array;

    public function findUserByName(string $username): ?array;

    public function getUser(string $userId): ?array;

    public function createUser(string $username, string $password): array;

    public function renameUser(string $userId, string $username): void;

    public function setPolicy(string $userId, array $policy): void;

    public function setPassword(string $userId, string $password): void;

    public function deleteUser(string $userId): void;

    /** @return array<int,array<string,mixed>> */
    public function listSessions(): array;

    public function stopSession(string $sessionId): void;

    /** @return array<int,array{id:string,name:string}> */
    public function listLibraries(): array;

    public function resolveLibraryNames(array $requestedNames): array;
}
