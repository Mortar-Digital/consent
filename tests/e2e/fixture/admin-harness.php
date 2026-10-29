<?php

/**
 * Run with `wp eval-file`: prints the admin screen's script/style tags and
 * the settings payload as JSON, so the e2e suite can render the real admin
 * app without signing in.
 */

wp_set_current_user(1);
remove_all_filters('mortar_consent_overrides');
wp_enqueue_script('mortar-consent-admin', WPMU_PLUGIN_URL . '/mortar-consent/assets/admin.js', ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'], (string) time(), ['in_footer' => true]);
wp_add_inline_script('mortar-consent-admin', 'window.mortarConsentAdmin = ' . wp_json_encode(['exportUrl' => '#export']) . ';', 'before');
wp_register_style('mortar-consent-admin', WPMU_PLUGIN_URL . '/mortar-consent/assets/admin.css', ['wp-components'], (string) time());
ob_start();
wp_styles()->do_items(['wp-components', 'common', 'forms', 'list-tables', 'mortar-consent-admin']);
wp_scripts()->do_items(['mortar-consent-admin']);
$tags = (string) ob_get_clean();
echo wp_json_encode([
    'tags' => $tags,
    'settings' => rest_do_request(new WP_REST_Request('GET', '/mortar-consent/v1/settings'))->get_data(),
]);
