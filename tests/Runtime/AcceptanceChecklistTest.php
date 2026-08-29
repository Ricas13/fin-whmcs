<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Runtime;

use PHPUnit\Framework\TestCase;

final class AcceptanceChecklistTest extends TestCase
{
    public function testCriticalLifecycleAndRecoveryScenariosRemainRequired(): void
    {
        self::assertContains('create_account_real_jellyfin', AcceptanceChecklist::REQUIRED);
        self::assertContains('ambiguous_create_recovery', AcceptanceChecklist::REQUIRED);
        self::assertContains('remote_success_local_failure_recovery', AcceptanceChecklist::REQUIRED);
        self::assertContains('concurrent_lifecycle_serialization', AcceptanceChecklist::REQUIRED);
        self::assertContains('drift_reconciliation', AcceptanceChecklist::REQUIRED);
    }
}
