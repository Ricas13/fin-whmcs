<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\FakeWhmcs;

final class LocalApi
{
    /** @var callable|null */
    private static $handler = null;

    public static function install(callable $handler): void
    {
        self::$handler = $handler;
    }

    public static function reset(): void
    {
        self::$handler = null;
    }

    public static function call(string $command, array $parameters): array
    {
        if (!is_callable(self::$handler)) {
            throw new \RuntimeException('Fake WHMCS local API handler is not installed.');
        }

        $result = (self::$handler)($command, $parameters);
        if (!is_array($result)) {
            throw new \RuntimeException('Fake WHMCS local API handler must return an array.');
        }

        return $result;
    }
}
