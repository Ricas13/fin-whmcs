<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Infrastructure\Database;

use Illuminate\Database\Schema\Blueprint;
use WHMCS\Database\Capsule;

final class Schema
{
    public const VERSION = 1;

    private const TABLE_BINDINGS = 'mod_captainfin_service_bindings';
    private const TABLE_OPERATIONS = 'mod_captainfin_operations';
    private const TABLE_POLICIES = 'mod_captainfin_product_policies';

    private function __construct()
    {
    }

    public static function install(): void
    {
        $schema = Capsule::schema();

        if (!$schema->hasTable(self::TABLE_POLICIES)) {
            $schema->create(self::TABLE_POLICIES, static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('product_id')->unique();
                $table->unsignedInteger('version')->default(1);
                $table->text('policy_json');
                $table->timestamps();
            });
        }

        if (!$schema->hasTable(self::TABLE_BINDINGS)) {
            $schema->create(self::TABLE_BINDINGS, static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('service_id')->unique();
                $table->unsignedInteger('client_id');
                $table->unsignedInteger('product_id');
                $table->unsignedInteger('server_id')->nullable();
                $table->string('state', 32)->default('pending');
                $table->string('jellyfin_user_id', 191)->nullable();
                $table->string('remote_username', 191)->nullable();
                $table->text('remote_identities_json')->nullable();
                $table->timestamps();

                $table->index(['client_id', 'product_id']);
                $table->index(['server_id', 'state']);
            });
        }

        if (!$schema->hasTable(self::TABLE_OPERATIONS)) {
            $schema->create(self::TABLE_OPERATIONS, static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('operation_key', 191)->unique();
                $table->unsignedInteger('service_id')->index();
                $table->string('operation_type', 64)->index();
                $table->string('target_hash', 64);
                $table->string('state', 32)->index();
                $table->unsignedInteger('attempts')->default(0);
                $table->string('remote_ref', 191)->nullable();
                $table->text('expected_remote_json')->nullable();
                $table->text('observed_remote_json')->nullable();
                $table->text('last_error')->nullable();
                $table->dateTime('retry_after')->nullable()->index();
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->timestamps();

                $table->index(['service_id', 'operation_type', 'state'], 'captainfin_ops_service_type_state');
            });
        }
    }

    /**
     * Deactivation deliberately preserves operational state.
     */
    public static function deactivate(): void
    {
    }

    public static function health(): array
    {
        $schema = Capsule::schema();

        return [
            self::TABLE_POLICIES => $schema->hasTable(self::TABLE_POLICIES),
            self::TABLE_BINDINGS => $schema->hasTable(self::TABLE_BINDINGS),
            self::TABLE_OPERATIONS => $schema->hasTable(self::TABLE_OPERATIONS),
        ];
    }
}
