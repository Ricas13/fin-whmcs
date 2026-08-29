<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Runtime;

use CaptainFin\Whmcs\Integrations\Jellyfin\HttpClient;
use CaptainFin\Whmcs\Integrations\Jellyfin\JellyfinClient;
use CaptainFin\Whmcs\Integrations\Jellyfin\ServerConfig;
use CaptainFin\Whmcs\Tests\FakeWhmcs\DatabaseHarness;
use CaptainFin\Whmcs\Tests\FakeWhmcs\FixtureFactory;
use CaptainFin\Whmcs\Tests\FakeWhmcs\ModuleRuntime;
use CaptainFin\Whmcs\Tests\FakeWhmcs\RuntimeSkip;
use PHPUnit\Framework\TestCase;

final class JellyfinLifecycleRuntimeTest extends TestCase
{
    public function testExportedWhmcsLifecycleConvergesRealJellyfinAndDurableSqlState(): void
    {
        if (!RuntimeSkip::jellyfinConfigured()) {
            self::markTestSkipped('Real lifecycle suite requires the disposable Jellyfin runtime environment.');
        }

        ModuleRuntime::load();
        DatabaseHarness::resetModuleState();

        $params = FixtureFactory::service([
            'serviceid' => 11001,
            'userid' => 5101,
            'pid' => 101,
            'serverid' => 1,
            'username' => 'captainfin_runtime_11001',
            'password' => 'Runtime-Service-Password!11001',
            'configoption4' => '2',
            'configoption5' => '1',
            'configoption6' => 'block',
        ]);
        $client = new JellyfinClient(new HttpClient(ServerConfig::fromWhmcs($params)));

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

        $binding = DatabaseHarness::table('mod_captainfin_service_bindings')->where('service_id', 11001)->first();
        self::assertNotNull($binding);
        self::assertSame($userId, (string) $binding->jellyfin_user_id);
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
            (string) DatabaseHarness::table('mod_captainfin_service_bindings')->where('service_id', 11001)->value('state')
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
        $changedPassword['password'] = 'Runtime-Changed-Password!11001';
        self::assertSame('success', captainfin_ChangePassword($changedPassword));

        self::assertSame('success', captainfin_TerminateAccount($changedPassword));
        self::assertNull($client->getUser($userId));
        self::assertSame(
            'terminated',
            (string) DatabaseHarness::table('mod_captainfin_service_bindings')->where('service_id', 11001)->value('state')
        );

        $latest = DatabaseHarness::table('mod_captainfin_operations')->where('service_id', 11001)->orderByDesc('id')->first();
        self::assertNotNull($latest);
        self::assertSame('local_applied', (string) $latest->state);
    }
}
