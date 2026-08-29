<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Placement;

final class MigrationPlanner
{
    /** @param array<string,mixed> $source @param array<string,mixed> $target */
    public static function plan(array $source, array $target, string $username, bool $targetUsernameAvailable): array
    {
        $sourceId = (int) ($source['id'] ?? 0);
        $targetId = (int) ($target['id'] ?? 0);
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            throw new \InvalidArgumentException('Server migration requires distinct source and target servers.');
        }
        if (!PlacementSelector::eligible($target)) {
            throw new \RuntimeException('Target Jellyfin server is not eligible for new placement.');
        }
        $username = trim($username);
        if ($username === '') {
            throw new \InvalidArgumentException('Migration requires the durable Jellyfin username.');
        }
        if (!$targetUsernameAvailable) {
            throw new \RuntimeException('Target Jellyfin server already contains the required username.');
        }

        return [
            'source_server_id' => $sourceId,
            'target_server_id' => $targetId,
            'username' => $username,
            'steps' => [
                'observe_source',
                'create_target_with_bootstrap_credential',
                'apply_target_policy',
                'set_target_customer_credential',
                'persist_target_remote_identity',
                'switch_primary_binding',
                'disable_source',
                'verify_target_access_and_source_disabled',
            ],
            'rollback_boundary' => 'switch_primary_binding',
        ];
    }

    private function __construct()
    {
    }
}
