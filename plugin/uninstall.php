<?php

/**
 * Runs when the plugin is deleted from the Plugins screen. The consent log is
 * evidence you may need to keep, so data is only removed when you opt in:
 *
 *     define('MD_CONSENT_DELETE_DATA_ON_UNINSTALL', true); // wp-config.php
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

wp_clear_scheduled_hook('md_consent_purge_log');

if (defined('MD_CONSENT_DELETE_DATA_ON_UNINSTALL') && MD_CONSENT_DELETE_DATA_ON_UNINSTALL === true) {
    global $wpdb;
    $wpdb->query($wpdb->prepare('DROP TABLE IF EXISTS %i', $wpdb->prefix . 'md_consent_log'));
    delete_option('md_consent_settings');
    delete_option('md_consent_log_schema');
    delete_option('md_consent_migrated');
}
