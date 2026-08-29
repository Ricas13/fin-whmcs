<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Runtime;

final class AcceptanceChecklist
{
    public const REQUIRED = [
        'create_account_real_jellyfin',
        'suspend_real_jellyfin',
        'unsuspend_real_jellyfin',
        'terminate_real_jellyfin',
        'change_package_real_jellyfin',
        'ambiguous_create_recovery',
        'remote_success_local_failure_recovery',
        'concurrent_lifecycle_serialization',
        'library_policy_narrows_only',
        'drift_reconciliation',
    ];

    private function __construct()
    {
    }
}
