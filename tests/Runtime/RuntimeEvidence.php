<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Runtime;

final class RuntimeEvidence
{
    /** @param string[] $proven */
    public static function missing(array $proven): array
    {
        return array_values(array_diff(AcceptanceChecklist::REQUIRED, $proven));
    }

    private function __construct()
    {
    }
}
