<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Reconciliation;

use CaptainFin\Whmcs\Reconciliation\WhmcsModuleCommandRunner;
use PHPUnit\Framework\TestCase;

final class WhmcsModuleCommandRunnerTest extends TestCase
{
    /** @dataProvider commandProvider */
    public function testMapsOperationToWhmcsModuleCommand(string $operation, string $expectedCommand): void
    {
        $calls = [];
        $runner = new WhmcsModuleCommandRunner(static function (string $command, array $params) use (&$calls): array {
            $calls[] = [$command, $params];
            return ['result' => 'success'];
        });

        $result = $runner->run($operation, 44);

        self::assertTrue($result['success']);
        self::assertSame([[$expectedCommand, ['serviceid' => 44]]], $calls);
    }

    public static function commandProvider(): array
    {
        return [
            'create' => ['create', 'ModuleCreate'],
            'suspend' => ['suspend', 'ModuleSuspend'],
            'unsuspend' => ['unsuspend', 'ModuleUnsuspend'],
            'terminate' => ['terminate', 'ModuleTerminate'],
            'package' => ['change_package', 'ModuleChangePackage'],
        ];
    }

    public function testPasswordOperationIsNotReplayed(): void
    {
        $called = false;
        $runner = new WhmcsModuleCommandRunner(static function () use (&$called): array {
            $called = true;
            return ['result' => 'success'];
        });

        $result = $runner->run('change_password', 44);

        self::assertFalse($called);
        self::assertFalse($result['success']);
        self::assertTrue($result['unsupported']);
    }

    public function testWhmcsErrorIsPreservedWithoutInventingSuccess(): void
    {
        $runner = new WhmcsModuleCommandRunner(static fn (): array => [
            'result' => 'error',
            'message' => 'Module command failed',
        ]);

        $result = $runner->run('suspend', 44);

        self::assertFalse($result['success']);
        self::assertFalse($result['unsupported']);
        self::assertSame('Module command failed', $result['message']);
    }

    public function testThrownWhmcsFailureBecomesStructuredFailure(): void
    {
        $runner = new WhmcsModuleCommandRunner(static function (): array {
            throw new \RuntimeException('boom');
        });

        $result = $runner->run('terminate', 44);

        self::assertFalse($result['success']);
        self::assertStringContainsString('boom', $result['message']);
    }
}
