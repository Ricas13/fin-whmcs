<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Build;

use PHPUnit\Framework\TestCase;

final class UpgradeInvariantTest extends TestCase
{
    public function testArchitectureRequiresOperationalStateRetention(): void
    {
        $architecture = file_get_contents(dirname(__DIR__, 2) . '/docs/ARCHITECTURE.md');
        self::assertIsString($architecture);
        self::assertStringContainsString('Deactivation does not drop operational tables', $architecture);
    }
}
