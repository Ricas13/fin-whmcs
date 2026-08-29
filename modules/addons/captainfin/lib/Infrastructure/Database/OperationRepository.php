<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Infrastructure\Database;

use CaptainFin\Whmcs\Domain\OperationState;
use DateTimeImmutable;
use WHMCS\Database\Capsule;

final class OperationRepository
{
    private const TABLE = 'mod_captainfin_operations';

    public function findOrCreate(
        string $operationKey,
        int $serviceId,
        string $operationType,
        string $targetHash,
        array $expectedRemote = []
    ): object {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        Capsule::table(self::TABLE)->insertOrIgnore([
            'operation_key' => $operationKey,
            'service_id' => $serviceId,
            'operation_type' => $operationType,
            'target_hash' => $targetHash,
            'state' => OperationState::PLANNED,
            'attempts' => 0,
            'expected_remote_json' => $expectedRemote === [] ? null : $this->encode($expectedRemote),
            'started_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $operation = Capsule::table(self::TABLE)
            ->where('operation_key', $operationKey)
            ->first();

        if ($operation === null) {
            throw new \RuntimeException('Unable to create or load CAPTAiNFiN operation.');
        }

        return $operation;
    }

    public function markFailed(int $id, string $error, ?DateTimeImmutable $retryAfter = null): void
    {
        Capsule::table(self::TABLE)
            ->where('id', $id)
            ->increment('attempts', 1, [
                'state' => OperationState::FAILED,
                'last_error' => $this->truncate($error),
                'retry_after' => $retryAfter?->format('Y-m-d H:i:s'),
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
    }

    public function markRemoteApplied(int $id, ?string $remoteRef = null, array $observedRemote = []): void
    {
        Capsule::table(self::TABLE)
            ->where('id', $id)
            ->increment('attempts', 1, [
                'state' => OperationState::REMOTE_APPLIED,
                'remote_ref' => $remoteRef,
                'observed_remote_json' => $observedRemote === [] ? null : $this->encode($observedRemote),
                'last_error' => null,
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
    }

    public function markLocalApplied(int $id): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        Capsule::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'state' => OperationState::LOCAL_APPLIED,
                'last_error' => null,
                'retry_after' => null,
                'completed_at' => $now,
                'updated_at' => $now,
            ]);
    }

    public function markManualAttention(int $id, string $error): void
    {
        Capsule::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'state' => OperationState::MANUAL_ATTENTION,
                'last_error' => $this->truncate($error),
                'retry_after' => null,
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
    }

    private function encode(array $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            throw new \RuntimeException('Unable to encode operation state.');
        }

        return $json;
    }

    private function truncate(string $value, int $max = 8000): string
    {
        return mb_substr($value, 0, $max);
    }
}
