<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Commercial;

use CaptainFin\Whmcs\Commercial\Edition;
use CaptainFin\Whmcs\Commercial\EditionException;
use CaptainFin\Whmcs\Commercial\EditionGate;
use CaptainFin\Whmcs\Integrations\MediaServer\MediaServerType;
use PHPUnit\Framework\TestCase;

final class EditionTest extends TestCase
{
    public function testJellyfinEditionOnlyGrantsJellyfinCapability(): void
    {
        $edition = Edition::fromId(Edition::JELLYFIN);

        self::assertTrue($edition->allowsProvider(MediaServerType::JELLYFIN));
        self::assertFalse($edition->allowsProvider(MediaServerType::EMBY));
        self::assertSame('captainfin-jellyfin', $edition->sku());
        self::assertSame('CAPTAiNFiN for Jellyfin', $edition->displayName());
    }

    public function testEmbyEditionOnlyGrantsEmbyCapability(): void
    {
        $edition = Edition::fromId(Edition::EMBY);

        self::assertFalse($edition->allowsProvider(MediaServerType::JELLYFIN));
        self::assertTrue($edition->allowsProvider(MediaServerType::EMBY));
        self::assertSame('captainfin-emby', $edition->sku());
        self::assertSame('CAPTAiNFiN for Emby', $edition->displayName());
    }

    public function testSuiteGrantsBothCapabilities(): void
    {
        $edition = Edition::fromId(Edition::SUITE);

        self::assertTrue($edition->allowsProvider(MediaServerType::JELLYFIN));
        self::assertTrue($edition->allowsProvider(MediaServerType::EMBY));
        self::assertSame('captainfin-media-suite', $edition->sku());
        self::assertSame('CAPTAiNFiN Media Suite', $edition->displayName());
    }

    public function testUnlicensedProviderCannotGrantOrModifyAccess(): void
    {
        $gate = new EditionGate(Edition::fromId(Edition::JELLYFIN));

        foreach (['create', 'unsuspend', 'change_package', 'change_password'] as $operation) {
            try {
                $gate->assertLifecycleAllowed($operation, MediaServerType::EMBY);
                self::fail($operation . ' unexpectedly bypassed the commercial edition gate.');
            } catch (EditionException) {
                self::assertTrue(true);
            }
        }
    }

    public function testSafetyCleanupRemainsAvailableAfterEditionDowngrade(): void
    {
        $gate = new EditionGate(Edition::fromId(Edition::JELLYFIN));

        $gate->assertLifecycleAllowed('suspend', MediaServerType::EMBY);
        $gate->assertLifecycleAllowed('terminate', MediaServerType::EMBY);

        self::assertTrue(true);
    }
}
