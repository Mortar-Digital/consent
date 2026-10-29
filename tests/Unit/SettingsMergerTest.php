<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Unit;

use PHPUnit\Framework\TestCase;
use MortarDigital\Consent\Config;
use MortarDigital\Consent\SettingsMerger;

final class SettingsMergerTest extends TestCase
{
    public function testLaterLayersWinAndTextsMergePerKey(): void
    {
        $defaults = ConfigTest::valid();
        $merged = SettingsMerger::merge(
            $defaults,
            ['lifetime_days' => 180, 'texts' => ['accept_all' => 'Yes please', 'reject_all' => '  '], 'unknown' => 1],
            ['lifetime_days' => 90, 'scripts' => ['ga' => 'analytics']],
        );

        self::assertSame(90, $merged['lifetime_days']);
        self::assertSame(['ga' => 'analytics'], $merged['scripts']);
        self::assertSame('Yes please', $merged['texts']['accept_all']);
        self::assertSame('Text', $merged['texts']['reject_all'], 'blank text falls back to default');
        self::assertArrayNotHasKey('unknown', $merged);
        Config::fromArray($merged);
    }

    public function testStorableDropsLockedKeysAndDefaultTexts(): void
    {
        $defaults = ConfigTest::valid();
        $stored = SettingsMerger::storable(
            ['policy_version' => 4, 'scripts' => ['x' => 'analytics'], 'texts' => ['accept_all' => 'Text', 'reject_all' => 'No thanks', 'bogus' => 'x'], 'evil' => true],
            $defaults,
            ['scripts'],
        );

        self::assertSame(['policy_version' => 4, 'texts' => ['reject_all' => 'No thanks']], $stored);
    }

    public function testStorableDropsValuesEqualToDefaults(): void
    {
        $defaults = ConfigTest::valid();
        $stored = SettingsMerger::storable(
            ['categories' => $defaults['categories'], 'lifetime_days' => 365, 'texts' => $defaults['texts'], 'reopen_button' => false],
            $defaults,
            [],
        );

        self::assertSame(['reopen_button' => false], $stored);
    }
}
