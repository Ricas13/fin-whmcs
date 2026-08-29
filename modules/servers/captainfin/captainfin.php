<?php

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use CaptainFin\Whmcs\Integrations\Jellyfin\HttpClient;
use CaptainFin\Whmcs\Integrations\Jellyfin\JellyfinClient;
use CaptainFin\Whmcs\Integrations\Jellyfin\ServerConfig;
use CaptainFin\Whmcs\Provisioning\LifecycleService;

$captainfinAutoloader = dirname(__DIR__, 2) . '/addons/captainfin/lib/autoload.php';

if (!is_file($captainfinAutoloader)) {
    throw new RuntimeException('CAPTAiNFiN addon files are missing. Install modules/addons/captainfin together with the server module.');
}

require_once $captainfinAutoloader;

function captainfin_MetaData(): array
{
    return [
        'DisplayName' => 'CAPTAiNFiN',
        'APIVersion' => '1.1',
        'RequiresServer' => true,
        'DefaultNonSSLPort' => 8096,
        'DefaultSSLPort' => 8920,
    ];
}

function captainfin_ConfigOptions(): array
{
    return [
        'Plan Class' => [
            'Type' => 'text',
            'Size' => '25',
            'Default' => 'premium',
            'Description' => 'Logical entitlement/server class, for example premium or free.',
            'SimpleMode' => true,
        ],
        'Libraries' => [
            'Type' => 'text',
            'Size' => '50',
            'Description' => 'Comma-separated allowed Jellyfin libraries. Empty grants all libraries.',
            'SimpleMode' => true,
        ],
        'User Selectable Libraries' => [
            'Type' => 'yesno',
            'Description' => 'Allow the client to choose from the libraries permitted by this product.',
            'Default' => 'off',
            'SimpleMode' => true,
        ],
        'Maximum Concurrent Streams' => [
            'Type' => 'text',
            'Size' => '8',
            'Default' => '0',
            'Description' => '0 disables the product-level stream limit.',
            'SimpleMode' => true,
        ],
        'Maximum Concurrent Transcodes' => [
            'Type' => 'text',
            'Size' => '8',
            'Default' => '0',
            'Description' => '0 disables the product-level transcode limit.',
            'SimpleMode' => true,
        ],
        '4K Transcode Policy' => [
            'Type' => 'dropdown',
            'Options' => 'allow,block',
            'Default' => 'allow',
            'SimpleMode' => true,
        ],
        'Network/IP Policy' => [
            'Type' => 'dropdown',
            'Options' => 'allow,household,strict_single_ip',
            'Default' => 'allow',
            'Description' => 'Controls concurrent playback from distinct network identities.',
        ],
        'Maximum Concurrent IPs' => [
            'Type' => 'text',
            'Size' => '8',
            'Default' => '0',
            'Description' => '0 disables the numeric distinct-IP limit.',
        ],
        'Jellyseerr Access' => [
            'Type' => 'yesno',
            'Default' => 'on',
            'SimpleMode' => true,
        ],
        'Stremio Access' => [
            'Type' => 'yesno',
            'Default' => 'off',
            'SimpleMode' => true,
        ],
        'Discord Managed Role ID' => [
            'Type' => 'text',
            'Size' => '25',
            'Description' => 'Optional role granted while the service is entitled.',
        ],
        'Inactivity Days' => [
            'Type' => 'text',
            'Size' => '8',
            'Default' => '0',
            'Description' => '0 disables inactivity enforcement for this product.',
        ],
        'Allow Downloads' => [
            'Type' => 'yesno',
            'Default' => 'off',
            'SimpleMode' => true,
        ],
        'Allow Video Transcoding' => [
            'Type' => 'yesno',
            'Default' => 'off',
            'SimpleMode' => true,
        ],
        'Allow Audio Transcoding' => [
            'Type' => 'yesno',
            'Default' => 'off',
            'SimpleMode' => true,
        ],
        'Allow Remuxing' => [
            'Type' => 'yesno',
            'Default' => 'off',
            'SimpleMode' => true,
        ],
        'Allow Live TV' => [
            'Type' => 'yesno',
            'Default' => 'off',
        ],
        'Allow Live TV Management' => [
            'Type' => 'yesno',
            'Default' => 'off',
        ],
        'Allow Remote Access' => [
            'Type' => 'yesno',
            'Default' => 'off',
            'SimpleMode' => true,
        ],
        'Allow Subtitle Editing' => [
            'Type' => 'yesno',
            'Default' => 'on',
        ],
    ];
}

function captainfin_TestConnection(array $params): array
{
    try {
        $config = ServerConfig::fromWhmcs($params);
        $info = (new JellyfinClient(new HttpClient($config)))->systemInfo();
        $version = trim((string) ($info['Version'] ?? ''));
        $serverName = trim((string) ($info['ServerName'] ?? ''));
        $details = array_values(array_filter([$serverName, $version !== '' ? 'Jellyfin ' . $version : null]));

        return [
            'success' => true,
            'error' => '',
            'message' => $details !== [] ? implode(' · ', $details) : 'Connected to Jellyfin successfully.',
        ];
    } catch (Throwable $error) {
        return [
            'success' => false,
            'error' => $error->getMessage(),
        ];
    }
}

function captainfin_CreateAccount(array $params): string
{
    return captainfin_run_lifecycle('create', $params);
}

function captainfin_SuspendAccount(array $params): string
{
    return captainfin_run_lifecycle('suspend', $params);
}

function captainfin_UnsuspendAccount(array $params): string
{
    return captainfin_run_lifecycle('unsuspend', $params);
}

function captainfin_TerminateAccount(array $params): string
{
    return captainfin_run_lifecycle('terminate', $params);
}

function captainfin_ChangePackage(array $params): string
{
    return captainfin_run_lifecycle('change_package', $params);
}

function captainfin_ChangePassword(array $params): string
{
    return captainfin_run_lifecycle('change_password', $params);
}

function captainfin_run_lifecycle(string $operationType, array $params): string
{
    return (new LifecycleService())->execute($operationType, $params);
}
