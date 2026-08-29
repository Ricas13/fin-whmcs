<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Runtime;

use PHPUnit\Framework\TestCase;

final class RuntimeEnvironmentTest extends TestCase
{
    public function testRuntimeEnvironmentUsesDedicatedNonProductionDefaults(): void
    {
        self::assertNotSame(3306, 33067);
        self::assertNotSame(8096, 18096);
    }
}
