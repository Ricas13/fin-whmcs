<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Provisioning;

use CaptainFin\Whmcs\Integrations\MediaServer\MediaServerException;

final class LifecycleFailureClassifier
{
    public const RETRY = 'retry';
    public const MANUAL_ATTENTION = 'manual_attention';

    public function classify(\Throwable $error): array
    {
        if ($error instanceof ManualAttentionException || $error instanceof \InvalidArgumentException) {
            return [
                'action' => self::MANUAL_ATTENTION,
                'delay_seconds' => null,
            ];
        }

        if ($error instanceof MediaServerException) {
            if ($error->isRetryable()) {
                return [
                    'action' => self::RETRY,
                    'delay_seconds' => 60,
                ];
            }

            return [
                'action' => self::MANUAL_ATTENTION,
                'delay_seconds' => null,
            ];
        }

        // Once a durable operation exists, local persistence/runtime errors are
        // safe to retry because every remote lifecycle path is required to be
        // observation-driven/idempotent. This closes remote-success -> local-
        // failure gaps for both Jellyfin and Emby without provider-specific
        // retry ownership.
        return [
            'action' => self::RETRY,
            'delay_seconds' => 300,
        ];
    }
}
