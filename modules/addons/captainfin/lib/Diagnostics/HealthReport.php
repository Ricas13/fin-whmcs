<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Diagnostics;

final class HealthReport
{
    /** @param array<int,array<string,mixed>> $checks */
    public static function fromChecks(array $checks): array
    {
        $normalised = [];
        $overall = 'healthy';
        foreach ($checks as $check) {
            $name = trim((string) ($check['name'] ?? 'unknown'));
            $status = strtolower(trim((string) ($check['status'] ?? 'unknown')));
            if (!in_array($status, ['healthy', 'degraded', 'failed', 'unknown'], true)) {
                $status = 'unknown';
            }
            if ($status === 'failed') {
                $overall = 'failed';
            } elseif ($overall !== 'failed' && in_array($status, ['degraded', 'unknown'], true)) {
                $overall = 'degraded';
            }
            $normalised[] = [
                'name' => $name,
                'status' => $status,
                'detail' => SupportBundleRedactor::redactText(trim((string) ($check['detail'] ?? ''))),
                'latency_ms' => isset($check['latency_ms']) ? max(0, (int) $check['latency_ms']) : null,
            ];
        }

        return ['overall' => $overall, 'checks' => $normalised];
    }

    private function __construct()
    {
    }
}
