<?php

declare(strict_types=1);

namespace MortarDigital\Consent;

/**
 * Combines the three config sources, lowest precedence first:
 * translated defaults, admin-saved settings, then code overrides. Keys set in
 * code are locked: the admin UI shows them read-only and saving ignores them.
 */
final class SettingsMerger
{
    public const EDITABLE_KEYS = [
        'policy_version',
        'lifetime_days',
        'categories',
        'scripts',
        'texts',
        'privacy_policy_url',
        'cookie_domain',
        'reopen_button',
        'log_enabled',
        'log_retention_days',
        'consent_mode',
        'embeds',
        'gpc_categories',
        'wp_consent_api',
    ];

    /**
     * @param array<string, mixed> $defaults
     * @param array<mixed> $stored
     * @param array<mixed> $overrides
     * @return array<string, mixed>
     */
    public static function merge(array $defaults, array $stored, array $overrides): array
    {
        $config = $defaults;
        foreach ([self::editable($stored), self::editable($overrides)] as $layer) {
            foreach ($layer as $key => $value) {
                // Texts merge per key, so unchanged copy keeps its translation.
                $config[$key] = $key === 'texts' && is_array($value) && is_array($config['texts'] ?? null)
                    ? array_merge($config['texts'], array_filter($value, static fn (mixed $t): bool => is_string($t) && trim($t) !== ''))
                    : $value;
            }
        }

        return $config;
    }

    /**
     * Reduces a submitted settings array to editable, unlocked keys. Texts
     * equal to the default are dropped so they keep following translations.
     *
     * @param array<mixed> $submitted
     * @param array<string, mixed> $defaults
     * @param list<string> $locked
     * @return array<string, mixed>
     */
    public static function storable(array $submitted, array $defaults, array $locked): array
    {
        $stored = array_diff_key(self::editable($submitted), array_flip($locked));
        // Values equal to the defaults are not stored, so untouched labels and
        // copy keep following translations and future default changes.
        $stored = array_filter(
            $stored,
            static fn (mixed $value, string $key): bool => $key === 'texts' || ! array_key_exists($key, $defaults) || $value !== $defaults[$key],
            ARRAY_FILTER_USE_BOTH,
        );
        if (isset($stored['texts'])) {
            $texts = is_array($stored['texts']) ? $stored['texts'] : [];
            $stored['texts'] = array_filter(
                array_intersect_key($texts, array_flip(Config::TEXT_KEYS)),
                static fn (mixed $t, string $key): bool => is_string($t) && trim($t) !== '' && $t !== ($defaults['texts'][$key] ?? null),
                ARRAY_FILTER_USE_BOTH,
            );
            if ($stored['texts'] === []) {
                unset($stored['texts']);
            }
        }

        return $stored;
    }

    /**
     * @param array<mixed> $values
     * @return array<string, mixed>
     */
    private static function editable(array $values): array
    {
        return array_intersect_key($values, array_flip(self::EDITABLE_KEYS));
    }
}
