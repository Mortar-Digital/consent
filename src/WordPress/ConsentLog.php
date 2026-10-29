<?php

declare(strict_types=1);

namespace MortarDigital\Consent\WordPress;

use MortarDigital\Consent\ConsentRecord;
use MortarDigital\Consent\Csv;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- the plugin's own table, which no WordPress API covers; rows are written once and read only on admin screens, so there is nothing to cache.

/**
 * Storage for consent records in a dedicated table. All queries go through
 * $wpdb->prepare(); rows hold no IP address, user agent or user id.
 */
final class ConsentLog
{
    private const SCHEMA_VERSION = '1';
    public const SCHEMA_OPTION = 'md_consent_log_schema';
    private const PURGE_BATCH = 5000;

    public function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'md_consent_log';
    }

    /** Creates or upgrades the table; cheap no-op once the schema is current. */
    public function install(): void
    {
        if (get_option(self::SCHEMA_OPTION) === self::SCHEMA_VERSION) {
            return;
        }
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta(sprintf(
            "CREATE TABLE %s (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  consent_id char(36) NOT NULL,
  policy_version int(10) unsigned NOT NULL,
  granted varchar(1024) NOT NULL DEFAULT '',
  method varchar(16) NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY consent_id (consent_id),
  KEY created_at (created_at)
) %s;",
            $this->table(),
            $wpdb->get_charset_collate(),
        ));
        update_option(self::SCHEMA_OPTION, self::SCHEMA_VERSION, true);
    }

    public function insert(ConsentRecord $record): bool
    {
        global $wpdb;

        return $wpdb->insert($this->table(), [
            'consent_id' => $record->consentId,
            'policy_version' => $record->policyVersion,
            'granted' => implode('.', $record->granted),
            'method' => $record->method,
            'created_at' => gmdate('Y-m-d H:i:s', $record->timestamp),
        ], ['%s', '%d', '%s', '%s', '%s']) === 1;
    }

    /** Deletes records older than the retention period, in bounded batches. */
    public function purge(int $retentionDays): int
    {
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time() - $retentionDays * 86400);
        $deleted = 0;
        do {
            $batch = (int) $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE created_at < %s LIMIT %d', $this->table(), $cutoff, self::PURGE_BATCH));
            $deleted += $batch;
        } while ($batch === self::PURGE_BATCH);

        return $deleted;
    }

    /** @return list<array<string, mixed>> */
    public function forConsentId(string $consentId): array
    {
        global $wpdb;

        return $this->rows($wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE consent_id = %s ORDER BY id DESC LIMIT 100', $this->table(), $consentId), ARRAY_A));
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 50): array
    {
        global $wpdb;

        return $this->rows($wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id DESC LIMIT %d', $this->table(), $limit), ARRAY_A));
    }

    /** @return array<string, int> method => count over the last $days days */
    public function summary(int $days): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT method, COUNT(*) AS total FROM %i WHERE created_at >= %s GROUP BY method',
            $this->table(),
            gmdate('Y-m-d H:i:s', time() - $days * 86400),
        ), ARRAY_A);
        $summary = array_fill_keys(ConsentRecord::METHODS, 0);
        foreach ($rows ?: [] as $row) {
            $summary[(string) $row['method']] = (int) $row['total'];
        }

        return $summary;
    }

    /** Streams every record as CSV, oldest first, without loading the table into memory. */
    public function exportCsv(callable $write): void
    {
        global $wpdb;
        $write(Csv::row(['id', 'consent_id', 'policy_version', 'granted', 'method', 'created_at_utc']));
        $after = 0;
        do {
            $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE id > %d ORDER BY id ASC LIMIT 1000', $this->table(), $after), ARRAY_A) ?: [];
            foreach ($rows as $row) {
                $write(Csv::row([$row['id'], $row['consent_id'], $row['policy_version'], str_replace('.', ' ', (string) $row['granted']), $row['method'], $row['created_at']]));
                $after = (int) $row['id'];
            }
        } while (count($rows) === 1000);
    }

    /**
     * @param mixed $rows
     * @return list<array<string, mixed>>
     */
    private function rows(mixed $rows): array
    {
        return array_values(array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'consentId' => (string) $r['consent_id'],
            'policyVersion' => (int) $r['policy_version'],
            'granted' => $r['granted'] === '' ? [] : explode('.', (string) $r['granted']),
            'method' => (string) $r['method'],
            'createdAt' => str_replace(' ', 'T', (string) $r['created_at']) . 'Z',
        ], array_filter(is_array($rows) ? $rows : [], is_array(...))));
    }
}
