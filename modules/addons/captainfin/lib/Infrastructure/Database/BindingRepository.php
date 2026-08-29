<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Infrastructure\Database;

use DateTimeImmutable;
use WHMCS\Database\Capsule;

final class BindingRepository
{
    private const TABLE = 'mod_captainfin_service_bindings';

    public function findByServiceId(int $serviceId): ?object
    {
        if ($serviceId <= 0) {
            return null;
        }

        return Capsule::table(self::TABLE)
            ->where('service_id', $serviceId)
            ->first();
    }

    public function upsertJellyfinBinding(
        array $params,
        string $userId,
        string $username,
        string $state = 'active'
    ): object {
        $serviceId = (int) ($params['serviceid'] ?? 0);
        if ($serviceId <= 0) {
            throw new \InvalidArgumentException('WHMCS service id is required for a binding.');
        }

        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $identities = json_encode([
            'jellyfin' => [
                'user_id' => $userId,
                'username' => $username,
                'server_id' => (int) ($params['serverid'] ?? 0),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($identities === false) {
            throw new \RuntimeException('Unable to encode remote identity mapping.');
        }

        $existing = $this->findByServiceId($serviceId);
        if ($existing === null) {
            Capsule::table(self::TABLE)->insert([
                'service_id' => $serviceId,
                'client_id' => (int) ($params['userid'] ?? 0),
                'product_id' => (int) ($params['pid'] ?? 0),
                'server_id' => (int) ($params['serverid'] ?? 0) ?: null,
                'state' => $state,
                'jellyfin_user_id' => $userId,
                'remote_username' => $username,
                'remote_identities_json' => $identities,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            Capsule::table(self::TABLE)
                ->where('service_id', $serviceId)
                ->update([
                    'client_id' => (int) ($params['userid'] ?? $existing->client_id),
                    'product_id' => (int) ($params['pid'] ?? $existing->product_id),
                    'server_id' => (int) ($params['serverid'] ?? $existing->server_id) ?: null,
                    'state' => $state,
                    'jellyfin_user_id' => $userId,
                    'remote_username' => $username,
                    'remote_identities_json' => $identities,
                    'updated_at' => $now,
                ]);
        }

        $binding = $this->findByServiceId($serviceId);
        if ($binding === null) {
            throw new \RuntimeException('Unable to persist CAPTAiNFiN service binding.');
        }

        return $binding;
    }

    public function setState(int $serviceId, string $state): void
    {
        $updated = Capsule::table(self::TABLE)
            ->where('service_id', $serviceId)
            ->update([
                'state' => $state,
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

        if ($updated === 0 && $this->findByServiceId($serviceId) === null) {
            throw new \RuntimeException('CAPTAiNFiN service binding was not found.');
        }
    }
}
