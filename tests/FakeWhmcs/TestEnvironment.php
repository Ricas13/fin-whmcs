<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\FakeWhmcs;

final class TestEnvironment
{
    public static function reset(): void
    {
        LocalApi::reset();
        ActivityLog::reset();
    }
}
