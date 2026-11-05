<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Integration;

use MortarDigital\Consent\WordPress\ConfigProvider;

final class ConfigProviderTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('mortar_consent_config', 99);
        parent::tearDown();
    }

    public function testInvalidConfigurationFailsClosed(): void
    {
        add_filter('mortar_consent_config', static fn(array $config): array => [
            ...$config,
            'scripts' => ['it-analytics' => 'analytics', 'it-typo'],
            'cookie_domain' => 'Not A Domain',
            'consent_mode' => ['enabled' => true, 'signals' => []],
        ], 99);

        [$config, $warnings] = $this->withWarningsCaptured(static fn() => (new ConfigProvider())->get());

        // Every handle the bad config named stays gated, behind every optional category.
        $everyOptional = ['functional', 'analytics', 'marketing'];
        $this->assertSame($everyOptional, $config->scripts->categoriesFor('it-analytics'));
        $this->assertSame($everyOptional, $config->scripts->categoriesFor('it-typo'));
        // Consent Mode stays on, with every signal denied.
        $this->assertNotNull($config->consentMode);
        $this->assertSame([], array_filter($config->consentMode->toArray()));
        $this->assertSame('', $config->cookieDomain);

        if (WP_DEBUG) {
            $this->assertStringContainsString('invalid configuration, using defaults', implode("\n", $warnings));
        }
    }

    public function testInvalidWriteToTheOptionKeepsThePreviousValue(): void
    {
        update_option(ConfigProvider::OPTION, ['lifetime_days' => 100]);

        update_option(ConfigProvider::OPTION, ['lifetime_days' => -1]);

        $this->assertSame(['lifetime_days' => 100], get_option(ConfigProvider::OPTION));
    }

    public function testUnknownKeysAreDroppedOnWrite(): void
    {
        update_option(ConfigProvider::OPTION, ['lifetime_days' => 90, 'not_a_setting' => true]);

        $this->assertSame(['lifetime_days' => 90], get_option(ConfigProvider::OPTION));
    }

    /**
     * Runs the callback with user warnings and error_log() captured instead
     * of reaching PHPUnit or the test output.
     *
     * @template T
     * @param callable(): T $callback
     * @return array{T, list<string>}
     */
    private function withWarningsCaptured(callable $callback): array
    {
        $warnings = [];
        $errorLog = (string) ini_get('error_log');
        ini_set('error_log', '/dev/null');
        set_error_handler(static function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, E_USER_WARNING);

        try {
            $result = $callback();
        } finally {
            restore_error_handler();
            ini_set('error_log', $errorLog);
        }

        return [$result, $warnings];
    }
}
