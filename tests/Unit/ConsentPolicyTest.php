<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Unit;

use MortarDigital\Consent\Category;
use MortarDigital\Consent\CategoryRegistry;
use MortarDigital\Consent\ConsentPolicy;
use MortarDigital\Consent\ConsentState;
use MortarDigital\Consent\InvalidConfiguration;
use PHPUnit\Framework\TestCase;

final class ConsentPolicyTest extends TestCase
{
    private const NOW = 1761480000;
    private const ID = '3f1c2a9e-8b7d-4c2e-9f10-2a3b4c5d6e7f';

    private ConsentPolicy $policy;

    protected function setUp(): void
    {
        $this->policy = new ConsentPolicy(new CategoryRegistry(
            new Category('necessary', 'Necessary', '', true),
            new Category('analytics', 'Analytics', ''),
        ), 2, 180);
    }

    public function testRequiredCategoriesAreAlwaysAllowed(): void
    {
        self::assertTrue($this->policy->allows(null, 'necessary', self::NOW));
    }

    public function testOptionalCategoriesNeedConsent(): void
    {
        self::assertFalse($this->policy->allows(null, 'analytics', self::NOW));
        self::assertFalse($this->policy->allows($this->state([]), 'analytics', self::NOW));
        self::assertTrue($this->policy->allows($this->state(['analytics']), 'analytics', self::NOW));
    }

    public function testUnknownCategoriesAreDenied(): void
    {
        self::assertFalse($this->policy->allows($this->state(['analytics']), 'marketing', self::NOW));
    }

    public function testPolicyVersionBumpInvalidatesConsent(): void
    {
        $old = new ConsentState(1, self::NOW, self::ID, ['analytics']);

        self::assertFalse($this->policy->isCurrent($old, self::NOW));
        self::assertFalse($this->policy->allows($old, 'analytics', self::NOW));
    }

    public function testConsentExpiresAfterLifetime(): void
    {
        $state = $this->state(['analytics']);

        self::assertTrue($this->policy->isCurrent($state, self::NOW + 180 * 86400));
        self::assertFalse($this->policy->isCurrent($state, self::NOW + 180 * 86400 + 1));
    }

    public function testFutureTimestampsBeyondSkewAreRejected(): void
    {
        self::assertTrue($this->policy->isCurrent($this->state([], self::NOW + 300), self::NOW));
        self::assertFalse($this->policy->isCurrent($this->state([], self::NOW + 301), self::NOW));
    }

    public function testLifetimeIsCapped(): void
    {
        $this->expectException(InvalidConfiguration::class);

        new ConsentPolicy($this->policy->categories, 1, ConsentPolicy::MAX_LIFETIME_DAYS + 1);
    }

    /** @param list<string> $granted */
    private function state(array $granted, int $timestamp = self::NOW): ConsentState
    {
        return new ConsentState(2, $timestamp, self::ID, $granted);
    }
}
