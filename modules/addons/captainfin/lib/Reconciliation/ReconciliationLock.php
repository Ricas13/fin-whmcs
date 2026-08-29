<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Reconciliation;

use WHMCS\Database\Capsule;

final class ReconciliationLock
{
    private const LOCK_NAME = 'captainfin:reconciler';

    public function run(callable $callback): mixed
    {
        $connection = Capsule::connection();
        $acquired = false;

        try {
            $row = $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [self::LOCK_NAME]);
            $acquired = (int) ($row->acquired ?? 0) === 1;

            if (!$acquired) {
                return [
                    'skipped' => true,
                    'reason' => 'another_reconciler_running',
                ];
            }

            return $callback();
        } finally {
            if ($acquired) {
                try {
                    $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [self::LOCK_NAME]);
                } catch (\Throwable) {
                    // Named locks are connection-scoped and are also released
                    // when the database connection closes. Never mask a run.
                }
            }
        }
    }
}
