<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Policy;

use CaptainFin\Whmcs\Policy\ProductPolicy;
use PHPUnit\Framework\TestCase;

final class ProductPolicyTest extends TestCase
{
    public function testNormalisesWhmcsOptionsWithRestrictiveDefaults(): void
    {
        $policy = ProductPolicy::fromWhmcsParams([
            'configoption1' => 'premium',
            'configoption2' => 'Movies, TV Shows, movies',
            'configoption4' => '2',
            'configoption5' => '1',
            'configoption6' => 'block',
            'configoption7' => 'strict_single_ip',
            'configoption8' => '1',
            'configoption9' => 'yes',
            'configoption20' => 'yes',
        ]);

        self::assertSame(['Movies', 'TV Shows'], $policy['libraries']);
        self::assertSame(2, $policy['max_streams']);
        self::assertSame(1, $policy['max_transcodes']);
        self::assertSame('block', $policy['four_k_transcode_policy']);
        self::assertSame('strict_single_ip', $policy['network_policy']);
        self::assertTrue($policy['jellyseerr_access']);
        self::assertFalse($policy['allow_downloads']);
        self::assertFalse($policy['allow_video_transcoding']);
        self::assertTrue($policy['allow_subtitle_editing']);
    }

    public function testInvalidNumericAndEnumValuesFailSafeToBoundedDefaults(): void
    {
        $policy = ProductPolicy::fromWhmcsParams([
            'configoption4' => '-10',
            'configoption5' => '999',
            'configoption6' => 'surprise',
            'configoption7' => 'whatever',
        ]);

        self::assertSame(0, $policy['max_streams']);
        self::assertSame(50, $policy['max_transcodes']);
        self::assertSame('allow', $policy['four_k_transcode_policy']);
        self::assertSame('allow', $policy['network_policy']);
    }
}
