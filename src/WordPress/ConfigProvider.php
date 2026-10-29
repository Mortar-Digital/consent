<?php

declare(strict_types=1);

namespace MortarDigital\Consent\WordPress;

use MortarDigital\Consent\Category;
use MortarDigital\Consent\Config;
use MortarDigital\Consent\InvalidConfiguration;
use MortarDigital\Consent\SettingsMerger;

/**
 * Builds the site config from, lowest precedence first: translated defaults,
 * settings saved in the admin screen, and code overrides
 * (`md_consent_overrides`, which also lock those keys in the UI). The
 * `md_consent_config` filter runs last as an escape hatch.
 */
final class ConfigProvider
{
    public const OPTION = 'md_consent_settings';

    private ?Config $config = null;

    public function get(): Config
    {
        if ($this->config !== null) {
            return $this->config;
        }
        $defaults = $this->defaults();
        $merged = SettingsMerger::merge($defaults, $this->stored(), $this->overrides());

        /**
         * Final say over the configuration. Changes made here are not shown
         * in the admin screen; prefer `md_consent_overrides`.
         *
         * @param array<string, mixed> $config
         */
        $filtered = apply_filters('md_consent_config', $merged);

        try {
            $this->config = Config::fromArray(is_array($filtered) ? $filtered : []);
        } catch (InvalidConfiguration $e) {
            $this->config = self::failClosed($defaults, $filtered, $e);
        }

        return $this->config;
    }

    /** @return array<string, mixed> */
    public function stored(): array
    {
        $stored = get_option(self::OPTION, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * Code-level settings, e.g. from a site MU-plugin or theme:
     *
     *     add_filter('md_consent_overrides', fn () => ['scripts' => ['ga' => 'analytics']]);
     *
     * @return array<string, mixed>
     */
    public function overrides(): array
    {
        $overrides = apply_filters('md_consent_overrides', []);

        return is_array($overrides) ? array_intersect_key($overrides, array_flip(SettingsMerger::EDITABLE_KEYS)) : [];
    }

    /** @return list<string> */
    public function lockedKeys(): array
    {
        return array_keys($this->overrides());
    }

    /**
     * Validates and stores settings submitted from the admin screen.
     *
     * @param array<mixed> $submitted
     * @return array<string, mixed> what was stored
     * @throws InvalidConfiguration
     */
    public function save(array $submitted): array
    {
        $defaults = $this->defaults();
        $locked = $this->lockedKeys();
        // Locked keys keep whatever was saved before, so removing the code
        // override later restores the admin's value rather than the default.
        $stored = [
            ...array_intersect_key($this->stored(), array_flip($locked)),
            ...SettingsMerger::storable($submitted, $defaults, $locked),
        ];
        Config::fromArray(SettingsMerger::merge($defaults, $stored, $this->overrides()));

        update_option(self::OPTION, $stored, true);
        $this->config = null;

        return $stored;
    }

    /**
     * Sanitises writes that bypass the admin screen (WP-CLI, other code):
     * anything that would produce an invalid config keeps the old value.
     *
     * @return array<mixed>
     */
    public function sanitizeOption(mixed $value): array
    {
        $old = $this->stored();
        if (! is_array($value)) {
            return $old;
        }
        $value = array_intersect_key($value, array_flip(SettingsMerger::EDITABLE_KEYS));
        try {
            Config::fromArray(SettingsMerger::merge($this->defaults(), $value, $this->overrides()));
        } catch (InvalidConfiguration $e) {
            if (function_exists('add_settings_error')) {
                add_settings_error(self::OPTION, 'invalid', $e->getMessage());
            }

            return $old;
        }

        return $value;
    }

    /**
     * Translated defaults, with categories in their normalised array shape so
     * values submitted from the admin screen compare equal when unchanged.
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $defaults = self::rawDefaults();
        $defaults['categories'] = array_map(
            static fn (array $c): array => Category::fromArray($c)->toArray(),
            $defaults['categories'],
        );

        return $defaults;
    }

    /** @return array<string, mixed> */
    private static function rawDefaults(): array
    {
        return [
            'policy_version' => 1,
            'lifetime_days' => 365,
            'categories' => [
                [
                    'id' => 'necessary',
                    'label' => __('Strictly necessary', 'md-consent'),
                    'description' => __('Needed for the site to work, for example keeping it secure and remembering your cookie choices. These cannot be switched off.', 'md-consent'),
                    'required' => true,
                    'cookies' => [[
                        'name' => 'md_consent',
                        'provider' => (string) wp_parse_url(home_url(), PHP_URL_HOST),
                        'purpose' => __('Remembers your cookie choices.', 'md-consent'),
                        'duration' => __('Until your consent expires', 'md-consent'),
                    ]],
                ],
                [
                    'id' => 'functional',
                    'label' => __('Functional', 'md-consent'),
                    'description' => __('Remember preferences you set and enable embedded content such as videos and maps.', 'md-consent'),
                ],
                [
                    'id' => 'analytics',
                    'label' => __('Analytics', 'md-consent'),
                    'description' => __('Help us understand how visitors use the site so we can improve it.', 'md-consent'),
                ],
                [
                    'id' => 'marketing',
                    'label' => __('Marketing', 'md-consent'),
                    'description' => __('Used to show relevant adverts and measure campaigns, sometimes by third parties.', 'md-consent'),
                ],
            ],
            'scripts' => [],
            'texts' => [
                'banner_title' => __('Cookies on this site', 'md-consent'),
                'banner_body' => __('We use necessary cookies to make this site work. With your permission we would also like to set optional cookies to understand how the site is used and to improve it. Optional cookies are only set if you allow them.', 'md-consent'),
                'accept_all' => __('Accept all', 'md-consent'),
                'reject_all' => __('Reject all', 'md-consent'),
                'manage' => __('Manage preferences', 'md-consent'),
                'dialog_title' => __('Cookie preferences', 'md-consent'),
                'dialog_body' => __('Choose which optional cookies you allow. You can change your mind at any time from the cookie settings link.', 'md-consent'),
                'save' => __('Save choices', 'md-consent'),
                'close' => __('Close', 'md-consent'),
                'always_active' => __('Always on', 'md-consent'),
                'privacy_link' => __('Read our cookie policy', 'md-consent'),
                'settings_button' => __('Cookie settings', 'md-consent'),
                'consent_id' => __('Your consent ID', 'md-consent'),
                /* translators: %s: provider name, e.g. YouTube */
                'embed_notice' => __('This content is hosted by %s, which may set cookies.', 'md-consent'),
                'embed_load' => __('Load this content', 'md-consent'),
                /* translators: %s: cookie category name, e.g. Functional */
                'embed_allow' => __('Always allow %s', 'md-consent'),
                /* translators: %s: cookie category names, e.g. Marketing */
                'gpc_notice' => __('Your browser is sending a Global Privacy Control signal, so %s stays off unless you switch it on yourself.', 'md-consent'),
            ],
            'privacy_policy_url' => (string) get_privacy_policy_url(),
            'cookie_domain' => '',
            'reopen_button' => true,
            'log_enabled' => true,
            'log_retention_days' => 1095,
            'consent_mode' => [
                'enabled' => false,
                'signals' => [
                    'ad_storage' => ['marketing'],
                    'ad_user_data' => ['marketing'],
                    'ad_personalization' => ['marketing'],
                    'analytics_storage' => ['analytics'],
                    'functionality_storage' => ['necessary'],
                    'personalization_storage' => ['functional'],
                    'security_storage' => ['necessary'],
                ],
            ],
            'embeds' => ['category' => 'functional', 'providers' => []],
            'gpc_categories' => ['marketing'],
            'wp_consent_api' => [
                'functional' => ['necessary'],
                'preferences' => ['functional'],
                'statistics' => ['analytics'],
                'statistics-anonymous' => ['analytics'],
                'marketing' => ['marketing'],
            ],
        ];
    }

    /**
     * An invalid config must never let mapped scripts run unchecked: fall
     * back to the defaults, but keep every handle the config tried to gate,
     * requiring consent to all optional categories.
     *
     * @param array<string, mixed> $defaults
     */
    private static function failClosed(array $defaults, mixed $filtered, InvalidConfiguration $error): Config
    {
        $message = 'md-consent: invalid configuration, using defaults: ' . $error->getMessage();
        if (function_exists('wp_trigger_error')) {
            wp_trigger_error(__METHOD__, $message, E_USER_WARNING);
        }
        error_log($message);

        $optional = array_values(array_map(
            static fn (array $c): string => $c['id'],
            array_filter($defaults['categories'], static fn (array $c): bool => empty($c['required'])),
        ));
        $scripts = is_array($filtered) && is_array($filtered['scripts'] ?? null) ? $filtered['scripts'] : [];
        foreach ($scripts as $key => $value) {
            // Gate both map keys and list-shaped typos such as ['google-analytics'].
            foreach ([$key, $value] as $handle) {
                if (is_string($handle) && $handle !== '' && strlen($handle) <= 200 && ! in_array($handle, $optional, true)) {
                    $defaults['scripts'][$handle] = $optional;
                }
            }
        }

        // Dropping Consent Mode would leave ungated Google tags with no
        // "denied" default, which Google treats as granted. Keep it on with
        // every signal denied.
        $consentMode = is_array($filtered) && is_array($filtered['consent_mode'] ?? null) ? $filtered['consent_mode'] : [];
        if (($consentMode['enabled'] ?? false) === true) {
            $defaults['consent_mode'] = [
                'enabled' => true,
                'signals' => array_fill_keys(Config::CONSENT_MODE_SIGNALS, []),
            ];
        }

        return Config::fromArray($defaults);
    }
}
