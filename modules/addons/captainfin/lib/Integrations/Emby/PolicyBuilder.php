<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\Emby;

final class PolicyBuilder
{
    private function __construct()
    {
    }

    public static function active(array $params, bool $enableAllFolders, array $enabledFolders): array
    {
        return self::build(
            false,
            $enableAllFolders,
            $enabledFolders,
            self::boolOption($params, 13, false),
            self::boolOption($params, 14, false),
            self::boolOption($params, 15, false),
            self::boolOption($params, 16, false),
            self::boolOption($params, 17, false),
            self::boolOption($params, 18, false),
            self::boolOption($params, 19, false),
            self::boolOption($params, 20, true),
            self::intOption($params, 4, 0),
        );
    }

    public static function disabled(): array
    {
        return self::build(false, false, [], false, false, false, false, false, false, false, false, 0, true);
    }

    private static function build(
        bool $isAdministrator,
        bool $enableAllFolders,
        array $enabledFolders,
        bool $allowDownloads,
        bool $allowVideoTranscoding,
        bool $allowAudioTranscoding,
        bool $allowRemuxing,
        bool $allowLiveTv,
        bool $allowLiveTvManagement,
        bool $allowRemoteAccess,
        bool $allowSubtitleEditing,
        int $simultaneousStreamLimit,
        bool $disabled = false,
    ): array {
        $enabled = !$disabled;

        return [
            'IsAdministrator' => $isAdministrator,
            'IsHidden' => true,
            'IsDisabled' => $disabled,
            'EnableAllDevices' => $enabled,
            'EnableAllFolders' => $enabled && $enableAllFolders,
            'EnabledFolders' => $enabled ? array_values($enabledFolders) : [],
            'EnableAllChannels' => false,
            'EnableRemoteAccess' => $enabled && $allowRemoteAccess,
            'EnableMediaPlayback' => $enabled,
            'EnableAudioPlaybackTranscoding' => $enabled && $allowAudioTranscoding,
            'EnableVideoPlaybackTranscoding' => $enabled && $allowVideoTranscoding,
            'EnablePlaybackRemuxing' => $enabled && $allowRemuxing,
            'EnableContentDownloading' => $enabled && $allowDownloads,
            'EnableSyncTranscoding' => false,
            'EnableMediaConversion' => false,
            'EnableContentDeletion' => false,
            'EnableRemoteControlOfOtherUsers' => false,
            'EnableSharedDeviceControl' => false,
            'EnableLiveTvManagement' => $enabled && $allowLiveTvManagement,
            'EnableLiveTvAccess' => $enabled && $allowLiveTv,
            'EnableSubtitleManagement' => $enabled && $allowSubtitleEditing,
            'EnableUserPreferenceAccess' => $enabled,
            'SimultaneousStreamLimit' => $enabled ? max(0, $simultaneousStreamLimit) : 0,
        ];
    }

    private static function boolOption(array $params, int $index, bool $default): bool
    {
        $key = 'configoption' . $index;
        if (!array_key_exists($key, $params) || $params[$key] === '' || $params[$key] === null) {
            return $default;
        }
        return filter_var($params[$key], FILTER_VALIDATE_BOOLEAN);
    }

    private static function intOption(array $params, int $index, int $default): int
    {
        $key = 'configoption' . $index;
        if (!array_key_exists($key, $params) || $params[$key] === '' || $params[$key] === null) {
            return $default;
        }
        return max(0, (int) $params[$key]);
    }
}
