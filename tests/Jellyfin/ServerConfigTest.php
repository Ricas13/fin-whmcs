<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Jellyfin;

use CaptainFin\Whmcs\Integrations\Jellyfin\ServerConfig;
use PHPUnit\Framework\TestCase;

final class ServerConfigTest extends TestCase
{
    public function testBuildsSecureWhmcsHostAndPort(): void
    {
        $config = ServerConfig::fromWhmcs([
            'serverid' => 9,
            'serverhostname' => 'jellyfin.example.com',
            'serversecure' => true,
            'serverport' => 8920,
            'serverpassword' => 'secret-api-key',
        ]);

        self::assertSame(9, $config->serverId());
        self::assertSame('https://jellyfin.example.com:8920', $config->baseUrl());
        self::assertSame('secret-api-key', $config->apiKey());
    }

    public function testAcceptsReverseProxyBasePath(): void
    {
        $config = ServerConfig::fromWhmcs([
            'serverid' => 2,
            'serverhostname' => 'https://media.example.com/jellyfin/',
            'serverpassword' => 'api-key',
        ]);

        self::assertSame('https://media.example.com/jellyfin', $config->baseUrl());
    }

    public function testPreservesBracketedIpv6Url(): void
    {
        $config = ServerConfig::fromWhmcs([
            'serverid' => 3,
            'serverhostname' => 'http://[2001:db8::10]:8096/jellyfin/',
            'serverpassword' => 'api-key',
        ]);

        self::assertSame('http://[2001:db8::10]:8096/jellyfin', $config->baseUrl());
    }

    /** @dataProvider invalidUrlProvider */
    public function testRejectsUnsafeOrInvalidUrls(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ServerConfig::fromWhmcs([
            'serverhostname' => $url,
            'serverpassword' => 'api-key',
        ]);
    }

    public static function invalidUrlProvider(): array
    {
        return [
            'credentials' => ['https://admin:secret@example.com'],
            'query string' => ['https://example.com/?token=secret'],
            'fragment' => ['https://example.com/#admin'],
            'unsupported scheme' => ['ftp://example.com'],
        ];
    }

    public function testRequiresApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('API key');

        ServerConfig::fromWhmcs([
            'serverhostname' => 'jellyfin.example.com',
            'serverpassword' => '',
        ]);
    }
}
