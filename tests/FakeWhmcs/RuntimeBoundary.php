<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\FakeWhmcs;

final class RuntimeBoundary
{
    public const EMULATED = [
        'localAPI command dispatch',
        'activity log capture',
        'service fixtures',
    ];

    public const NOT_EMULATED = [
        'WHMCS admin rendering',
        'WHMCS client area rendering',
        'WHMCS permissions',
        'WHMCS addon activation',
        'WHMCS internal Capsule implementation',
    ];

    private function __construct()
    {
    }
}
