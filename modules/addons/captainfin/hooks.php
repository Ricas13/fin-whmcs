<?php

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use CaptainFin\Whmcs\Reconciliation\Reconciler;

require_once __DIR__ . '/lib/autoload.php';

add_hook('AfterCronJob', 1, static function (): void {
    try {
        $summary = (new Reconciler())->run(20);

        if (!empty($summary['skipped'])) {
            return;
        }

        // Successful routine passes are intentionally silent. Surface only
        // outcomes that an operator may need to understand without leaking any
        // integration credentials or customer secrets into WHMCS activity logs.
        $manual = (int) ($summary['manual_attention'] ?? 0);
        $recovered = (int) ($summary['recovered'] ?? 0);
        $superseded = (int) ($summary['superseded'] ?? 0);

        if (($manual > 0 || $recovered > 0 || $superseded > 0) && function_exists('logActivity')) {
            logActivity(sprintf(
                'CAPTAiNFiN reconciliation: %d recovered, %d superseded, %d require manual attention.',
                $recovered,
                $superseded,
                $manual
            ));
        }
    } catch (Throwable $error) {
        if (function_exists('logActivity')) {
            logActivity('CAPTAiNFiN reconciliation failed: ' . $error->getMessage());
        }
    }
});
