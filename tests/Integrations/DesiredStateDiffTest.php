<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Integrations;

use CaptainFin\Whmcs\Integrations\DesiredStateDiff;
use PHPUnit\Framework\TestCase;

final class DesiredStateDiffTest extends TestCase
{
    public function testEquivalentSetLikeListsProduceNoDiff(): void
    {
        $observed = ['enabled' => true, 'permissions' => ['request', 'manage']];
        $desired = ['enabled' => true, 'permissions' => ['manage', 'request']];

        self::assertSame([], DesiredStateDiff::between($observed, $desired));
    }

    public function testOnlyChangedFieldsAreReturned(): void
    {
        $diff = DesiredStateDiff::between(
            ['enabled' => true, 'quota' => 5, 'name' => 'A'],
            ['enabled' => true, 'quota' => 10, 'name' => 'A'],
            ['enabled', 'quota', 'name']
        );

        self::assertSame(['quota'], array_keys($diff));
        self::assertSame(5, $diff['quota']['observed']);
        self::assertSame(10, $diff['quota']['desired']);
    }
}
