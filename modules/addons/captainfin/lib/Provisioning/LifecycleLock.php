<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Provisioning;

use WHMCS\Database\Capsule;

final class LifecycleLock
{
    public function run(int $serviceId, callable $callback, int $timeoutSeconds = 10): mixed
    {
        if ($serviceId <= 0) {
            throw new \InvalidArgumentException('WHMCS service id is required for lifecycle locking.');
        }

        $timeoutSeconds = max(0, min(30, $timeoutSeconds));
        $lockName = 'captainfin:svc:' . $serviceId;
        $connection = Capsule::connection();
        $acquired = false;

        try {
            $row = $connection->selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lockName, $timeoutSeconds]);
            $acquired = (int) ($row->acquired ?? 0) === 1;

            if (!$acquired) {
                throw new LifecycleBusyException(
                    sprintf('Another CAPTAiNFiN lifecycle operation is already running for service #%d.', $serviceId)
                );
            }

            return $callback();
        } finally {
            if ($acquired) {
                try {
                    $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
                } catch (\Throwable) {
                    // MySQL releases named locks when the connection closes.
                    // Never mask the lifecycle result with a lock-release error.
                }
            }
        }
    }
}
