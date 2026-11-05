<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Unit;

use MortarDigital\Consent\Category;
use MortarDigital\Consent\CategoryRegistry;
use MortarDigital\Consent\SignalMap;
use PHPUnit\Framework\TestCase;

final class SignalMapTest extends TestCase
{
    public function testGrantsOnlyWhenEveryListedCategoryIsAllowed(): void
    {
        $registry = new CategoryRegistry(new Category('necessary', 'N', '', true), new Category('analytics', 'A', ''), new Category('marketing', 'M', ''));
        $map = new SignalMap([
            'security_storage' => 'necessary',
            'analytics_storage' => ['analytics'],
            'ad_storage' => ['analytics', 'marketing'],
            'ad_user_data' => [],
        ], $registry, ['security_storage', 'analytics_storage', 'ad_storage', 'ad_user_data'], 'Test');

        $allowed = ['necessary' => true, 'analytics' => true, 'marketing' => false];

        self::assertSame(
            ['security_storage' => true, 'analytics_storage' => true, 'ad_storage' => false, 'ad_user_data' => false],
            $map->resolve(static fn(string $id): bool => $allowed[$id]),
        );
    }
}
