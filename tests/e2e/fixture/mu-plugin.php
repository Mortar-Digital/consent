<?php

/**
 * E2E fixture for Mortar Consent. Installed into the test site by
 * tests/e2e/run.mjs and removed afterwards. Never deploy it.
 */

declare(strict_types=1);

// Bedrock sets WP_ENV; WordPress's own environment type defaults to production.
if (! defined('ABSPATH') || (defined('WP_ENV') ? WP_ENV : wp_get_environment_type()) === 'production') {
    return;
}

// Consent Mode on, via a code override (also exercises locking).
add_filter('mortar_consent_overrides', static fn(): array => ['consent_mode' => ['enabled' => true, 'signals' => [
    'ad_storage' => ['marketing'],
    'ad_user_data' => ['marketing'],
    'ad_personalization' => ['marketing'],
    'analytics_storage' => ['analytics'],
    'functionality_storage' => ['necessary'],
    'personalization_storage' => ['functional'],
    'security_storage' => ['necessary'],
]]]);

add_filter('mortar_consent_config', static function (array $config): array {
    $config['scripts']['md-test-analytics'] = 'analytics';

    foreach ($config['categories'] as &$category) {
        if ($category['id'] === 'analytics') {
            $category['cookies'] = [['name' => '_sdtest_*', 'provider' => 'Test & Co <b>', 'purpose' => 'Testing', 'duration' => 'Session']];
        }
    }

    return $config;
});

add_action('wp_enqueue_scripts', static function (): void {
    // Regular plugin under wp-env, MU-plugin on a Bedrock site.
    $src = is_file(WP_PLUGIN_DIR . '/mortar-consent/mortar-consent.php')
        ? plugins_url('mortar-consent/tests/e2e/fixture/tracker.js')
        : WPMU_PLUGIN_URL . '/mortar-consent/tests/e2e/fixture/tracker.js';
    wp_enqueue_script('md-test-analytics', $src, [], '1', ['in_footer' => true]);
    wp_add_inline_script('md-test-analytics', 'window.mdTestBefore = 1; window.mdTestLog = (window.mdTestLog||[]).concat("before");', 'before');
    wp_add_inline_script('md-test-analytics', 'window.mdTestLog = (window.mdTestLog||[]).concat("after");', 'after');
    // Depends on the gated handle, so it must inherit the gate.
    wp_enqueue_script('md-test-dependent', $src . '?dependent', ['md-test-analytics'], '1', ['in_footer' => true]);
});

add_action('wp_footer', static function (): void {
    echo '<script type="text/plain" data-mortar-consent="marketing">window.mdTestLog = (window.mdTestLog||[]).concat("marketing-inline");</script>';
}, 50);
