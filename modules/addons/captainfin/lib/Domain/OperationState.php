<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Domain;

final class OperationState
{
    public const PLANNED = 'planned';
    public const REMOTE_APPLIED = 'remote_applied';
    public const LOCAL_APPLIED = 'local_applied';
    public const FAILED = 'failed';
    public const MANUAL_ATTENTION = 'manual_attention';

    private const ALL = [
        self::PLANNED,
        self::REMOTE_APPLIED,
        self::LOCAL_APPLIED,
        self::FAILED,
        self::MANUAL_ATTENTION,
    ];

    private function __construct()
    {
    }

    public static function isValid(string $state): bool
    {
        return in_array($state, self::ALL, true);
    }

    public static function isTerminal(string $state): bool
    {
        return in_array($state, [self::LOCAL_APPLIED, self::MANUAL_ATTENTION], true);
    }
}
