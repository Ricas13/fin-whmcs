<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Runtime;

use CaptainFin\Whmcs\Integrations\Emby\EmbyClient;
use CaptainFin\Whmcs\Integrations\Emby\HttpClient;
use CaptainFin\Whmcs\Integrations\Emby\ServerConfig;
use CaptainFin\Whmcs\Tests\FakeWhmcs\DatabaseHarness;
use CaptainFin\Whmcs\Tests\FakeWhmcs\FixtureFactory;
use CaptainFin\Whmcs\Tests\FakeWhmcs\ModuleRuntime;
use CaptainFin\Whmcs\Tests\FakeWhmcs\RuntimeSkip;
use PHPUnit\Framework\TestCase;

final class EmbyLifecycleRuntimeTest extends TestCase
{
    public function testExportedWhmcsLifecycleConvergesRealEmbyAndDurableSqlState(): void
    {
        if (!RuntimeSkip::embyConfigured()) {
            self::markTestSkipped('Real lifecycle suite requires the disposable Emby runtime environment.');
        }

        ModuleRuntime::load();
        DatabaseHarness::resetModuleState();

        $params = FixtureFactory::service([
            'serviceid' => 12001,
            'userid' => 5201,
            'pid' => 102,
            'serverid' => 2,
            'serverhostname' => (string) getenv('CAPTAINFIN_TEST_EMBY_URL'),
            'serverport' => 0,
            'serverusername' => 'emby',
            'serverpassword' => (string) getenv('CAPTAINFIN_TEST_EMBY_API_KEY'),
            'username' => 'captainfin_emby_runtime_12001',
            'password' => 'Runtime-Emby-Service-Password!12001',
            'configoption4' => '2',
            'configoption5' => '1',
            'configoption6' => 'block',
        ]);
        $client = new EmbyClient(new HttpClient(ServerConfig::fromWhmcs($params)));

        $connection = captainfin_TestConnection($params);
        self::assertTrue((bool) ($connection['success'] ?? false), (string) ($connection['error'] ?? 'Emby connection failed'));

        $preexisting = $client->findUserByName($params['username']);
        if ($preexisting !== null && trim((string) ($preexisting['Id'] ?? '')) !== '') {
            $client->deleteUser((string) $preexisting['Id']);
        }

        self::assertSame('success', captainfin_CreateAccount($params));
        $created = $client->findUserByName($params['username']);
        self::assertNotNull($created);
        $userId = trim((string) ($created['Id'] ?? ''));
        self::assertNotSame('', $userId);
        self::assertFalse((bool) ($created['Policy']['IsDisabled'] ?? true));
        self::assertSame(2, (int) ($created['Policy']['SimultaneousStreamLimit'] ?? -1));

        $binding = DatabaseHarness::table('mod_captainfin_service_bindings')->where('service_id', 12001)->first();
        self::assertNotNull($binding);
        self::assertSame('emby', (string) $binding->media_server_type);
        self::assertSame($userId, (string) $binding->media_user_id);
        self::assertSame('active', (string) $binding->state);

        self::assertSame('success', captainfin_CreateAccount($params));
        $matches = array_values(array_filter(
            $client->listUsers(),
            static fn (array $user): bool => mb_strtolower((string) ($user['Name'] ?? ''), 'UTF-8') === mb_strtolower($params['username'], 'UTF-8')
        ));
        self::assertCount(1, $matches);
        self::assertSame($userId, (string) ($matches[0]['Id'] ?? ''));

        self::assertSame('success', captainfin_SuspendAccount($params));
        $suspended = $client->getUser($userId);
        self::assertNotNull($suspended);
        self::assertTrue((bool) ($suspended['Policy']['IsDisabled'] ?? false));
        self::assertSame(
            'suspended',
            (string) DatabaseHarness::table('mod_captainfin_service_bindings')->where('service_id', 12001)->value('state')
        );

        self::assertSame('success', captainfin_UnsuspendAccount($params));
        $active = $client->getUser($userId);
        self::assertNotNull($active);
        self::assertFalse((bool) ($active['Policy']['IsDisabled'] ?? true));

        $changedPackage = $params;
        $changedPackage['configoption13'] = 'on';
        self::assertSame('success', captainfin_ChangePackage($changedPackage));
        $packageApplied = $client->getUser($userId);
        self::assertNotNull($packageApplied);
        self::assertTrue((bool) ($packageApplied['Policy']['EnableContentDownloading'] ?? false));

        $changedPassword = $changedPackage;
        $changedPassword['password'] = 'Runtime-Emby-Changed-Password!12001';
        self::assertSame('success', captainfin_ChangePassword($changedPassword));

        self::assertSame('success', captainfin_TerminateAccount($changedPassword));
        self::assertNull($client->getUser($userId));
        self::assertSame(
            'terminated',
            (string) DatabaseHarness::table('mod_captainfin_service_bindings')->where('service_id', 12001)->value('state')
        );

        $latest = DatabaseHarness::table('mod_captainfin_operations')->where('service_id', 12001)->orderByDesc('id')->first();
        self::assertNotNull($latest);
        self::assertSame('local_applied', (string) $latest->state);
    }
}
