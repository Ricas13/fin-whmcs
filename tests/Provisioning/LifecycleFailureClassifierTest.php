<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Tests\Provisioning;

use CaptainFin\Whmcs\Integrations\Jellyfin\JellyfinException;
use CaptainFin\Whmcs\Provisioning\LifecycleFailureClassifier;
use CaptainFin\Whmcs\Provisioning\ManualAttentionException;
use PHPUnit\Framework\TestCase;

final class LifecycleFailureClassifierTest extends TestCase
{
    private LifecycleFailureClassifier $classifier;

    protected function setUp(): void
    {
        $this->classifier = new LifecycleFailureClassifier();
    }

    public function testTransientJellyfinFailureRetriesQuickly(): void
    {
        $result = $this->classifier->classify(
            new JellyfinException('timeout', null, true, true)
        );

        self::assertSame(LifecycleFailureClassifier::RETRY, $result['action']);
        self::assertSame(60, $result['delay_seconds']);
    }

    public function testNonRetryableJellyfinFailureRequiresAttention(): void
    {
        $result = $this->classifier->classify(
            new JellyfinException('unauthorised', 401, false, false)
        );

        self::assertSame(LifecycleFailureClassifier::MANUAL_ATTENTION, $result['action']);
        self::assertNull($result['delay_seconds']);
    }

    public function testConfigurationFailureRequiresAttention(): void
    {
        $result = $this->classifier->classify(new \InvalidArgumentException('bad config'));

        self::assertSame(LifecycleFailureClassifier::MANUAL_ATTENTION, $result['action']);
    }

    public function testExplicitUnsafeRecoveryRequiresAttention(): void
    {
        $result = $this->classifier->classify(new ManualAttentionException('ownership ambiguous'));

        self::assertSame(LifecycleFailureClassifier::MANUAL_ATTENTION, $result['action']);
    }

    public function testLocalPersistenceOrRuntimeFailureIsRetryable(): void
    {
        $result = $this->classifier->classify(new \RuntimeException('database write failed'));

        self::assertSame(LifecycleFailureClassifier::RETRY, $result['action']);
        self::assertSame(300, $result['delay_seconds']);
    }
}
