<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Unit;

use MortarDigital\Consent\Config;
use MortarDigital\Consent\InvalidConfiguration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    /** @return array<string, mixed> */
    public static function valid(): array
    {
        return [
            'policy_version' => 1,
            'lifetime_days' => 365,
            'categories' => [
                ['id' => 'necessary', 'label' => 'Necessary', 'description' => 'Site essentials.', 'required' => true],
                ['id' => 'analytics', 'label' => 'Analytics', 'description' => 'Usage stats.', 'cookies' => ['_ga', '_ga_*']],
                ['id' => 'marketing', 'label' => 'Marketing', 'cookies' => [['name' => '_fbp', 'provider' => 'Meta', 'purpose' => 'Ads', 'duration' => '3 months']]],
            ],
            'scripts' => ['google-analytics' => 'analytics', 'ads' => ['analytics', 'marketing']],
            'texts' => array_fill_keys(Config::TEXT_KEYS, 'Text'),
            'privacy_policy_url' => '/privacy-policy/',
        ];
    }

    public function testBuildsFromValidArray(): void
    {
        $config = Config::fromArray(self::valid());

        self::assertSame(['analytics'], $config->scripts->categoriesFor('google-analytics'));
        self::assertSame(['analytics', 'marketing'], $config->scripts->categoriesFor('ads'));
        self::assertSame([], $config->scripts->categoriesFor('jquery'));
        self::assertTrue($config->reopenButton);
        self::assertTrue($config->logEnabled);
        self::assertSame(1095, $config->logRetentionDays);
        self::assertSame([
            'cookie' => ['name' => 'mortar_consent', 'domain' => ''],
            'policyVersion' => 1,
            'lifetime' => 365 * 86400,
            'categories' => [
                ['id' => 'necessary', 'required' => true, 'cookies' => []],
                ['id' => 'analytics', 'required' => false, 'cookies' => ['_ga', '_ga_*']],
                ['id' => 'marketing', 'required' => false, 'cookies' => ['_fbp']],
            ],
            'consentMode' => null,
            'wpConsentApi' => [],
            'gpc' => [],
        ], $config->toClientArray());
    }

    public function testPhaseThreeIntegrations(): void
    {
        $config = Config::fromArray([...self::valid(),
            'consent_mode' => ['enabled' => true, 'signals' => ['ad_storage' => 'marketing', 'security_storage' => ['necessary'], 'analytics_storage' => ['analytics']]],
            'wp_consent_api' => ['statistics' => ['analytics']],
            'embeds' => ['category' => 'marketing', 'providers' => ['vimeo' => 'analytics', 'wordpress' => '']],
            'gpc_categories' => ['marketing'],
        ]);

        self::assertSame(['ad_storage' => ['marketing'], 'security_storage' => ['necessary'], 'analytics_storage' => ['analytics']], $config->toClientArray()['consentMode']);
        self::assertSame(['statistics' => ['analytics']], $config->toClientArray()['wpConsentApi']);
        self::assertSame('marketing', $config->embedCategoryFor('youtube'));
        self::assertSame('analytics', $config->embedCategoryFor('vimeo'));
        self::assertNull($config->embedCategoryFor('wordpress'));
        self::assertSame(['marketing'], $config->gpcCategories);
    }

    public function testDisabledConsentModeIsOmittedFromClient(): void
    {
        $config = Config::fromArray([...self::valid(), 'consent_mode' => ['enabled' => false, 'signals' => ['ad_storage' => 'marketing']]]);

        self::assertNull($config->consentMode);
        self::assertNull($config->toClientArray()['consentMode']);
    }

    /** @return iterable<string, array{callable(array<string, mixed>): array<string, mixed>}> */
    public static function invalidMutations(): iterable
    {
        yield 'duplicate category' => [static fn(array $c): array => [...$c, 'categories' => [...$c['categories'], ['id' => 'analytics', 'label' => 'Again']]]];
        yield 'bad category id' => [static fn(array $c): array => [...$c, 'categories' => [['id' => 'Bad Id', 'label' => 'X']]]];
        yield 'empty label' => [static fn(array $c): array => [...$c, 'categories' => [['id' => 'x', 'label' => ' ']]]];
        yield 'bad cookie pattern' => [static fn(array $c): array => [...$c, 'categories' => [['id' => 'x', 'label' => 'X', 'cookies' => ['a;b']]]]];
        yield 'script to required category' => [static fn(array $c): array => [...$c, 'scripts' => ['x' => 'necessary']]];
        yield 'script to unknown category' => [static fn(array $c): array => [...$c, 'scripts' => ['x' => 'ads']]];
        yield 'string policy version' => [static fn(array $c): array => [...$c, 'policy_version' => '1']];
        yield 'zero lifetime' => [static fn(array $c): array => [...$c, 'lifetime_days' => 0]];
        yield 'missing text' => [static function (array $c): array {
            unset($c['texts']['reject_all']);

            return $c;
        }];
        yield 'javascript url' => [static fn(array $c): array => [...$c, 'privacy_policy_url' => 'javascript:alert(1)']];
        yield 'protocol-relative url' => [static fn(array $c): array => [...$c, 'privacy_policy_url' => '//evil.example/']];
        yield 'cookie without name' => [static fn(array $c): array => [...$c, 'categories' => [['id' => 'x', 'label' => 'X', 'cookies' => [['provider' => 'Google']]]]]];
        yield 'retention too short' => [static fn(array $c): array => [...$c, 'log_retention_days' => 7]];
        yield 'unknown consent mode signal' => [static fn(array $c): array => [...$c, 'consent_mode' => ['enabled' => false, 'signals' => ['ad_stuff' => 'marketing']]]];
        yield 'consent mode unknown category' => [static fn(array $c): array => [...$c, 'consent_mode' => ['enabled' => true, 'signals' => ['ad_storage' => 'ads']]]];
        yield 'wp consent api unknown category' => [static fn(array $c): array => [...$c, 'wp_consent_api' => ['statistics' => 'stats']]];
        yield 'embed required category' => [static fn(array $c): array => [...$c, 'embeds' => ['category' => 'necessary']]];
        yield 'embed bad provider slug' => [static fn(array $c): array => [...$c, 'embeds' => ['category' => '', 'providers' => ['You Tube' => 'marketing']]]];
        yield 'gpc required category' => [static fn(array $c): array => [...$c, 'gpc_categories' => ['necessary']]];
        yield 'bad cookie domain' => [static fn(array $c): array => [...$c, 'cookie_domain' => 'example.com; path=/']];
    }

    /** @param callable(array<string, mixed>): array<string, mixed> $mutate */
    #[DataProvider('invalidMutations')]
    public function testRejectsInvalidConfig(callable $mutate): void
    {
        $this->expectException(InvalidConfiguration::class);
        Config::fromArray($mutate(self::valid()));
    }
}
