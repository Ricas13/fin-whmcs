<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests;

use CaptainFin\Whmcs\Domain\OperationState;
use CaptainFin\Whmcs\Infrastructure\Database\Schema;
use CaptainFin\Whmcs\Integrations\MediaServer\MediaServerType;
use PHPUnit\Framework\TestCase;

final class ModuleContractTest extends TestCase
{
    public function testOperationStatesAreRecognised(): void
    {
        self::assertTrue(OperationState::isValid(OperationState::PLANNED));
        self::assertTrue(OperationState::isValid(OperationState::REMOTE_APPLIED));
        self::assertTrue(OperationState::isValid(OperationState::LOCAL_APPLIED));
        self::assertTrue(OperationState::isValid(OperationState::FAILED));
        self::assertTrue(OperationState::isValid(OperationState::MANUAL_ATTENTION));
        self::assertTrue(OperationState::isValid(OperationState::SUPERSEDED));
        self::assertFalse(OperationState::isValid('success-ish'));
    }

    public function testTerminalOperationStatesAreExplicit(): void
    {
        self::assertTrue(OperationState::isTerminal(OperationState::LOCAL_APPLIED));
        self::assertTrue(OperationState::isTerminal(OperationState::MANUAL_ATTENTION));
        self::assertTrue(OperationState::isTerminal(OperationState::SUPERSEDED));
        self::assertFalse(OperationState::isTerminal(OperationState::PLANNED));
        self::assertFalse(OperationState::isTerminal(OperationState::REMOTE_APPLIED));
        self::assertFalse(OperationState::isTerminal(OperationState::FAILED));
    }

    public function testProvisioningModuleExposesRequiredLifecycleSurface(): void
    {
        require_once dirname(__DIR__) . '/modules/servers/captainfin/captainfin.php';

        self::assertTrue(function_exists('captainfin_TestConnection'));
        self::assertTrue(function_exists('captainfin_CreateAccount'));
        self::assertTrue(function_exists('captainfin_SuspendAccount'));
        self::assertTrue(function_exists('captainfin_UnsuspendAccount'));
        self::assertTrue(function_exists('captainfin_TerminateAccount'));
        self::assertTrue(function_exists('captainfin_ChangePackage'));
        self::assertTrue(function_exists('captainfin_ChangePassword'));

        $metadata = captainfin_MetaData();
        self::assertSame('CAPTAiNFiN', $metadata['DisplayName']);
        self::assertTrue($metadata['RequiresServer']);
    }

    public function testAddonExposesActivationUpgradeAndNonDestructiveDeactivationSurface(): void
    {
        require_once dirname(__DIR__) . '/modules/addons/captainfin/captainfin.php';

        self::assertTrue(function_exists('captainfin_activate'));
        self::assertTrue(function_exists('captainfin_upgrade'));
        self::assertTrue(function_exists('captainfin_deactivate'));
        self::assertGreaterThanOrEqual(3, Schema::VERSION);

        $config = captainfin_config();
        self::assertSame('CAPTAiNFiN', $config['name']);
        self::assertSame('0.3.0-dev', $config['version']);
    }

    public function testProductOptionsContainEstablishedMediaServerManagementBaseline(): void
    {
        require_once dirname(__DIR__) . '/modules/servers/captainfin/captainfin.php';

        $options = captainfin_ConfigOptions();

        self::assertArrayHasKey('Libraries', $options);
        self::assertArrayHasKey('User Selectable Libraries', $options);
        self::assertArrayHasKey('Maximum Concurrent Streams', $options);
        self::assertArrayHasKey('Maximum Concurrent Transcodes', $options);
        self::assertArrayHasKey('4K Transcode Policy', $options);
        self::assertArrayHasKey('Network/IP Policy', $options);
        self::assertArrayHasKey('Jellyseerr Access', $options);
        self::assertArrayHasKey('Stremio Access', $options);
        self::assertArrayHasKey('Discord Managed Role ID', $options);
        self::assertArrayHasKey('Inactivity Days', $options);
        self::assertArrayHasKey('Allow Downloads', $options);
        self::assertArrayHasKey('Allow Video Transcoding', $options);
        self::assertArrayHasKey('Allow Audio Transcoding', $options);
        self::assertArrayHasKey('Allow Remuxing', $options);
        self::assertArrayHasKey('Allow Live TV', $options);
        self::assertArrayHasKey('Allow Live TV Management', $options);
        self::assertArrayHasKey('Allow Remote Access', $options);
        self::assertArrayHasKey('Allow Subtitle Editing', $options);

        self::assertArrayNotHasKey('Default', $options['Allow Video Transcoding']);
        self::assertArrayNotHasKey('Default', $options['Allow Remote Access']);
        self::assertSame('yes', $options['Allow Subtitle Editing']['Default']);
        self::assertSame('yes', $options['Jellyseerr Access']['Default']);
    }

    public function testMediaServerSelectorDefaultsLegacyServersToJellyfinAndAllowsEmby(): void
    {
        self::assertSame(MediaServerType::JELLYFIN, MediaServerType::fromWhmcs([]));
        self::assertSame(MediaServerType::JELLYFIN, MediaServerType::fromWhmcs(['serverusername' => 'jellyfin']));
        self::assertSame(MediaServerType::EMBY, MediaServerType::fromWhmcs(['serverusername' => 'EmBy']));

        $this->expectException(\InvalidArgumentException::class);
        MediaServerType::fromWhmcs(['serverusername' => 'plex']);
    }

    public function testAddonShipsAutomaticReconciliationHook(): void
    {
        $hooks = dirname(__DIR__) . '/modules/addons/captainfin/hooks.php';
        self::assertFileExists($hooks);

        $contents = file_get_contents($hooks);
        self::assertIsString($contents);
        self::assertStringContainsString("add_hook('AfterCronJob'", $contents);
        self::assertStringContainsString('new Reconciler()', $contents);
    }
}
