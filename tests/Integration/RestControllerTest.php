<?php

declare(strict_types=1);

namespace MortarDigital\Consent\Tests\Integration;

use MortarDigital\Consent\WordPress\ConfigProvider;
use MortarDigital\Consent\WordPress\ConsentLog;

/** The REST routes as registered by the running plugin, through WordPress's REST server. */
final class RestControllerTest extends IntegrationTestCase
{
    private const CONSENT_ID = '0b9d2c3e-5f60-4a71-8b82-93a4b5c6d7e8';

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearRateLimits();
    }

    protected function tearDown(): void
    {
        global $wpdb;
        $wpdb->delete((new ConsentLog())->table(), ['consent_id' => self::CONSENT_ID]);
        $this->clearRateLimits();
        parent::tearDown();
    }

    public function testValidRecordIsStored(): void
    {
        $response = $this->post(['granted' => ['analytics'], 'method' => 'custom']);

        $this->assertSame(201, $response->get_status());
        $records = (new ConsentLog())->forConsentId(self::CONSENT_ID);
        $this->assertCount(1, $records);
        $this->assertSame(['analytics'], $records[0]['granted']);
        $this->assertSame('custom', $records[0]['method']);
    }

    public function testRecordWithUnknownCategoryIsRejected(): void
    {
        $response = $this->post(['granted' => ['nope'], 'method' => 'custom']);

        $this->assertSame(400, $response->get_status());
        $this->assertSame('Unknown category.', $response->as_error()->get_error_message());
        $this->assertSame([], (new ConsentLog())->forConsentId(self::CONSENT_ID));
    }

    public function testOversizedBodyIsRejected(): void
    {
        $request = new \WP_REST_Request('POST', '/mortar-consent/v1/records');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body(str_repeat(' ', 2048) . '{}');

        $this->assertSame(413, rest_do_request($request)->get_status());
    }

    public function testRecordsListNeedsTheCapability(): void
    {
        $request = new \WP_REST_Request('GET', '/mortar-consent/v1/records');

        $this->assertSame(401, rest_do_request($request)->get_status());

        wp_set_current_user($this->administrator());
        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status());
        $this->assertArrayHasKey('summary', $response->get_data());
    }

    public function testSettingsAreSavedAndReturned(): void
    {
        wp_set_current_user($this->administrator());

        $response = $this->putSettings(['lifetime_days' => 200]);

        $this->assertSame(200, $response->get_status());
        $this->assertSame(200, $response->get_data()['effective']['lifetime_days']);
        $this->assertSame(200, get_option(ConfigProvider::OPTION)['lifetime_days']);
    }

    public function testInvalidSettingsReturnAPlainTextMessage(): void
    {
        wp_set_current_user($this->administrator());

        $response = $this->putSettings(['cookie_domain' => 'Not A Domain']);

        $this->assertSame(400, $response->get_status());
        // Escaped when thrown, decoded for JSON: quotes arrive as quotes.
        $this->assertSame('"cookie_domain" must be empty or a lowercase hostname.', $response->as_error()->get_error_message());
    }

    /** @param array<string, mixed> $fields */
    private function post(array $fields): \WP_REST_Response
    {
        $request = new \WP_REST_Request('POST', '/mortar-consent/v1/records');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode([
            'consentId' => self::CONSENT_ID,
            'policyVersion' => (new ConfigProvider())->get()->policy->version,
            ...$fields,
        ]));

        return rest_do_request($request);
    }

    /** @param array<string, mixed> $settings */
    private function putSettings(array $settings): \WP_REST_Response
    {
        $request = new \WP_REST_Request('PUT', '/mortar-consent/v1/settings');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode(['settings' => $settings]));

        return rest_do_request($request);
    }

    private function administrator(): int
    {
        $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
        $this->assertNotEmpty($admins, 'The test site needs an administrator.');

        return (int) $admins[0];
    }

    private function clearRateLimits(): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like('_transient_mortar_consent_rl_') . '%',
            $wpdb->esc_like('_transient_timeout_mortar_consent_rl_') . '%',
        ));
        wp_cache_flush();
    }
}
