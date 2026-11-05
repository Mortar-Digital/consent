<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Unit;

use MortarDigital\Consent\Category;
use MortarDigital\Consent\CategoryRegistry;
use MortarDigital\Consent\ConsentPolicy;
use MortarDigital\Consent\ConsentRecord;
use MortarDigital\Consent\InvalidRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsentRecordTest extends TestCase
{
    private const ID = '3f1c2a9e-8b7d-4c2e-9f10-2a3b4c5d6e7f';

    private static function policy(): ConsentPolicy
    {
        return new ConsentPolicy(new CategoryRegistry(
            new Category('necessary', 'Necessary', '', true),
            new Category('analytics', 'Analytics', ''),
            new Category('marketing', 'Marketing', ''),
        ), 3, 365);
    }

    public function testAcceptsValidPayloadAndNormalisesOrder(): void
    {
        $record = ConsentRecord::fromPayload(
            ['consentId' => self::ID, 'policyVersion' => 3, 'granted' => ['marketing', 'analytics', 'marketing'], 'method' => 'custom'],
            self::policy(),
            1761480000,
        );

        self::assertSame(self::ID, $record->consentId);
        self::assertSame(['analytics', 'marketing'], $record->granted);
        self::assertSame('custom', $record->method);
        self::assertSame(1761480000, $record->timestamp, 'server time, never client time');
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidPayloads(): iterable
    {
        $valid = ['consentId' => self::ID, 'policyVersion' => 3, 'granted' => [], 'method' => 'reject-all'];
        yield 'extra field' => [[...$valid, 'ip' => '1.2.3.4']];
        yield 'bad id' => [[...$valid, 'consentId' => 'abc']];
        yield 'stale policy' => [[...$valid, 'policyVersion' => 2]];
        yield 'string policy' => [[...$valid, 'policyVersion' => '3']];
        yield 'required category' => [[...$valid, 'granted' => ['necessary']]];
        yield 'unknown category' => [[...$valid, 'granted' => ['ads']]];
        yield 'granted map' => [[...$valid, 'granted' => ['a' => 'analytics']]];
        yield 'bad method' => [[...$valid, 'method' => 'gpc']];
        yield 'missing method' => [array_diff_key($valid, ['method' => true])];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidPayloads(array $payload): void
    {
        $this->expectException(InvalidRecord::class);
        ConsentRecord::fromPayload($payload, self::policy(), 1761480000);
    }
}
