<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Unit;

use MortarDigital\Consent\Category;
use MortarDigital\Consent\CategoryRegistry;
use MortarDigital\Consent\ConsentCookieCodec;
use MortarDigital\Consent\ConsentState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsentCookieCodecTest extends TestCase
{
    /** @return array<string, array{array<string, mixed>, CategoryRegistry}> */
    public static function fixtureCases(): array
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/cookie-codec.json'), true, flags: JSON_THROW_ON_ERROR);
        $registry = new CategoryRegistry(...array_map(
            static fn(array $c): Category => new Category($c['id'], $c['id'], '', $c['required']),
            $fixture['categories'],
        ));
        $cases = [];

        foreach ($fixture['cases'] as $case) {
            $cases[$case['name']] = [$case, $registry];
        }

        return $cases;
    }

    /** @param array<string, mixed> $case */
    #[DataProvider('fixtureCases')]
    public function testDecodesSharedFixtures(array $case, CategoryRegistry $registry): void
    {
        $state = (new ConsentCookieCodec())->decode($case['value'], $registry);

        if ($case['expect'] === null) {
            self::assertNull($state);

            return;
        }
        self::assertNotNull($state);
        self::assertSame($case['expect'], [
            'policyVersion' => $state->policyVersion,
            'timestamp' => $state->timestamp,
            'consentId' => $state->consentId,
            'granted' => $state->granted,
        ]);
    }

    public function testPatternMatchesSharedFixture(): void
    {
        $fixture = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/cookie-codec.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame($fixture['pattern'], ConsentCookieCodec::pattern());
    }

    public function testRoundTrips(): void
    {
        $registry = new CategoryRegistry(new Category('analytics', 'Analytics', ''), new Category('marketing', 'Marketing', ''));
        $codec = new ConsentCookieCodec();
        $state = new ConsentState(2, 1761480000, '3f1c2a9e-8b7d-4c2e-9f10-2a3b4c5d6e7f', ['marketing']);

        self::assertSame('1|2|1761480000|3f1c2a9e-8b7d-4c2e-9f10-2a3b4c5d6e7f|marketing', $codec->encode($state));
        self::assertEquals($state, $codec->decode($codec->encode($state), $registry));
    }

    public function testRejectsOversizedValues(): void
    {
        $registry = new CategoryRegistry(new Category('analytics', 'Analytics', ''));
        $value = '1|1|1761480000|3f1c2a9e-8b7d-4c2e-9f10-2a3b4c5d6e7f|' . implode('.', array_fill(0, 200, 'analytics'));

        self::assertNull((new ConsentCookieCodec())->decode($value, $registry));
    }
}
