<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Runtime;

use CaptainFin\Whmcs\Tests\FakeWhmcs\RuntimeSkip;
use PHPUnit\Framework\TestCase;

final class JellyfinLifecycleRuntimeTest extends TestCase
{
    public function testLifecycleSuiteRequiresBootstrappedJellyfinAndDatabase(): void
    {
        if (!RuntimeSkip::jellyfinConfigured()) {
            self::markTestSkipped('Real lifecycle suite requires deterministic Jellyfin bootstrap/API key.');
        }

        // Full create/suspend/unsuspend/change-package/terminate assertions are
        // mounted once the DB harness wires WHMCS Capsule to the disposable SQL
        // schema. Keeping this test opt-in prevents a source-only pass from being
        // mistaken for runtime lifecycle proof.
        self::assertTrue(true);
    }
}
