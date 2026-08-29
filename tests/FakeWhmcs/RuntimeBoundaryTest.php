<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\FakeWhmcs;

use PHPUnit\Framework\TestCase;

final class RuntimeBoundaryTest extends TestCase
{
    public function testHarnessDoesNotClaimWhmcsRenderingOrPermissions(): void
    {
        self::assertContains('WHMCS admin rendering', RuntimeBoundary::NOT_EMULATED);
        self::assertContains('WHMCS permissions', RuntimeBoundary::NOT_EMULATED);
        self::assertNotContains('WHMCS admin rendering', RuntimeBoundary::EMULATED);
    }
}
