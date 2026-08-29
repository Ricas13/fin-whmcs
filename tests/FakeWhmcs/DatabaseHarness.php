<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\FakeWhmcs;

use CaptainFin\Whmcs\Infrastructure\Database\Schema;
use Illuminate\Database\Capsule\Manager;

final class DatabaseHarness
{
    private static ?Manager $capsule = null;

    public static function boot(): void
    {
        if (self::$capsule !== null) {
            return;
        }

        $capsule = new Manager();
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => getenv('CAPTAINFIN_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('CAPTAINFIN_TEST_DB_PORT') ?: 33067),
            'database' => getenv('CAPTAINFIN_TEST_DB_NAME') ?: 'captainfin_test',
            'username' => getenv('CAPTAINFIN_TEST_DB_USER') ?: 'captainfin',
            'password' => getenv('CAPTAINFIN_TEST_DB_PASSWORD') ?: 'captainfin',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        self::$capsule = $capsule;

        Schema::install();
    }

    public static function resetModuleState(): void
    {
        self::boot();
        foreach ([
            'mod_captainfin_policy_events',
            'mod_captainfin_activity_observations',
            'mod_captainfin_health_checks',
            'mod_captainfin_audit_events',
            'mod_captainfin_server_telemetry',
            'mod_captainfin_integration_bindings',
            'mod_captainfin_operations',
            'mod_captainfin_service_bindings',
            'mod_captainfin_servers',
            'mod_captainfin_product_policies',
        ] as $table) {
            Manager::table($table)->delete();
        }
    }

    public static function table(string $table): \Illuminate\Database\Query\Builder
    {
        self::boot();
        return Manager::table($table);
    }

    private function __construct()
    {
    }
}
