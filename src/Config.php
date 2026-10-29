<?php

declare(strict_types=1);

namespace MortarDigital\Consent;

/**
 * Validated site configuration. Built from a plain array so the source
 * (defaults, admin settings, code overrides) stays a WordPress concern.
 */
final class Config
{
    public const TEXT_KEYS = [
        'banner_title',
        'banner_body',
        'accept_all',
        'reject_all',
        'manage',
        'dialog_title',
        'dialog_body',
        'save',
        'close',
        'always_active',
        'privacy_link',
        'settings_button',
        'consent_id',
        'embed_notice',
        'embed_load',
        'embed_allow',
        'gpc_notice',
    ];

    /** Google Consent Mode v2 signals. */
    public const CONSENT_MODE_SIGNALS = [
        'ad_storage',
        'ad_user_data',
        'ad_personalization',
        'analytics_storage',
        'functionality_storage',
        'personalization_storage',
        'security_storage',
    ];

    /** Categories defined by the WP Consent API plugin. */
    public const WP_CONSENT_API_CATEGORIES = [
        'functional',
        'preferences',
        'statistics',
        'statistics-anonymous',
        'marketing',
    ];

    public const MIN_RETENTION_DAYS = 30;
    public const MAX_RETENTION_DAYS = 3650;

    private const MAX_TEXT_LENGTH = 2000;

    /** @param array<string, string> $texts */
    private function __construct(
        public readonly ConsentPolicy $policy,
        public readonly ScriptMap $scripts,
        public readonly array $texts,
        public readonly string $privacyPolicyUrl,
        public readonly string $cookieDomain,
        public readonly bool $reopenButton,
        public readonly bool $logEnabled,
        public readonly int $logRetentionDays,
        public readonly ?SignalMap $consentMode,
        public readonly SignalMap $wpConsentApi,
        public readonly string $embedCategory,
        /** @var array<string, string> provider slug => category id ('' = never gate) */
        public readonly array $embedProviders,
        /** @var list<string> */
        public readonly array $gpcCategories,
    ) {
    }

    /** @param array<mixed> $data */
    public static function fromArray(array $data): self
    {
        $categories = $data['categories'] ?? null;
        if (! is_array($categories) || ! array_is_list($categories)) {
            throw new InvalidConfiguration('"categories" must be a list.');
        }
        $registry = new CategoryRegistry(...array_map(
            static fn (mixed $c): Category => is_array($c) ? Category::fromArray($c) : throw new InvalidConfiguration('Each category must be an array.'),
            $categories,
        ));

        $version = $data['policy_version'] ?? null;
        $lifetime = $data['lifetime_days'] ?? null;
        if (! is_int($version) || ! is_int($lifetime)) {
            throw new InvalidConfiguration('"policy_version" and "lifetime_days" must be integers.');
        }
        $policy = new ConsentPolicy($registry, $version, $lifetime);

        $scripts = $data['scripts'] ?? [];
        if (! is_array($scripts)) {
            throw new InvalidConfiguration('"scripts" must be a map of handle => category.');
        }

        $texts = $data['texts'] ?? null;
        if (! is_array($texts)) {
            throw new InvalidConfiguration('"texts" must be an array.');
        }
        $cleanTexts = [];
        foreach (self::TEXT_KEYS as $key) {
            $text = $texts[$key] ?? null;
            if (! is_string($text) || trim($text) === '' || strlen($text) > self::MAX_TEXT_LENGTH) {
                throw new InvalidConfiguration(esc_html(sprintf('Text "%s" must be a non-empty string under %d bytes.', $key, self::MAX_TEXT_LENGTH)));
            }
            $cleanTexts[$key] = $text;
        }

        $privacyUrl = $data['privacy_policy_url'] ?? '';
        if (! is_string($privacyUrl) || ($privacyUrl !== '' && ! self::isSafeUrl($privacyUrl))) {
            throw new InvalidConfiguration('"privacy_policy_url" must be an http(s) URL or a site-relative path.');
        }

        $domain = $data['cookie_domain'] ?? '';
        if (! is_string($domain) || ($domain !== '' && preg_match('/^\.?(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/D', $domain) !== 1)) {
            throw new InvalidConfiguration('"cookie_domain" must be empty or a lowercase hostname.');
        }

        $reopen = $data['reopen_button'] ?? true;
        if (! is_bool($reopen)) {
            throw new InvalidConfiguration('"reopen_button" must be a boolean.');
        }

        $logEnabled = $data['log_enabled'] ?? true;
        $retention = $data['log_retention_days'] ?? 1095;
        if (! is_bool($logEnabled) || ! is_int($retention) || $retention < self::MIN_RETENTION_DAYS || $retention > self::MAX_RETENTION_DAYS) {
            throw new InvalidConfiguration(esc_html(sprintf('"log_enabled" must be a boolean and "log_retention_days" between %d and %d.', self::MIN_RETENTION_DAYS, self::MAX_RETENTION_DAYS)));
        }

        $consentMode = $data['consent_mode'] ?? ['enabled' => false, 'signals' => []];
        if (! is_array($consentMode) || ! is_bool($consentMode['enabled'] ?? null) || ! is_array($consentMode['signals'] ?? null)) {
            throw new InvalidConfiguration('"consent_mode" needs a boolean "enabled" and a "signals" map.');
        }
        // Validated even when disabled, so turning it on later cannot fail.
        $signals = new SignalMap($consentMode['signals'], $registry, self::CONSENT_MODE_SIGNALS, 'Consent Mode');

        $wpConsentApi = $data['wp_consent_api'] ?? [];
        if (! is_array($wpConsentApi)) {
            throw new InvalidConfiguration('"wp_consent_api" must be a map of WP Consent API category => category ids.');
        }

        [$embedCategory, $embedProviders] = self::embeds($data['embeds'] ?? ['category' => '', 'providers' => []], $registry);

        $gpc = $data['gpc_categories'] ?? [];
        if (! is_array($gpc) || ! array_is_list($gpc)) {
            throw new InvalidConfiguration('"gpc_categories" must be a list of category ids.');
        }
        foreach ($gpc as $id) {
            if (! is_string($id) || ! $registry->isOptional($id)) {
                throw new InvalidConfiguration(esc_html(sprintf('"gpc_categories" refers to "%s", which is not an optional category.', is_string($id) ? $id : gettype($id))));
            }
        }

        return new self(
            $policy,
            new ScriptMap($scripts, $registry),
            $cleanTexts,
            $privacyUrl,
            $domain,
            $reopen,
            $logEnabled,
            $retention,
            $consentMode['enabled'] ? $signals : null,
            new SignalMap($wpConsentApi, $registry, self::WP_CONSENT_API_CATEGORIES, 'WP Consent API'),
            $embedCategory,
            $embedProviders,
            array_values(array_unique($gpc)),
        );
    }

    /** The category an embed from this provider needs, or null to leave it alone. */
    public function embedCategoryFor(string $provider): ?string
    {
        $category = $this->embedProviders[$provider] ?? $this->embedCategory;

        return $category === '' ? null : $category;
    }

    /**
     * @return array{string, array<string, string>}
     */
    private static function embeds(mixed $embeds, CategoryRegistry $registry): array
    {
        if (! is_array($embeds) || ! is_string($embeds['category'] ?? null) || ! is_array($embeds['providers'] ?? [])) {
            throw new InvalidConfiguration('"embeds" needs a "category" string and an optional "providers" map.');
        }
        $check = static function (string $id, string $what) use ($registry): void {
            if ($id !== '' && ! $registry->isOptional($id)) {
                throw new InvalidConfiguration(esc_html(sprintf('%s refers to "%s", which is not an optional category.', $what, $id)));
            }
        };
        $check($embeds['category'], 'The embed category');
        $providers = [];
        foreach ($embeds['providers'] ?? [] as $provider => $id) {
            if (! is_string($provider) || preg_match('/^[a-z0-9-]{1,40}$/D', $provider) !== 1 || ! is_string($id)) {
                throw new InvalidConfiguration('Embed providers must map a provider slug to a category id (or "" to never gate it).');
            }
            $check($id, sprintf('Embed provider "%s"', $provider));
            $providers[$provider] = $id;
        }

        return [$embeds['category'], $providers];
    }

    /**
     * The subset the browser runtime needs. Labels and copy are rendered
     * server-side, so they are not duplicated here.
     *
     * @return array<string, mixed>
     */
    public function toClientArray(): array
    {
        return [
            'cookie' => [
                'name' => ConsentCookieCodec::COOKIE_NAME,
                'domain' => $this->cookieDomain,
            ],
            'policyVersion' => $this->policy->version,
            'lifetime' => $this->policy->lifetimeSeconds(),
            'categories' => array_map(
                static fn (Category $c): array => ['id' => $c->id, 'required' => $c->required, 'cookies' => $c->cookieNames()],
                $this->policy->categories->all(),
            ),
            'consentMode' => $this->consentMode?->toArray(),
            'wpConsentApi' => $this->wpConsentApi->toArray(),
            'gpc' => $this->gpcCategories,
        ];
    }

    private static function isSafeUrl(string $url): bool
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return ! preg_match('/[\s\\\\]/', $url);
        }
        $scheme = parse_url($url, PHP_URL_SCHEME); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- this layer does not load WordPress.

        return in_array($scheme, ['http', 'https'], true) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
