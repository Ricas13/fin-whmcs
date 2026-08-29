<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests;

use CaptainFin\Whmcs\Domain\OperationState;
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
        self::assertFalse(OperationState::isValid('success-ish'));
    }

    public function testOnlyConvergedAndManualAttentionStatesAreTerminal(): void
    {
        self::assertTrue(OperationState::isTerminal(OperationState::LOCAL_APPLIED));
        self::assertTrue(OperationState::isTerminal(OperationState::MANUAL_ATTENTION));
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

    public function testProductOptionsContainEstablishedJellyfinManagementBaseline(): void
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

        self::assertSame('off', $options['Allow Video Transcoding']['Default']);
        self::assertSame('off', $options['Allow Remote Access']['Default']);
        self::assertSame('on', $options['Allow Subtitle Editing']['Default']);
    }
}
