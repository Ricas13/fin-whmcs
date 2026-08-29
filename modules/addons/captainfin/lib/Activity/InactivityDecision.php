<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Activity;

use DateTimeImmutable;

final class InactivityDecision
{
    public static function evaluate(
        int $inactivityDays,
        ?DateTimeImmutable $lastObservedPlaybackAt,
        DateTimeImmutable $now,
        array $telemetryTrust
    ): array {
        if ($inactivityDays <= 0) {
            return ['enforce' => false, 'reason' => 'policy_disabled'];
        }
        if (empty($telemetryTrust['ready'])) {
            return ['enforce' => false, 'reason' => 'telemetry_untrusted'];
        }
        if ($lastObservedPlaybackAt === null) {
            return ['enforce' => false, 'reason' => 'no_observation_baseline'];
        }

        $cutoff = $now->modify('-' . $inactivityDays . ' days');
        if ($lastObservedPlaybackAt > $cutoff) {
            return ['enforce' => false, 'reason' => 'recent_activity', 'cutoff' => $cutoff];
        }

        return [
            'enforce' => true,
            'reason' => 'inactive',
            'cutoff' => $cutoff,
            'last_observed_playback_at' => $lastObservedPlaybackAt,
        ];
    }

    private function __construct()
    {
    }
}
