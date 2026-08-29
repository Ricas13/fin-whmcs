<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\FakeWhmcs;

final class ModuleRuntime
{
    public static function load(): void
    {
        require_once __DIR__ . '/bootstrap.php';
        require_once __DIR__ . '/functions.php';
        require_once dirname(__DIR__, 2) . '/modules/addons/captainfin/lib/autoload.php';
        require_once dirname(__DIR__, 2) . '/modules/servers/captainfin/captainfin.php';
    }
}
