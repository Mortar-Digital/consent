<?php

declare(strict_types=1);

namespace MortarDigital\Consent\WordPress;

use MortarDigital\Consent\Config;
use MortarDigital\Consent\ConsentRecord;
use MortarDigital\Consent\ConsentState;
use MortarDigital\Consent\InvalidConfiguration;
use MortarDigital\Consent\InvalidRecord;
use MortarDigital\Consent\SettingsMerger;

/**
 * REST routes under mortar-consent/v1:
 *
 * - POST /records: public, records a visitor's choice (strictly validated, rate limited).
 * - GET  /records: admin, summary, recent records or lookup by consent id.
 * - GET|PUT /settings: admin, the settings screen's data source.
 */
final class RestController
{
    public const NAMESPACE = 'mortar-consent/v1';
    private const MAX_BODY_BYTES = 1024;

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly ConsentLog $log,
        private readonly RateLimiter $limiter,
    ) {
    }

    public static function capability(): string
    {
        return (string) apply_filters('mortar_consent_capability', 'manage_options');
    }

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, '/records', [
            [
                'methods' => 'POST',
                'callback' => $this->createRecord(...),
                // Visitors are anonymous; abuse is limited by validation and rate limiting.
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => 'GET',
                'callback' => $this->listRecords(...),
                'permission_callback' => $this->canManage(...),
                'args' => [
                    'consent_id' => [
                        'type' => 'string',
                        'pattern' => '^' . ConsentState::UUID_PATTERN . '$',
                    ],
                ],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/settings', [
            [
                'methods' => 'GET',
                'callback' => fn (): \WP_REST_Response => new \WP_REST_Response($this->settingsPayload()),
                'permission_callback' => $this->canManage(...),
            ],
            [
                'methods' => 'PUT',
                'callback' => $this->updateSettings(...),
                'permission_callback' => $this->canManage(...),
                'args' => [
                    'settings' => ['type' => 'object', 'required' => true],
                ],
            ],
        ]);
    }

    public function canManage(): bool
    {
        return current_user_can(self::capability());
    }

    public function createRecord(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $config = $this->configProvider->get();
        if (! $config->logEnabled) {
            return new \WP_Error('mortar_consent_log_disabled', 'Consent logging is disabled.', ['status' => 404]);
        }

        if (strlen($request->get_body()) > self::MAX_BODY_BYTES) {
            return new \WP_Error('mortar_consent_too_large', 'Payload too large.', ['status' => 413]);
        }

        $payload = $request->get_json_params();
        if (! is_array($payload)) {
            return new \WP_Error('mortar_consent_invalid', 'Expected a JSON object.', ['status' => 400]);
        }

        if (! $this->limiter->attempt(RateLimiter::clientIp())) {
            return new \WP_Error('mortar_consent_rate_limited', 'Too many requests.', ['status' => 429]);
        }

        try {
            $record = ConsentRecord::fromPayload($payload, $config->policy, time());
        } catch (InvalidRecord $e) {
            return new \WP_Error('mortar_consent_invalid', wp_specialchars_decode($e->getMessage(), ENT_QUOTES), ['status' => 400]);
        }

        if (! $this->log->insert($record)) {
            return new \WP_Error('mortar_consent_storage', 'Could not store the record.', ['status' => 500]);
        }

        return new \WP_REST_Response(['recorded' => true], 201);
    }

    public function listRecords(\WP_REST_Request $request): \WP_REST_Response
    {
        $consentId = $request->get_param('consent_id');

        return new \WP_REST_Response([
            'summary' => $this->log->summary(30),
            'records' => is_string($consentId) && $consentId !== '' ? $this->log->forConsentId($consentId) : $this->log->recent(50),
        ]);
    }

    public function updateSettings(\WP_REST_Request $request): \WP_REST_Response|\WP_Error
    {
        $settings = $request->get_param('settings');

        try {
            $this->configProvider->save(is_array($settings) ? $settings : []);
        } catch (InvalidConfiguration $e) {
            return new \WP_Error('mortar_consent_invalid_settings', wp_specialchars_decode($e->getMessage(), ENT_QUOTES), ['status' => 400]);
        }

        return new \WP_REST_Response($this->settingsPayload());
    }

    /** @return array<string, mixed> */
    private function settingsPayload(): array
    {
        $defaults = $this->configProvider->defaults();
        $stored = $this->configProvider->stored();
        $overrides = $this->configProvider->overrides();

        return [
            'effective' => SettingsMerger::merge($defaults, $stored, $overrides),
            'defaults' => $defaults,
            'locked' => array_keys($overrides),
            'textKeys' => Config::TEXT_KEYS,
        ];
    }
}
