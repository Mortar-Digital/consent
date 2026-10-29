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
        wp_print_inline_script_tag($this->script(), ['id' => 'md-consent-mode']);
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

        // Kept ES5 and tiny: it runs before anything else on the page.
        return <<<JS
(function (c) {
  var w = window, granted = [], m, v, now = Date.now() / 1000, state = {};
  w.dataLayer = w.dataLayer || [];
  w.gtag = w.gtag || function () { w.dataLayer.push(arguments); };
  m = document.cookie.match(new RegExp('(?:^|;\\\\s*)' + c.cookie + '=([^;]*)'));
  if (m) {
    v = new RegExp(c.pattern).exec(m[1]);
    if (v && +v[1] === c.policy && +v[2] <= now + 300 && now - +v[2] <= c.lifetime && v[4]) { granted = v[4].split('.'); }
  }
  for (var signal in c.signals) {
    state[signal] = c.signals[signal].length && c.signals[signal].every(function (id) {
      return c.required.indexOf(id) > -1 || granted.indexOf(id) > -1;
    }) ? 'granted' : 'denied';
  }
  state.wait_for_update = 500;
  w.gtag('consent', 'default', state);
  w.gtag('set', 'ads_data_redaction', true);
})({$data});
JS;
    }
}
