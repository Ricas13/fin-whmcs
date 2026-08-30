<?php

declare(strict_types=1);

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly.');
}

use CaptainFin\Whmcs\Commercial\Edition;
use CaptainFin\Whmcs\Commercial\EditionGate;
use CaptainFin\Whmcs\Integrations\Emby\EmbyClient;
use CaptainFin\Whmcs\Integrations\Emby\HttpClient as EmbyHttpClient;
use CaptainFin\Whmcs\Integrations\Emby\ServerConfig as EmbyServerConfig;
use CaptainFin\Whmcs\Integrations\Jellyfin\HttpClient as JellyfinHttpClient;
use CaptainFin\Whmcs\Integrations\Jellyfin\JellyfinClient;
use CaptainFin\Whmcs\Integrations\Jellyfin\ServerConfig as JellyfinServerConfig;
use CaptainFin\Whmcs\Integrations\MediaServer\MediaServerType;
use CaptainFin\Whmcs\Provisioning\LifecycleService;

$captainfinAutoloader = dirname(__DIR__, 2) . '/addons/captainfin/lib/autoload.php';

if (!is_file($captainfinAutoloader)) {
    throw new RuntimeException('CAPTAiNFiN addon files are missing. Install modules/addons/captainfin together with the server module.');
}

require_once $captainfinAutoloader;

function captainfin_MetaData(): array
{
    $edition = Edition::current();

    return [
        'DisplayName' => $edition->displayName(),
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
            'Description' => 'Comma-separated allowed media libraries. Empty grants all libraries.',
            'SimpleMode' => true,
        ],
        'User Selectable Libraries' => [
            'Type' => 'yesno',
            'Description' => 'Allow the client to choose from the libraries permitted by this product.',
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
            'Default' => 'yes',
            'SimpleMode' => true,
        ],
        'Stremio Access' => [
            'Type' => 'yesno',
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
            'SimpleMode' => true,
        ],
        'Allow Video Transcoding' => [
            'Type' => 'yesno',
            'SimpleMode' => true,
        ],
        'Allow Audio Transcoding' => [
            'Type' => 'yesno',
            'SimpleMode' => true,
        ],
        'Allow Remuxing' => [
            'Type' => 'yesno',
            'SimpleMode' => true,
        ],
        'Allow Live TV' => [
            'Type' => 'yesno',
        ],
        'Allow Live TV Management' => [
            'Type' => 'yesno',
        ],
        'Allow Remote Access' => [
            'Type' => 'yesno',
            'SimpleMode' => true,
        ],
        'Allow Subtitle Editing' => [
            'Type' => 'yesno',
            'Default' => 'yes',
        ],
    ];
}

function captainfin_TestConnection(array $params): array
{
    try {
        $provider = MediaServerType::fromWhmcs($params);
        (new EditionGate())->assertProviderAllowed($provider);

        if ($provider === MediaServerType::EMBY) {
            $info = (new EmbyClient(new EmbyHttpClient(EmbyServerConfig::fromWhmcs($params))))->systemInfo();
            $label = 'Emby';
        } else {
            $info = (new JellyfinClient(new JellyfinHttpClient(JellyfinServerConfig::fromWhmcs($params))))->systemInfo();
            $label = 'Jellyfin';
        }

        $version = trim((string) ($info['Version'] ?? ''));
        $serverName = trim((string) ($info['ServerName'] ?? ''));
        $details = array_values(array_filter([
            $serverName,
            $version !== '' ? $label . ' ' . $version : $label,
        ]));

        return [
            'success' => true,
            'error' => '',
            'message' => implode(' · ', $details) . ' connected successfully.',
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
