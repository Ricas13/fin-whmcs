<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Provisioning;

use CaptainFin\Whmcs\Infrastructure\Database\BindingRepository;
use CaptainFin\Whmcs\Infrastructure\Database\OperationRepository;
use CaptainFin\Whmcs\Integrations\Jellyfin\HttpClient;
use CaptainFin\Whmcs\Integrations\Jellyfin\JellyfinClient;
use CaptainFin\Whmcs\Integrations\Jellyfin\JellyfinException;
use CaptainFin\Whmcs\Integrations\Jellyfin\PolicyBuilder;
use CaptainFin\Whmcs\Integrations\Jellyfin\ServerConfig;

final class JellyfinLifecycle
{
    private OperationRepository $operations;
    private BindingRepository $bindings;

    public function __construct(
        ?OperationRepository $operations = null,
        ?BindingRepository $bindings = null
    ) {
        $this->operations = $operations ?? new OperationRepository();
        $this->bindings = $bindings ?? new BindingRepository();
    }

    public function execute(string $operationType, array $params, object $operation): void
    {
        $config = ServerConfig::fromWhmcs($params);
        $client = new JellyfinClient(new HttpClient($config));

        match ($operationType) {
            'create' => $this->create($client, $params, $operation),
            'suspend' => $this->suspend($client, $params, $operation),
            'unsuspend' => $this->activate($client, $params, $operation, true),
            'change_package' => $this->activate($client, $params, $operation, false),
            'change_password' => $this->changePassword($client, $params, $operation),
            'terminate' => $this->terminate($client, $params, $operation),
            default => throw new \InvalidArgumentException('Unsupported CAPTAiNFiN lifecycle operation: ' . $operationType),
        };
    }

    private function create(JellyfinClient $client, array $params, object $operation): void
    {
        $serviceId = $this->serviceId($params);
        $binding = $this->bindings->findByServiceId($serviceId);
        $this->assertBindingServerMatches($binding, $params);

        $username = $this->desiredUsername($params, $binding);
        $password = $this->servicePassword($params);
        $policy = $this->activePolicy($client, $params);
        $user = null;

        if ($binding !== null && trim((string) ($binding->jellyfin_user_id ?? '')) !== '') {
            $user = $client->getUser((string) $binding->jellyfin_user_id);
        }

        if ($user === null && trim((string) ($operation->remote_ref ?? '')) !== '') {
            $candidate = $client->getUser((string) $operation->remote_ref);
            if ($candidate !== null) {
                $remoteName = trim((string) ($candidate['Name'] ?? ''));
                if ($this->nameKey($remoteName) !== $this->nameKey($username)) {
                    throw new ManualAttentionException(
                        'Previously recorded Jellyfin remote identity no longer matches the expected username.'
                    );
                }
                $user = $candidate;
            }
        }

        if ($user === null) {
            $existing = $client->findUserByName($username);
            if ($existing !== null) {
                throw new ManualAttentionException(
                    sprintf('Jellyfin username "%s" already exists but is not safely bound to this WHMCS service.', $username)
                );
            }

            $bootstrapPassword = $this->bootstrapPassword();
            try {
                $user = $client->createUser($username, $bootstrapPassword);
            } catch (JellyfinException $error) {
                if (!$error->isAmbiguous()) {
                    throw $error;
                }

                // We proved the username did not exist immediately before the
                // ambiguous create. If it exists now, this operation created it.
                try {
                    $observed = $client->findUserByName($username);
                } catch (\Throwable) {
                    throw $error;
                }

                if ($observed === null) {
                    throw $error;
                }
                $user = $observed;
            }
        }

        $userId = trim((string) ($user['Id'] ?? ''));
        if ($userId === '') {
            throw new JellyfinException('Jellyfin user is missing its remote ID.');
        }

        $this->operations->markRemoteApplied((int) $operation->id, $userId, [
            'stage' => 'user_exists',
            'jellyfin_user_id' => $userId,
            'username' => $username,
        ]);

        // Keep the customer credential unknown to the new remote account until
        // the restrictive plan/library policy is successfully applied.
        $client->setPolicy($userId, $policy);
        $client->setPassword($userId, $password);

        $this->operations->markRemoteApplied((int) $operation->id, $userId, [
            'stage' => 'ready',
            'jellyfin_user_id' => $userId,
            'username' => $username,
            'disabled' => false,
        ]);

        $this->bindings->upsertJellyfinBinding($params, $userId, $username, 'active');
        $this->operations->markLocalApplied((int) $operation->id);
    }

    private function suspend(JellyfinClient $client, array $params, object $operation): void
    {
        $binding = $this->requiredBinding($params);
        $this->assertBindingServerMatches($binding, $params);
        $userId = (string) $binding->jellyfin_user_id;
        $user = $client->getUser($userId);

        if ($user !== null) {
            $client->setPolicy($userId, PolicyBuilder::disabled());
        }

        $this->operations->markRemoteApplied((int) $operation->id, $userId, [
            'jellyfin_user_id' => $userId,
            'disabled' => true,
            'remote_missing' => $user === null,
        ]);
        $this->bindings->setState($this->serviceId($params), 'suspended');
        $this->operations->markLocalApplied((int) $operation->id);
    }

    private function activate(JellyfinClient $client, array $params, object $operation, bool $recreateMissing): void
    {
        $binding = $this->requiredBinding($params);
        $this->assertBindingServerMatches($binding, $params);
        $userId = (string) $binding->jellyfin_user_id;
        $user = $client->getUser($userId);

        if ($user === null) {
            if (!$recreateMissing) {
                throw new ManualAttentionException('The bound Jellyfin user is missing; package change cannot safely recreate it.');
            }
            $this->create($client, $params, $operation);
            return;
        }

        $policy = $this->activePolicy($client, $params);
        $client->setPolicy($userId, $policy);

        $this->operations->markRemoteApplied((int) $operation->id, $userId, [
            'jellyfin_user_id' => $userId,
            'username' => (string) ($user['Name'] ?? $binding->remote_username),
            'disabled' => false,
        ]);
        $this->bindings->setState($this->serviceId($params), 'active');
        $this->operations->markLocalApplied((int) $operation->id);
    }

    private function changePassword(JellyfinClient $client, array $params, object $operation): void
    {
        $binding = $this->requiredBinding($params);
        $this->assertBindingServerMatches($binding, $params);
        $userId = (string) $binding->jellyfin_user_id;

        if ($client->getUser($userId) === null) {
            throw new ManualAttentionException('The bound Jellyfin user is missing; password change was not applied.');
        }

        $client->setPassword($userId, $this->servicePassword($params));
        $this->operations->markRemoteApplied((int) $operation->id, $userId, [
            'jellyfin_user_id' => $userId,
            'password_updated' => true,
        ]);
        $this->operations->markLocalApplied((int) $operation->id);
    }

    private function terminate(JellyfinClient $client, array $params, object $operation): void
    {
        $serviceId = $this->serviceId($params);
        $binding = $this->bindings->findByServiceId($serviceId);
        $this->assertBindingServerMatches($binding, $params);

        $userId = $binding !== null ? trim((string) ($binding->jellyfin_user_id ?? '')) : '';
        if ($userId === '') {
            $userId = trim((string) ($operation->remote_ref ?? ''));
        }
        if ($userId === '') {
            $userId = (string) ($this->operations->latestKnownRemoteRefForService($serviceId) ?? '');
        }

        if ($userId !== '') {
            try {
                $client->deleteUser($userId);
            } catch (JellyfinException $error) {
                if (!$error->isAmbiguous()) {
                    throw $error;
                }

                try {
                    $remaining = $client->getUser($userId);
                } catch (\Throwable) {
                    throw $error;
                }

                if ($remaining !== null) {
                    throw $error;
                }
            }

            $this->operations->markRemoteApplied((int) $operation->id, $userId, [
                'jellyfin_user_id' => $userId,
                'deleted' => true,
            ]);
        } else {
            // No durable remote ID exists. We can prove the expected username is
            // absent, but must not delete a same-name account that may belong to
            // somebody else.
            $username = $this->desiredUsername($params, $binding);
            $sameName = $client->findUserByName($username);
            if ($sameName !== null) {
                throw new ManualAttentionException(
                    sprintf('An unbound Jellyfin user named "%s" exists; CAPTAiNFiN will not delete it without ownership proof.', $username)
                );
            }

            $this->operations->markRemoteApplied((int) $operation->id, null, [
                'deleted' => true,
                'known_remote_identity' => false,
                'expected_username_absent' => true,
            ]);
        }

        if ($binding !== null) {
            $this->bindings->setState($serviceId, 'terminated');
        }
        $this->operations->markLocalApplied((int) $operation->id);
    }

    private function activePolicy(JellyfinClient $client, array $params): array
    {
        $requested = $this->configuredLibraries($params);
        if ($requested === []) {
            return PolicyBuilder::active($params, true, []);
        }

        $resolved = $client->resolveLibraryNames($requested);
        if ($resolved['missing'] !== []) {
            throw new ManualAttentionException(
                'Configured Jellyfin libraries are missing on the assigned server: '
                . implode(', ', $resolved['missing'])
            );
        }

        return PolicyBuilder::active($params, false, $resolved['enabledFolders']);
    }

    private function configuredLibraries(array $params): array
    {
        $raw = (string) ($params['configoption2'] ?? '');
        if (trim($raw) === '') {
            return [];
        }

        $values = preg_split('/\s*,\s*/', $raw) ?: [];
        $result = [];
        foreach ($values as $value) {
            $value = trim($value);
            if ($value !== '') {
                $result[$this->nameKey($value)] = $value;
            }
        }

        return array_values($result);
    }

    private function requiredBinding(array $params): object
    {
        $binding = $this->bindings->findByServiceId($this->serviceId($params));
        if ($binding === null || trim((string) ($binding->jellyfin_user_id ?? '')) === '') {
            throw new ManualAttentionException('No durable Jellyfin binding exists for this WHMCS service.');
        }

        return $binding;
    }

    private function assertBindingServerMatches(?object $binding, array $params): void
    {
        if ($binding === null || $binding->server_id === null) {
            return;
        }

        $assignedServerId = (int) ($params['serverid'] ?? 0);
        if ($assignedServerId > 0 && (int) $binding->server_id !== $assignedServerId) {
            throw new ManualAttentionException(
                'This service is bound to a different Jellyfin server. Server migration must run through the migration workflow.'
            );
        }
    }

    private function desiredUsername(array $params, ?object $binding = null): string
    {
        $bound = trim((string) ($binding->remote_username ?? ''));
        if ($bound !== '') {
            return $bound;
        }

        $candidate = trim((string) ($params['username'] ?? ''));
        if ($candidate === '') {
            $email = trim((string) ($params['clientsdetails']['email'] ?? ''));
            $candidate = strstr($email, '@', true) ?: $email;
        }
        if ($candidate === '') {
            $candidate = 'user' . $this->serviceId($params);
        }

        $candidate = preg_replace('/[^A-Za-z0-9._@+\-]/', '_', $candidate) ?? '';
        $candidate = trim($candidate, '._-');
        $candidate = mb_substr($candidate, 0, 80);

        if (mb_strlen($candidate) < 3) {
            $candidate = 'user' . $this->serviceId($params);
        }

        if (!preg_match('/^[A-Za-z0-9._@+\-]{3,80}$/', $candidate)) {
            throw new \InvalidArgumentException('Unable to derive a valid Jellyfin username for this service.');
        }

        return $candidate;
    }

    private function servicePassword(array $params): string
    {
        $password = (string) ($params['password'] ?? '');
        $length = strlen($password);
        if ($length < 8 || $length > 200) {
            throw new \InvalidArgumentException('Jellyfin password must be between 8 and 200 characters.');
        }

        return $password;
    }

    private function bootstrapPassword(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function serviceId(array $params): int
    {
        $serviceId = (int) ($params['serviceid'] ?? 0);
        if ($serviceId <= 0) {
            throw new \InvalidArgumentException('WHMCS service id is required.');
        }

        return $serviceId;
    }

    private function nameKey(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }
}
