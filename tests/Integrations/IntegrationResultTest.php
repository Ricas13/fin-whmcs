<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Integrations;

use CaptainFin\Whmcs\Integrations\IntegrationResult;
use PHPUnit\Framework\TestCase;

final class IntegrationResultTest extends TestCase
{
    public function testUnchangedAlwaysReportsZeroMutations(): void
    {
        $result = IntegrationResult::unchanged(['id' => 'remote']);
        self::assertFalse($result['changed']);
        self::assertSame(0, $result['mutations']);
    }
}
