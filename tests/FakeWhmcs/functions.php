<?php

declare(strict_types=1);

use CaptainFin\Whmcs\Tests\FakeWhmcs\ActivityLog;
use CaptainFin\Whmcs\Tests\FakeWhmcs\LocalApi;

if (!function_exists('localAPI')) {
    function localAPI(string $command, array $parameters = [], ?string $adminUsername = null): array
    {
        return LocalApi::call($command, $parameters);
    }
}

if (!function_exists('logActivity')) {
    function logActivity(string $message, int $userId = 0): void
    {
        ActivityLog::write($message);
    }
}
