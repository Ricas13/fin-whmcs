<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Policy;

final class PolicySamplerContract
{
    public const MAX_PARALLEL_SERVER_POLLS = 4;
    public const DEFAULT_SAMPLE_INTERVAL_SECONDS = 30;
    public const TELEMETRY_SLACK_SECONDS = 20;

    /** @param array<string,mixed> $trust */
    public static function mayEnforce(array $trust): bool
    {
        return !empty($trust['ready']);
    }

    private function __construct()
    {
    }
}
