<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\FakeWhmcs;

use PHPUnit\Framework\TestCase;

final class WhmcsOwnedBoundaryTest extends TestCase
{
    public function testHarnessLoadsRealModuleEntrypoints(): void
    {
        ModuleRuntime::load();

        self::assertTrue(function_exists('captainfin_CreateAccount'));
        self::assertTrue(function_exists('captainfin_SuspendAccount'));
        self::assertTrue(function_exists('captainfin_UnsuspendAccount'));
        self::assertTrue(function_exists('captainfin_TerminateAccount'));
        self::assertTrue(function_exists('captainfin_ChangePackage'));
        self::assertTrue(function_exists('captainfin_ChangePassword'));
    }
}
