<?php

declare(strict_types=1);

namespace MortarDigital\Consent\WordPress;

use MortarDigital\Consent\ConsentCookieCodec;

/**
 * WordPress entry point. The MU-plugin adapter (or standalone plugin file)
 * calls boot() once with the package's directory and public URL.
 */
final class Plugin
{
    public const PURGE_HOOK = 'md_consent_purge_log';

    private static ?self $instance = null;

    private ?Renderer $renderer = null;

    private readonly ConsentLog $log;

    private function __construct(
        private readonly string $dir,
        private readonly string $url,
        private readonly ConfigProvider $configProvider,
    ) {
        $this->log = new ConsentLog();
    }

    public static function boot(string $dir, string $url): self
    {
        if (self::$instance !== null) {
            return self::$instance;
        }
        self::$instance = new self(rtrim($dir, '/'), rtrim($url, '/'), new ConfigProvider());
        // Config uses translated defaults and theme filters, so build it late.
        add_action('init', self::$instance->init(...), 5);

        return self::$instance;
    }

    public static function instance(): ?self
    {
        return self::$instance;
    }

    public function init(): void
    {
        // Loaded as an MU package or as the standalone plugin build.
        if (str_starts_with(wp_normalize_path($this->dir), wp_normalize_path(WPMU_PLUGIN_DIR))) {
            load_muplugin_textdomain('md-consent', basename($this->dir) . '/languages');
        } else {
            load_plugin_textdomain('md-consent', false, basename($this->dir) . '/languages');
        }
        (new LegacyMigration($this->log))->run();
        register_setting('md_consent', ConfigProvider::OPTION, [
            'type' => 'object',
            'default' => [],
            'show_in_rest' => false,
            'sanitize_callback' => $this->configProvider->sanitizeOption(...),
        ]);
        $config = $this->configProvider->get();

        (new ScriptGate($config->scripts))->register();
        (new ConsentModeSnippet($config))->register();
        (new EmbedGate($config))->register();
        // WP Consent API (wordpress.org/plugins/wp-consent-api), when active:
        // declare opt-in; the runtime mirrors choices with wp_set_consent().
        add_filter('wp_get_consent_type', static fn (): string => 'optin');
        $this->renderer = new Renderer($config, [
            'log' => $config->logEnabled ? rest_url(RestController::NAMESPACE . '/records') : '',
        ]);
        $this->renderer->register();

        add_action('wp_enqueue_scripts', $this->enqueueAssets(...), 1);
        $this->registerBlocks();

        $this->log->install();
        /**
         * Consent-log rate limits per 10 minutes: per client, and site-wide.
         * Raise the global ceiling for very busy sites.
         *
         * @param array{per_client: int, global: int} $limits
         */
        $limits = apply_filters('md_consent_rate_limits', ['per_client' => 20, 'global' => 2000]);
        $rest = new RestController($this->configProvider, $this->log, new RateLimiter(
            'md_consent_rl_',
            max(1, (int) ($limits['per_client'] ?? 20)),
            max(1, (int) ($limits['global'] ?? 2000)),
            600,
        ));
        add_action('rest_api_init', $rest->register(...));
        $this->scheduleRetention($config->logRetentionDays);

        if (is_admin()) {
            (new AdminPage($this->url, $this->dir, $this->log))->register();
        }
    }

    /**
     * Server-side check, e.g. before printing a tracking pixel. Pages that
     * vary on this cannot be full-page cached; prefer client-side gating.
     */
    public function has(string $category): bool
    {
        $policy = $this->configProvider->get()->policy;
        $raw = $_COOKIE[ConsentCookieCodec::COOKIE_NAME] ?? $_COOKIE[ConsentCookieCodec::LEGACY_COOKIE_NAME] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- strictly validated by the codec.
        $state = is_string($raw) ? (new ConsentCookieCodec())->decode(wp_unslash($raw), $policy->categories) : null;

        return $policy->allows($state, $category, time());
    }

    private function enqueueAssets(): void
    {
        // Minified build (pnpm build) unless SCRIPT_DEBUG asks for the readable source.
        $runtime = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? 'assets/consent.js' : 'assets/consent.min.js';
        wp_enqueue_script('md-consent', $this->url . '/' . $runtime, [], $this->version($runtime), [
            'strategy' => 'defer',
            'in_footer' => false,
        ]);

        /**
         * Whether to load the fallback stylesheet. Themes that fully style the
         * banner can return false; most only need the CSS custom properties.
         */
        if (apply_filters('md_consent_load_styles', true)) {
            wp_enqueue_style('md-consent', $this->url . '/assets/consent.css', [], $this->version('assets/consent.css'));
        }
    }

    private function registerBlocks(): void
    {
        $renderer = $this->renderer;
        if ($renderer === null) {
            return;
        }
        // Editor scripts are registered by handle: block.json "file:" paths
        // resolve symlinks, which breaks URLs when the package is symlinked.
        $editorDeps = ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render'];
        foreach (['settings-button', 'cookie-declaration'] as $block) {
            wp_register_script("md-consent-{$block}-editor", "{$this->url}/blocks/{$block}/index.js", $editorDeps, $this->version("blocks/{$block}/index.js"), ['in_footer' => true]);
            wp_set_script_translations("md-consent-{$block}-editor", 'md-consent');
        }

        register_block_type($this->dir . '/blocks/settings-button', [
            // RichText stores entity-encoded text; decode so esc_html() does not double-encode.
            'render_callback' => static fn (array $attributes): string => $renderer->settingsButton(
                is_string($attributes['label'] ?? null) ? html_entity_decode(wp_strip_all_tags($attributes['label']), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '',
                // wp-element-button picks up the theme.json button styles.
                get_block_wrapper_attributes(['class' => 'md-consent-open wp-element-button']),
            ),
        ]);
        register_block_type($this->dir . '/blocks/cookie-declaration', [
            'render_callback' => static fn (array $attributes): string => $renderer->declaration(
                ($attributes['showSettingsButton'] ?? true) === true,
                get_block_wrapper_attributes(['class' => 'md-consent-declaration']),
            ),
        ]);

        add_shortcode('md_cookie_settings', static fn (mixed $atts): string => $renderer->settingsButton(
            is_array($atts) && is_string($atts['label'] ?? null) ? $atts['label'] : '',
        ));
        add_shortcode('md_cookie_declaration', static fn (): string => $renderer->declaration(true, 'class="md-consent-declaration"'));
    }

    private function scheduleRetention(int $retentionDays): void
    {
        add_action(self::PURGE_HOOK, function () use ($retentionDays): void {
            $this->log->purge($retentionDays);
        });
        if (! wp_next_scheduled(self::PURGE_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::PURGE_HOOK);
        }
    }

    private function version(string $path): string
    {
        $mtime = @filemtime($this->dir . '/' . $path);

        return $mtime === false ? '0' : (string) $mtime;
    }
}
