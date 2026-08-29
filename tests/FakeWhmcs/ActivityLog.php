<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\FakeWhmcs;

final class ActivityLog
{
    /** @var string[] */
    private static array $messages = [];

    public static function write(string $message): void
    {
        self::$messages[] = $message;
    }

    /** @return string[] */
    public static function messages(): array
    {
        return self::$messages;
    }

    public static function reset(): void
    {
        self::$messages = [];
    }
}
