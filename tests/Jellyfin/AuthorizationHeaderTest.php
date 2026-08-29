<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Jellyfin;

use CaptainFin\Whmcs\Integrations\Jellyfin\AuthorizationHeader;
use PHPUnit\Framework\TestCase;

final class AuthorizationHeaderTest extends TestCase
{
    public function testBuildsCanonicalModernMediaBrowserHeader(): void
    {
        self::assertSame(
            'MediaBrowser Client="CAPTAiNFiN%20WHMCS", Device="WHMCS", DeviceId="captainfin-whmcs", Version="0.2.0", Token="abc123"',
            AuthorizationHeader::build('abc123')
        );
    }

    public function testIncludesEmptyTokenForPreAuthenticationRequests(): void
    {
        self::assertSame(
            'MediaBrowser Client="Tests", Device="CI", DeviceId="device-1", Version="12.0", Token=""',
            AuthorizationHeader::build('', 'Tests', 'CI', 'device-1', '12.0')
        );
    }

    public function testEncodesHeaderComponentsLikeOfficialJellyfinSdk(): void
    {
        self::assertSame(
            'MediaBrowser Client="Client%20%26%20Co!", Device="CI%2FRunner", DeviceId="id%3A1", Version="1.0%20beta", Token="token%22%0D%0AInjected%3A%20x"',
            AuthorizationHeader::build("token\"\r\nInjected: x", 'Client & Co!', 'CI/Runner', 'id:1', '1.0 beta')
        );
    }
}
