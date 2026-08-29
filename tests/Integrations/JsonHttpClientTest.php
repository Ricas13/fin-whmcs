<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Integrations;

use CaptainFin\Whmcs\Integrations\Http\JsonHttpClient;
use PHPUnit\Framework\TestCase;

final class JsonHttpClientTest extends TestCase
{
    /** @dataProvider invalidBaseUrlProvider */
    public function testRejectsUnsafeBaseUrls(string $url): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JsonHttpClient($url);
    }

    public static function invalidBaseUrlProvider(): array
    {
        return [
            ['ftp://example.com'],
            ['https://user:pass@example.com'],
            ['https://example.com/?token=secret'],
            ['https://example.com/#fragment'],
        ];
    }

    public function testRejectsOriginEscapingPath(): void
    {
        $client = new JsonHttpClient('https://example.com/api');
        $this->expectException(\InvalidArgumentException::class);
        $client->request('//evil.example/path');
    }
}
