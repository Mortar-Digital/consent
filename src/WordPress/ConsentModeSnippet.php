<?php

declare(strict_types=1);

namespace MortarDigital\Consent\WordPress;

use MortarDigital\Consent\Config;
use MortarDigital\Consent\ConsentCookieCodec;

/**
 * Google Consent Mode v2 defaults, printed first thing in <head> so they run
 * before any Google tag. The snippet reads the visitor's consent cookie
 * itself, so returning visitors start "granted" without waiting for the
 * deferred runtime. The HTML stays identical for everyone, so it is cache-safe.
 *
 * Printed through wp_print_inline_script_tag(), so CSP plugins that add
 * nonces via `wp_inline_script_attributes` cover it.
 */
final class ConsentModeSnippet
{
    public function __construct(private readonly Config $config)
    {
    }

    public function register(): void
    {
        if ($this->config->consentMode !== null) {
            add_action('wp_head', $this->print(...), 1);
        }
    }

    public function print(): void
    {
        wp_print_inline_script_tag($this->script(), ['id' => 'mortar-consent-mode']);
    }

    public function script(): string
    {
        $categories = $this->config->policy->categories;
        $required = array_values(array_map(
            static fn ($c): string => $c->id,
            array_filter($categories->all(), static fn ($c): bool => $c->required),
        ));

        $data = wp_json_encode([
            'signals' => $this->config->consentMode?->toArray() ?? [],
            'required' => $required,
            'cookie' => ConsentCookieCodec::COOKIE_NAME,
            'pattern' => ConsentCookieCodec::pattern(),
            'policy' => $this->config->policy->version,
            'lifetime' => $this->config->policy->lifetimeSeconds(),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES);

        $function = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/consent-mode.js'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- reads a file shipped with the plugin.

        return trim(preg_replace('#^//.*\n#m', '', $function) ?? $function) . '(' . $data . ');';
    }
}
