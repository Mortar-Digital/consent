<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Integration;

use MortarDigital\Consent\ConsentRecord;
use MortarDigital\Consent\WordPress\ConsentLog;

final class ConsentLogTest extends IntegrationTestCase
{
    private const OLD_ID = '1c2d3e4f-5a6b-4c7d-8e9f-a0b1c2d3e4f5';
    private const NEW_ID = '2d3e4f5a-6b7c-4d8e-9fa0-b1c2d3e4f5a6';

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE consent_id IN (%s, %s)', (new ConsentLog())->table(), self::OLD_ID, self::NEW_ID));
        parent::tearDown();
    }

    public function testPurgeDeletesOnlyRecordsPastRetention(): void
    {
        $log = new ConsentLog();
        $this->assertTrue($log->insert($this->record(self::OLD_ID, time() - 40 * DAY_IN_SECONDS)));
        $this->assertTrue($log->insert($this->record(self::NEW_ID, time())));

        $log->purge(30);

        $this->assertSame([], $log->forConsentId(self::OLD_ID));
        $this->assertCount(1, $log->forConsentId(self::NEW_ID));
    }

    public function testCsvExportIncludesTheRecord(): void
    {
        $log = new ConsentLog();
        $log->insert($this->record(self::NEW_ID, time()));
        $csv = '';

        $log->exportCsv(static function (string $chunk) use (&$csv): void {
            $csv .= $chunk;
        });

        $this->assertStringStartsWith('"id","consent_id","policy_version","granted","method","created_at_utc"', $csv);
        $this->assertStringContainsString('"' . self::NEW_ID . '"', $csv);
        $this->assertStringContainsString('"analytics marketing"', $csv);
    }

    private function record(string $consentId, int $time): ConsentRecord
    {
        return ConsentRecord::fromPayload(
            ['consentId' => $consentId, 'policyVersion' => self::config()->policy->version, 'granted' => ['marketing', 'analytics'], 'method' => 'custom'],
            self::config()->policy,
            $time,
        );
    }
}
