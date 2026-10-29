<?php

declare(strict_types=1);

namespace MortarDigital\Consent\WordPress;

/**
 * One-off move from the package's old sd_ / sd-consent names: settings, the
 * consent log table, the purge event, and blocks or shortcodes saved in post
 * content. Runs once per site; a fresh install only records that it ran.
 */
final class LegacyMigration
{
    private const DONE_OPTION = 'md_consent_migrated';
    private const CONTENT_RENAMES = [
        'wp:sd-consent/' => 'wp:md-consent/',
        '[sd_cookie_' => '[md_cookie_',
        'data-sd-consent' => 'data-md-consent',
        'data-sd-type' => 'data-md-type',
    ];

    public function __construct(private readonly ConsentLog $log)
    {
    }

    public function run(): void
    {
        if (get_option(self::DONE_OPTION) === '1') {
            return;
        }
        global $wpdb;

        foreach (['sd_consent_settings' => ConfigProvider::OPTION, 'sd_consent_log_schema' => ConsentLog::SCHEMA_OPTION] as $old => $new) {
            $value = get_option($old, null);
            if ($value !== null && get_option($new, null) === null) {
                update_option($new, $value, true);
            }
            delete_option($old);
        }

        $oldTable = $wpdb->prefix . 'sd_consent_log';
        $newTable = $this->log->table();
        $hasTable = static fn (string $table): bool => $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
        if ($hasTable($oldTable) && ! $hasTable($newTable)) {
            $wpdb->query($wpdb->prepare('RENAME TABLE %i TO %i', $oldTable, $newTable));
        }

        wp_clear_scheduled_hook('sd_consent_purge_log');

        foreach (self::CONTENT_RENAMES as $from => $to) {
            $wpdb->query($wpdb->prepare(
                "UPDATE %i SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
                $wpdb->posts,
                $from,
                $to,
                '%' . $wpdb->esc_like($from) . '%',
            ));
        }
        // Post content may be in a persistent object cache; this runs once.
        wp_cache_flush();

        update_option(self::DONE_OPTION, '1', true);
    }
}
