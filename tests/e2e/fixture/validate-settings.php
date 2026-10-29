<?php

/**
 * Run with `wp eval-file`: sends the settings JSON on stdin through the real
 * PUT /settings route as an admin and prints the status, then restores the
 * previous settings.
 */

wp_set_current_user(1);
remove_all_filters('mortar_consent_overrides');
remove_all_filters('mortar_consent_config');
$previous = get_option('mortar_consent_settings', []);
$request = new WP_REST_Request('PUT', '/mortar-consent/v1/settings');
$request->set_header('content-type', 'application/json');
$request->set_body((string) file_get_contents('php://stdin'));
$response = rest_do_request($request);
echo wp_json_encode(['status' => $response->get_status(), 'data' => $response->get_data()]);
$previous === [] ? delete_option('mortar_consent_settings') : update_option('mortar_consent_settings', $previous);
