<?php

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use CaptainFin\Whmcs\Diagnostics\OperationDiagnosticsRepository;
use CaptainFin\Whmcs\Infrastructure\Database\Schema;
use WHMCS\Database\Capsule;

require_once __DIR__ . '/lib/autoload.php';

function captainfin_config(): array
{
    return [
        'name' => 'CAPTAiNFiN',
        'description' => 'Media-service provisioning, policy, reconciliation and diagnostics for WHMCS.',
        'version' => '0.2.0-dev',
        'author' => 'CAPTAiNFiN',
        'language' => 'english',
        'fields' => [
            'policySamplerEnabled' => [
                'FriendlyName' => 'Policy sampler',
                'Type' => 'yesno',
                'Description' => 'Enable near-real-time activity/stream policy sampling once the sampler command is installed.',
                'Default' => 'on',
            ],
        ],
    ];
}

function captainfin_activate(): array
{
    try {
        Schema::install();

        return [
            'status' => 'success',
            'description' => 'CAPTAiNFiN schema installed. Operational data is retained on deactivation.',
        ];
    } catch (Throwable $error) {
        return [
            'status' => 'error',
            'description' => 'Unable to activate CAPTAiNFiN: ' . $error->getMessage(),
        ];
    }
}

function captainfin_upgrade(array $vars): void
{
    $installed = (string) ($vars['version'] ?? '0.0.0');
    if (version_compare($installed, '0.2.0', '<')) {
        // Schema::install() is deliberately idempotent. Version 0.2 adds the
        // integration/activity/telemetry/health/audit operational tables.
        Schema::install();
    }
}

function captainfin_deactivate(): array
{
    try {
        Schema::deactivate();

        return [
            'status' => 'success',
            'description' => 'CAPTAiNFiN deactivated. Operational data was preserved.',
        ];
    } catch (Throwable $error) {
        return [
            'status' => 'error',
            'description' => 'Unable to deactivate CAPTAiNFiN cleanly: ' . $error->getMessage(),
        ];
    }
}

function captainfin_output(array $vars): void
{
    try {
        $health = Schema::health();
        $operations = Capsule::table('mod_captainfin_operations')->count();
        $bindings = Capsule::table('mod_captainfin_service_bindings')->count();
        $integrationBindings = Capsule::table('mod_captainfin_integration_bindings')->count();
        $diagnostics = (new OperationDiagnosticsRepository())->summary();
    } catch (Throwable $error) {
        echo '<div class="alert alert-danger">CAPTAiNFiN database health check failed: '
            . htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8')
            . '</div>';
        return;
    }

    $schemaHealthy = !in_array(false, $health, true);

    echo '<div class="container-fluid">';
    echo '<h2>CAPTAiNFiN</h2>';
    echo '<p>Native WHMCS media provisioning and lifecycle management.</p>';
    echo '<div class="row">';
    captainfin_render_stat('Schema', $schemaHealthy ? 'Healthy' : 'Incomplete');
    captainfin_render_stat('Service bindings', (string) $bindings);
    captainfin_render_stat('Integration bindings', (string) $integrationBindings);
    captainfin_render_stat('Operations', (string) $operations);
    captainfin_render_stat('Unresolved operations', (string) $diagnostics['unresolved']);
    captainfin_render_stat('Manual attention', (string) $diagnostics['manual_attention']);
    echo '</div>';

    echo '<div class="alert alert-info" style="margin-top: 20px">';
    echo '<strong>Development build.</strong> Jellyfin lifecycle and automatic operation recovery are active. '
        . 'Multi-server policy, activity enforcement and cross-integration adapters are under pre-license hardening.';
    echo '</div>';
    echo '</div>';
}

function captainfin_render_stat(string $label, string $value): void
{
    echo '<div class="col-md-3 col-sm-6">';
    echo '<div class="panel panel-default"><div class="panel-body">';
    echo '<div style="font-size:12px;text-transform:uppercase;color:#777">'
        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        . '</div>';
    echo '<div style="font-size:24px;font-weight:600">'
        . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
        . '</div>';
    echo '</div></div></div>';
}
