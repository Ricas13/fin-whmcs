<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\FakeWhmcs;

/**
 * Marker for DB-backed integration tests.
 *
 * Runtime tests boot the module against a real MySQL/MariaDB-compatible schema
 * instead of trying to reproduce WHMCS Capsule query semantics in memory.
 */
final class CapsuleHarness
{
    private function __construct()
    {
    }
}
