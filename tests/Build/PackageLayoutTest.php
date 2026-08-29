<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Build;

use PHPUnit\Framework\TestCase;

final class PackageLayoutTest extends TestCase
{
    public function testRequiredWhmcsModuleEntrypointsExist(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertFileExists($root . '/modules/addons/captainfin/captainfin.php');
        self::assertFileExists($root . '/modules/addons/captainfin/lib/autoload.php');
        self::assertFileExists($root . '/modules/servers/captainfin/captainfin.php');
    }
}
