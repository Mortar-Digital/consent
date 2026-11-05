<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Integration;

use MortarDigital\Consent\Config;
use MortarDigital\Consent\SettingsMerger;
use MortarDigital\Consent\WordPress\ConfigProvider;
use PHPUnit\Framework\TestCase;

/**
 * Restores the plugin's settings option and the current user after each
 * test, so tests can change both freely.
 */
abstract class IntegrationTestCase extends TestCase
{
    private mixed $savedSettings;

    protected function setUp(): void
    {
        $this->savedSettings = get_option(ConfigProvider::OPTION, null);
        wp_set_current_user(0);
    }

    protected function tearDown(): void
    {
        if ($this->savedSettings === null) {
            delete_option(ConfigProvider::OPTION);
        } else {
            update_option(ConfigProvider::OPTION, $this->savedSettings);
        }

        wp_set_current_user(0);
    }

    /**
     * The default configuration with some settings replaced.
     *
     * @param array<string, mixed> $overrides
     */
    protected static function config(array $overrides = []): Config
    {
        return Config::fromArray(SettingsMerger::merge((new ConfigProvider())->defaults(), [], $overrides));
    }

    /** Captures what a callback prints. */
    protected static function captureOutput(callable $print): string
    {
        ob_start();

        try {
            $print();
        } finally {
            $output = (string) ob_get_clean();
        }

        return $output;
    }
}
