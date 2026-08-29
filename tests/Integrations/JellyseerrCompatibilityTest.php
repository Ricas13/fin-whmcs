<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Integrations;

use CaptainFin\Whmcs\Integrations\Jellyseerr\Compatibility;
use PHPUnit\Framework\TestCase;

final class JellyseerrCompatibilityTest extends TestCase
{
    public function testImportUsesCurrentJellyfinUserIdsField(): void
    {
        self::assertSame(
            ['jellyfinUserIds' => ['6213b704-a0d9-5429-3110-f4d561b0f614']],
            Compatibility::importBody('6213b704-a0d9-5429-3110-f4d561b0f614')
        );
    }

    public function testDashedAndUndashedGuidsCompareCanonically(): void
    {
        self::assertSame(
            Compatibility::normaliseJellyfinUserId('6213b704-a0d9-5429-3110-f4d561b0f614'),
            Compatibility::normaliseJellyfinUserId('6213b704a0d954293110f4d561b0f614')
        );
    }
}
