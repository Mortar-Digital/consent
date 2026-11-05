<?php

/**
 * Run with `wp eval-file`: prints the admin screen's script/style tags and
 * the settings payload as JSON, so the e2e suite can render the real admin
 * app without signing in.
 */

wp_set_current_user(1);
// Regular plugin under wp-env, MU-plugin on a Bedrock site.
$regular = is_file(WP_PLUGIN_DIR . '/mortar-consent/mortar-consent.php');
$base = $regular ? plugins_url('mortar-consent') : WPMU_PLUGIN_URL . '/mortar-consent';
$asset = require ($regular ? WP_PLUGIN_DIR : WPMU_PLUGIN_DIR) . '/mortar-consent/assets/admin/index.asset.php';
remove_all_filters('mortar_consent_overrides');
wp_enqueue_script('mortar-consent-admin', $base . '/assets/admin/index.js', $asset['dependencies'], (string) time(), ['in_footer' => true]);
wp_add_inline_script('mortar-consent-admin', 'window.mortarConsentAdmin = ' . wp_json_encode(['exportUrl' => '#export']) . ';', 'before');
wp_register_style('mortar-consent-admin', $base . '/assets/admin.css', ['wp-components'], (string) time());
ob_start();
wp_styles()->do_items(['wp-components', 'common', 'forms', 'list-tables', 'mortar-consent-admin']);
wp_scripts()->do_items(['mortar-consent-admin']);
$tags = (string) ob_get_clean();
echo wp_json_encode([
    'tags' => $tags,
    'settings' => rest_do_request(new WP_REST_Request('GET', '/mortar-consent/v1/settings'))->get_data(),
]);
