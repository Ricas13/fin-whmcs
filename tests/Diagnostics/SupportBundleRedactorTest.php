<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Diagnostics;

use CaptainFin\Whmcs\Diagnostics\SupportBundleRedactor;
use PHPUnit\Framework\TestCase;

final class SupportBundleRedactorTest extends TestCase
{
    public function testRecursivelyRedactsKnownSecretFields(): void
    {
        $redacted = SupportBundleRedactor::redact([
            'server' => ['hostname' => 'media.example.com', 'api_key' => 'jf-secret'],
            'discord_token' => 'discord-secret',
            'nested' => ['password' => 'customer-secret'],
        ]);

        self::assertSame('media.example.com', $redacted['server']['hostname']);
        self::assertSame('[REDACTED]', $redacted['server']['api_key']);
        self::assertSame('[REDACTED]', $redacted['discord_token']);
        self::assertSame('[REDACTED]', $redacted['nested']['password']);
    }

    public function testRedactsSecretsEmbeddedInDiagnosticText(): void
    {
        $text = "Authorization: Bearer abc.def\nGET /?api_key=secret&x=1\nAuthorization: MediaBrowser Token=\"jf-key\"";
        $redacted = SupportBundleRedactor::redactText($text);

        self::assertStringNotContainsString('abc.def', $redacted);
        self::assertStringNotContainsString('secret', $redacted);
        self::assertStringNotContainsString('jf-key', $redacted);
    }
}
