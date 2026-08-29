<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Runtime;

use PHPUnit\Framework\TestCase;

final class RuntimeEvidenceTest extends TestCase
{
    public function testMissingEvidenceIsNotSilentlyTreatedAsPassing(): void
    {
        $missing = RuntimeEvidence::missing(['create_account_real_jellyfin']);

        self::assertNotContains('create_account_real_jellyfin', $missing);
        self::assertContains('terminate_real_jellyfin', $missing);
        self::assertContains('remote_success_local_failure_recovery', $missing);
    }
}
