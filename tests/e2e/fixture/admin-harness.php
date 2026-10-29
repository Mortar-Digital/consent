<?php

/**
 * Run with `wp eval-file`: prints the admin screen's script/style tags and
 * the settings payload as JSON, so the e2e suite can render the real admin
 * app without signing in.
 */

wp_set_current_user(1);
remove_all_filters('md_consent_overrides');
wp_enqueue_script('md-consent-admin', WPMU_PLUGIN_URL . '/md-consent/assets/admin.js', ['wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n'], (string) time(), ['in_footer' => true]);
wp_add_inline_script('md-consent-admin', 'window.mdConsentAdmin = ' . wp_json_encode(['exportUrl' => '#export']) . ';', 'before');
wp_register_style('md-consent-admin', WPMU_PLUGIN_URL . '/md-consent/assets/admin.css', ['wp-components'], (string) time());
ob_start();
wp_styles()->do_items(['wp-components', 'common', 'forms', 'list-tables', 'md-consent-admin']);
wp_scripts()->do_items(['md-consent-admin']);
$tags = (string) ob_get_clean();
echo wp_json_encode([
    'tags' => $tags,
    'settings' => rest_do_request(new WP_REST_Request('GET', '/md-consent/v1/settings'))->get_data(),
]);
