<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Activity;

final class PlaybackPresentation
{
    public static function describe(array $observation): array
    {
        $source = strtolower(trim((string) ($observation['source'] ?? 'unknown')));
        $label = match ($source) {
            'jellyfin_webhook' => 'Observed playback event',
            'jellyfin_session_poll' => 'Observed active playback',
            default => 'Observed playback activity',
        };

        return [
            'label' => $label,
            'source' => $source,
            'is_exact_billing_measurement' => false,
            'disclaimer' => 'Playback activity reflects events and session observations received from the configured Jellyfin server.',
        ];
    }

    private function __construct()
    {
    }
}
