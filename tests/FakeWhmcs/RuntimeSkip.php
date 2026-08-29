<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\FakeWhmcs;

final class RuntimeSkip
{
    public static function jellyfinConfigured(): bool
    {
        return trim((string) getenv('CAPTAINFIN_TEST_JELLYFIN_API_KEY')) !== '';
    }
}
