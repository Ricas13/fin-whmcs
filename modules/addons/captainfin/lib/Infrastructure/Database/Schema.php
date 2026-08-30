<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Infrastructure\Database;

use Illuminate\Database\Schema\Blueprint;
use WHMCS\Database\Capsule;

final class Schema
{
    public const VERSION = 3;

    private const TABLE_BINDINGS = 'mod_captainfin_service_bindings';
    private const TABLE_OPERATIONS = 'mod_captainfin_operations';
    private const TABLE_POLICIES = 'mod_captainfin_product_policies';
    private const TABLE_SERVERS = 'mod_captainfin_servers';
    private const TABLE_INTEGRATION_BINDINGS = 'mod_captainfin_integration_bindings';
    private const TABLE_ACTIVITY = 'mod_captainfin_activity_observations';
    private const TABLE_ACTIVE_SESSIONS = 'mod_captainfin_active_sessions';
    private const TABLE_TELEMETRY = 'mod_captainfin_server_telemetry';
    private const TABLE_POLICY_EVENTS = 'mod_captainfin_policy_events';
    private const TABLE_HEALTH = 'mod_captainfin_health_checks';
    private const TABLE_AUDIT = 'mod_captainfin_audit_events';

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
                $table->string('media_server_type', 32)->default('jellyfin')->index();
                $table->string('media_user_id', 191)->nullable()->index();
                // Retained through the pre-v1 migration window so installations
                // created by early development builds can upgrade in place.
                $table->string('jellyfin_user_id', 191)->nullable();
                $table->string('remote_username', 191)->nullable();
                $table->text('remote_identities_json')->nullable();
                $table->timestamps();

                $table->index(['client_id', 'product_id']);
                $table->index(['server_id', 'state']);
                $table->index(['media_server_type', 'server_id', 'state'], 'captainfin_binding_media_server');
            });
        } else {
            if (!$schema->hasColumn(self::TABLE_BINDINGS, 'media_server_type')) {
                $schema->table(self::TABLE_BINDINGS, static function (Blueprint $table): void {
                    $table->string('media_server_type', 32)->default('jellyfin')->index();
                });
            }
            if (!$schema->hasColumn(self::TABLE_BINDINGS, 'media_user_id')) {
                $schema->table(self::TABLE_BINDINGS, static function (Blueprint $table): void {
                    $table->string('media_user_id', 191)->nullable()->index();
                });
            }
        }

        // Backfill early Jellyfin-only development rows into provider-neutral
        // ownership fields. The legacy column remains readable until v1 cleanup.
        if ($schema->hasColumn(self::TABLE_BINDINGS, 'jellyfin_user_id')) {
            Capsule::statement(
                'UPDATE ' . self::TABLE_BINDINGS
                . ' SET media_user_id = jellyfin_user_id, media_server_type = ?'
                . ' WHERE media_user_id IS NULL AND jellyfin_user_id IS NOT NULL',
                ['jellyfin']
            );
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

        if (!$schema->hasTable(self::TABLE_SERVERS)) {
            $schema->create(self::TABLE_SERVERS, static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('whmcs_server_id')->unique();
                $table->string('server_class', 64)->default('premium')->index();
                $table->string('media_server_type', 32)->default('jellyfin')->index();
                $table->boolean('enabled')->default(true)->index();
                $table->boolean('allow_new_users')->default(true);
                $table->string('placement_mode', 32)->default('active');
                $table->unsignedInteger('priority')->default(100);
                $table->unsignedInteger('placement_weight')->default(100);
                $table->unsignedInteger('max_users')->default(0);
                $table->string('health_status', 32)->default('unknown')->index();
                $table->text('settings_json')->nullable();
                $table->timestamps();
                $table->index(['server_class', 'enabled', 'placement_mode'], 'captainfin_servers_placement');
            });
        } elseif (!$schema->hasColumn(self::TABLE_SERVERS, 'media_server_type')) {
            $schema->table(self::TABLE_SERVERS, static function (Blueprint $table): void {
                $table->string('media_server_type', 32)->default('jellyfin')->index();
            });
        }

        if (!$schema->hasTable(self::TABLE_INTEGRATION_BINDINGS)) {
            $schema->create(self::TABLE_INTEGRATION_BINDINGS, static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('service_id')->index();
                $table->string('integration', 32);
                $table->string('remote_id', 191)->nullable();
                $table->string('state', 32)->default('pending')->index();
                $table->text('identity_json')->nullable();
                $table->dateTime('last_observed_at')->nullable();
                $table->timestamps();
                $table->unique(['service_id', 'integration'], 'captainfin_binding_service_integration');
            });
        }

        if (!$schema->hasTable(self::TABLE_ACTIVITY)) {
            $schema->create(self::TABLE_ACTIVITY, static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('service_id')->nullable()->index();
                $table->unsignedInteger('server_id')->index();
                $table->string('media_server_type', 32)->default('jellyfin')->index();
                $table->string('media_user_id', 191)->index();
                $table->string('jellyfin_user_id', 191)->nullable()->index();
                $table->string('session_id', 191)->index();
                $table->string('event_type', 32)->index();
                $table->string('source', 32)->index();
                $table->string('network_key', 191)->nullable();
                $table->string('item_id', 191)->nullable();
                $table->dateTime('observed_at')->index();
                $table->text('observation_json')->nullable();
                $table->timestamps();
                $table->index(['server_id', 'session_id', 'observed_at'], 'captainfin_activity_session');
            });
        } else {
            if (!$schema->hasColumn(self::TABLE_ACTIVITY, 'media_server_type')) {
                $schema->table(self::TABLE_ACTIVITY, static function (Blueprint $table): void {
                    $table->string('media_server_type', 32)->default('jellyfin')->index();
                });
            }
            if (!$schema->hasColumn(self::TABLE_ACTIVITY, 'media_user_id')) {
                $schema->table(self::TABLE_ACTIVITY, static function (Blueprint $table): void {
                    $table->string('media_user_id', 191)->nullable()->index();
                });
            }
            if ($schema->hasColumn(self::TABLE_ACTIVITY, 'jellyfin_user_id')) {
                Capsule::statement(
                    'UPDATE ' . self::TABLE_ACTIVITY
                    . ' SET media_user_id = jellyfin_user_id, media_server_type = ?'
                    . ' WHERE media_user_id IS NULL AND jellyfin_user_id IS NOT NULL',
                    ['jellyfin']
                );
            }
        }

        if (!$schema->hasTable(self::TABLE_ACTIVE_SESSIONS)) {
            $schema->create(self::TABLE_ACTIVE_SESSIONS, static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('server_id')->index();
                $table->unsignedInteger('service_id')->nullable()->index();
                $table->string('session_id', 191);
                $table->string('media_server_type', 32)->default('jellyfin')->index();
                $table->string('media_user_id', 191)->index();
                $table->string('jellyfin_user_id', 191)->nullable()->index();
                $table->string('network_key', 191)->nullable();
                $table->boolean('is_transcoding')->default(false)->index();
                $table->unsignedInteger('source_height')->default(0);
                $table->dateTime('first_seen_at')->index();
                $table->dateTime('last_seen_at')->index();
                $table->text('observation_json')->nullable();
                $table->timestamps();
                $table->unique(['server_id', 'session_id'], 'captainfin_active_server_session');
            });
        } else {
            if (!$schema->hasColumn(self::TABLE_ACTIVE_SESSIONS, 'media_server_type')) {
                $schema->table(self::TABLE_ACTIVE_SESSIONS, static function (Blueprint $table): void {
                    $table->string('media_server_type', 32)->default('jellyfin')->index();
                });
            }
            if (!$schema->hasColumn(self::TABLE_ACTIVE_SESSIONS, 'media_user_id')) {
                $schema->table(self::TABLE_ACTIVE_SESSIONS, static function (Blueprint $table): void {
                    $table->string('media_user_id', 191)->nullable()->index();
                });
            }
            if ($schema->hasColumn(self::TABLE_ACTIVE_SESSIONS, 'jellyfin_user_id')) {
                Capsule::statement(
                    'UPDATE ' . self::TABLE_ACTIVE_SESSIONS
                    . ' SET media_user_id = jellyfin_user_id, media_server_type = ?'
                    . ' WHERE media_user_id IS NULL AND jellyfin_user_id IS NOT NULL',
                    ['jellyfin']
                );
            }
        }

        if (!$schema->hasTable(self::TABLE_TELEMETRY)) {
            $schema->create(self::TABLE_TELEMETRY, static function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('server_id')->unique();
                $table->string('last_poll_status', 32)->default('unknown')->index();
                $table->dateTime('last_poll_at')->nullable();
                $table->dateTime('last_successful_poll_at')->nullable()->index();
                $table->unsignedInteger('consecutive_failures')->default(0);
                $table->text('last_error')->nullable();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable(self::TABLE_POLICY_EVENTS)) {
            $schema->create(self::TABLE_POLICY_EVENTS, static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('service_id')->index();
                $table->unsignedInteger('server_id')->index();
                $table->string('session_id', 191)->nullable()->index();
                $table->string('action', 64)->index();
                $table->string('reason', 64)->index();
                $table->text('detail_json')->nullable();
                $table->dateTime('observed_at')->index();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable(self::TABLE_HEALTH)) {
            $schema->create(self::TABLE_HEALTH, static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->string('integration', 32)->index();
                $table->string('scope', 191)->nullable()->index();
                $table->string('status', 32)->index();
                $table->unsignedInteger('latency_ms')->nullable();
                $table->text('detail')->nullable();
                $table->dateTime('checked_at')->index();
                $table->timestamps();
            });
        }

        if (!$schema->hasTable(self::TABLE_AUDIT)) {
            $schema->create(self::TABLE_AUDIT, static function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('service_id')->nullable()->index();
                $table->unsignedInteger('actor_admin_id')->nullable()->index();
                $table->string('action', 96)->index();
                $table->string('source', 32)->default('system')->index();
                $table->text('detail_json')->nullable();
                $table->timestamps();
            });
        }
    }

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
            self::TABLE_SERVERS => $schema->hasTable(self::TABLE_SERVERS),
            self::TABLE_INTEGRATION_BINDINGS => $schema->hasTable(self::TABLE_INTEGRATION_BINDINGS),
            self::TABLE_ACTIVITY => $schema->hasTable(self::TABLE_ACTIVITY),
            self::TABLE_ACTIVE_SESSIONS => $schema->hasTable(self::TABLE_ACTIVE_SESSIONS),
            self::TABLE_TELEMETRY => $schema->hasTable(self::TABLE_TELEMETRY),
            self::TABLE_POLICY_EVENTS => $schema->hasTable(self::TABLE_POLICY_EVENTS),
            self::TABLE_HEALTH => $schema->hasTable(self::TABLE_HEALTH),
            self::TABLE_AUDIT => $schema->hasTable(self::TABLE_AUDIT),
        ];
    }
}
