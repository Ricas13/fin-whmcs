<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Runtime;

use CaptainFin\Whmcs\Integrations\Jellyfin\HttpClient;
use CaptainFin\Whmcs\Integrations\Jellyfin\JellyfinClient;
use CaptainFin\Whmcs\Integrations\Jellyfin\ServerConfig;
use CaptainFin\Whmcs\Tests\FakeWhmcs\FixtureFactory;
use CaptainFin\Whmcs\Tests\FakeWhmcs\RuntimeSkip;
use PHPUnit\Framework\TestCase;

final class JellyfinRuntimeTest extends TestCase
{
    public function testRealJellyfinConnectionAndLibraryDiscovery(): void
    {
        if (!RuntimeSkip::jellyfinConfigured()) {
            self::markTestSkipped('Set CAPTAINFIN_TEST_JELLYFIN_API_KEY to run real Jellyfin integration tests.');
        }

        $params = FixtureFactory::service();
        $client = new JellyfinClient(new HttpClient(ServerConfig::fromWhmcs($params)));

        $info = $client->systemInfo();
        self::assertNotSame('', trim((string) ($info['Version'] ?? '')));
        self::assertIsArray($client->listLibraries());
        self::assertIsArray($client->listUsers());
    }
}
