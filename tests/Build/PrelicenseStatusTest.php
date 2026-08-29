<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Build;

use PHPUnit\Framework\TestCase;

final class PrelicenseStatusTest extends TestCase
{
    public function testStatusDoesNotClaimIncompleteRuntimeWorkIsDone(): void
    {
        $status = file_get_contents(dirname(__DIR__, 2) . '/docs/CURRENT_PRELICENSE_STATUS.md');
        self::assertIsString($status);
        self::assertStringContainsString('Still requiring concrete external/runtime work', $status);
        self::assertStringContainsString('unattended Jellyfin test bootstrap', $status);
    }
}
