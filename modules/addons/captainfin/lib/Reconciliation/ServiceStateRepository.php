<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Reconciliation;

use WHMCS\Database\Capsule;

final class ServiceStateRepository
{
    public function find(int $serviceId): ?object
    {
        if ($serviceId <= 0) {
            return null;
        }

        return Capsule::table('tblhosting as h')
            ->join('tblproducts as p', 'p.id', '=', 'h.packageid')
            ->where('h.id', $serviceId)
            ->select([
                'h.id',
                'h.userid',
                'h.packageid',
                'h.server',
                'h.domainstatus',
                'p.servertype',
            ])
            ->first();
    }

    public function isEnded(object $service): bool
    {
        return in_array(
            strtolower(trim((string) ($service->domainstatus ?? ''))),
            ['cancelled', 'terminated'],
            true
        );
    }

    public function isSuspended(object $service): bool
    {
        return strtolower(trim((string) ($service->domainstatus ?? ''))) === 'suspended';
    }

    public function usesCaptainFin(object $service): bool
    {
        return strtolower(trim((string) ($service->servertype ?? ''))) === 'captainfin';
    }
}
