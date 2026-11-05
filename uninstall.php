<?php

/**
 * Runs when the plugin is deleted from the Plugins screen. The consent log is
 * evidence you may need to keep, so data is only removed when you opt in:
 *
 *     define('MORTAR_CONSENT_DELETE_DATA_ON_UNINSTALL', true); // wp-config.php
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Cron events, options and the log table are per site, so a network
// uninstall has to visit every site.
$mortar_consent_uninstall = static function (): void {
    wp_clear_scheduled_hook('mortar_consent_purge_log');

    if (! defined('MORTAR_CONSENT_DELETE_DATA_ON_UNINSTALL') || MORTAR_CONSENT_DELETE_DATA_ON_UNINSTALL !== true) {
        return;
    }
    global $wpdb;
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'mortar_consent_log')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- dropping the plugin's own table.
    delete_option('mortar_consent_settings');
    delete_option('mortar_consent_log_schema');
};

if (is_multisite()) {
    foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $mortar_consent_site) {
        switch_to_blog((int) $mortar_consent_site);
        $mortar_consent_uninstall();
        restore_current_blog();
    }
} else {
    $mortar_consent_uninstall();
}
