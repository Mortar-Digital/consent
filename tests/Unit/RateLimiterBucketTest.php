<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Unit;

use PHPUnit\Framework\TestCase;
use MortarDigital\Consent\WordPress\RateLimiter;

final class RateLimiterBucketTest extends TestCase
{
    public function testIpv6AddressesShareTheirSlash64(): void
    {
        self::assertSame(RateLimiter::bucket('2001:db8:1:2::1'), RateLimiter::bucket('2001:db8:1:2:ffff:ffff:ffff:ffff'));
        self::assertNotSame(RateLimiter::bucket('2001:db8:1:2::1'), RateLimiter::bucket('2001:db8:1:3::1'));
    }

    public function testIpv4IsUnchanged(): void
    {
        self::assertSame('203.0.113.7', RateLimiter::bucket('203.0.113.7'));
    }
}
