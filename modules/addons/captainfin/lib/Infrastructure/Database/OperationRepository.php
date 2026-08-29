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

    public function findById(int $id): ?object
    {
        if ($id <= 0) {
            return null;
        }

        return Capsule::table(self::TABLE)->where('id', $id)->first();
    }

    public function latestForService(int $serviceId): ?object
    {
        if ($serviceId <= 0) {
            return null;
        }

        return Capsule::table(self::TABLE)
            ->where('service_id', $serviceId)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Return operations where automatic replay can materially advance state.
     *
     * planned/remote_applied entries are only considered after a grace period
     * so a normal in-flight web request is not mistaken for abandoned work.
     * failed entries are eligible only when the original failure explicitly
     * supplied retry_after; non-retryable failures remain for admin review.
     *
     * @return object[]
     */
    public function dueForReconciliation(
        int $limit = 20,
        ?DateTimeImmutable $now = null,
        int $staleSeconds = 60
    ): array {
        $limit = max(1, min(100, $limit));
        $staleSeconds = max(30, min(3600, $staleSeconds));
        $now ??= new DateTimeImmutable();
        $nowSql = $now->format('Y-m-d H:i:s');
        $staleBefore = $now->modify('-' . $staleSeconds . ' seconds')->format('Y-m-d H:i:s');

        return Capsule::table(self::TABLE)
            ->where(static function ($query) use ($staleBefore, $nowSql): void {
                $query->where(static function ($stale) use ($staleBefore): void {
                    $stale->whereIn('state', [OperationState::PLANNED, OperationState::REMOTE_APPLIED])
                        ->where('updated_at', '<=', $staleBefore);
                })->orWhere(static function ($failed) use ($nowSql): void {
                    $failed->where('state', OperationState::FAILED)
                        ->whereNotNull('retry_after')
                        ->where('retry_after', '<=', $nowSql);
                });
            })
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->all();
    }

    public function latestKnownRemoteRefForService(int $serviceId): ?string
    {
        $row = Capsule::table(self::TABLE)
            ->where('service_id', $serviceId)
            ->whereNotNull('remote_ref')
            ->where('remote_ref', '<>', '')
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        $remoteRef = trim((string) $row->remote_ref);
        return $remoteRef !== '' ? $remoteRef : null;
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

    public function markSuperseded(int $id, string $reason): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        Capsule::table(self::TABLE)
            ->where('id', $id)
            ->update([
                'state' => OperationState::SUPERSEDED,
                'last_error' => $this->truncate($reason),
                'retry_after' => null,
                'completed_at' => $now,
                'updated_at' => $now,
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
