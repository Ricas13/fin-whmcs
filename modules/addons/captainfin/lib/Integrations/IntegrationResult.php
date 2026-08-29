<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations;

final class IntegrationResult
{
    public static function unchanged(array $observed = []): array
    {
        return ['changed' => false, 'observed' => $observed, 'mutations' => 0];
    }

    public static function changed(array $observed = [], int $mutations = 1): array
    {
        return ['changed' => true, 'observed' => $observed, 'mutations' => max(1, $mutations)];
    }

    private function __construct()
    {
    }
}
