<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Jellyfin;

use CaptainFin\Whmcs\Integrations\Jellyfin\PolicyBuilder;
use PHPUnit\Framework\TestCase;

final class PolicyBuilderTest extends TestCase
{
    public function testActivePolicyDefaultsRemainRestrictive(): void
    {
        $policy = PolicyBuilder::active([], true, []);

        self::assertFalse($policy['IsAdministrator']);
        self::assertFalse($policy['IsDisabled']);
        self::assertTrue($policy['EnableMediaPlayback']);
        self::assertTrue($policy['EnableAllFolders']);
        self::assertFalse($policy['EnableRemoteAccess']);
        self::assertFalse($policy['EnableContentDownloading']);
        self::assertFalse($policy['EnableVideoPlaybackTranscoding']);
        self::assertFalse($policy['EnableAudioPlaybackTranscoding']);
        self::assertFalse($policy['EnablePlaybackRemuxing']);
        self::assertFalse($policy['EnableLiveTvAccess']);
        self::assertFalse($policy['EnableLiveTvManagement']);
        self::assertTrue($policy['EnableSubtitleManagement']);
        self::assertSame('None', $policy['SyncPlayAccess']);
    }

    public function testConfiguredTechnicalOptionsAreApplied(): void
    {
        $params = [
            'configoption13' => 'on',
            'configoption14' => 'on',
            'configoption15' => 'on',
            'configoption16' => 'on',
            'configoption17' => 'on',
            'configoption18' => 'on',
            'configoption19' => 'on',
            'configoption20' => 'off',
        ];

        $policy = PolicyBuilder::active($params, false, ['library-1']);

        self::assertFalse($policy['EnableAllFolders']);
        self::assertSame(['library-1'], $policy['EnabledFolders']);
        self::assertTrue($policy['EnableContentDownloading']);
        self::assertTrue($policy['EnableVideoPlaybackTranscoding']);
        self::assertTrue($policy['EnableAudioPlaybackTranscoding']);
        self::assertTrue($policy['EnablePlaybackRemuxing']);
        self::assertTrue($policy['EnableLiveTvAccess']);
        self::assertTrue($policy['EnableLiveTvManagement']);
        self::assertTrue($policy['EnableRemoteAccess']);
        self::assertFalse($policy['EnableSubtitleManagement']);
    }

    public function testDisabledPolicyRemovesPlaybackAndFolderAccess(): void
    {
        $policy = PolicyBuilder::disabled();

        self::assertTrue($policy['IsDisabled']);
        self::assertFalse($policy['EnableAllDevices']);
        self::assertFalse($policy['EnableAllFolders']);
        self::assertSame([], $policy['EnabledFolders']);
        self::assertFalse($policy['EnableMediaPlayback']);
        self::assertFalse($policy['EnableRemoteAccess']);
        self::assertFalse($policy['EnableVideoPlaybackTranscoding']);
        self::assertFalse($policy['EnableAudioPlaybackTranscoding']);
        self::assertFalse($policy['EnableContentDownloading']);
    }
}
