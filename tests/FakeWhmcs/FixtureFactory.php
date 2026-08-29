<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\FakeWhmcs;

final class FixtureFactory
{
    public static function service(array $overrides = []): array
    {
        $defaults = [
            'serviceid' => 1001,
            'userid' => 501,
            'pid' => 10,
            'serverid' => 1,
            'serverhostname' => getenv('CAPTAINFIN_TEST_JELLYFIN_HOST') ?: '127.0.0.1',
            'serverport' => (int) (getenv('CAPTAINFIN_TEST_JELLYFIN_PORT') ?: 8096),
            'serversecure' => false,
            'serverpassword' => getenv('CAPTAINFIN_TEST_JELLYFIN_API_KEY') ?: 'test-api-key',
            'username' => 'captainfin_test_1001',
            'password' => 'CorrectHorseBatteryStaple!1001',
            'clientsdetails' => [
                'email' => 'captainfin-test@example.invalid',
            ],
            'configoption1' => 'premium',
            'configoption2' => '',
            'configoption3' => '',
            'configoption4' => '2',
            'configoption5' => '1',
            'configoption6' => 'block',
            'configoption7' => 'allow',
            'configoption8' => '0',
            'configoption9' => 'on',
            'configoption10' => '',
            'configoption11' => '',
            'configoption12' => '0',
        ];

        return array_replace_recursive($defaults, $overrides);
    }
}
