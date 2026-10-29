# Consent

First-party cookie consent for Mortar Digital WordPress sites:

- Opt-in for the UK and EU.
- "Reject all" sits beside "Accept all" with the same styling.
- Visitors choose per category, and a settings control on every page lets them withdraw consent.
- No third-party services, and nothing for sites to build: the minified runtime is committed.

See [PLAN.md](PLAN.md) for the roadmap. Phases 1–3 are built:

- The banner and preferences dialog.
- Script gating.
- The Settings → Cookie consent screen.
- A consent log.
- The cookie settings button and cookie declaration blocks.
- Google Consent Mode v2.
- Click-to-load embeds.
- Global Privacy Control.
- WP Consent API support.

Phase 4 is also done:

- An independent security review, with its findings fixed.
- The minified runtime.
- An end-to-end browser suite.
- PHPStan at level 8.
- A standalone plugin build.

## How it loads

`composer.json` declares `type: wordpress-muplugin`, so sites install it into `web/app/mu-plugins/mortar-consent/`. The site template's `web/app/mu-plugins/mortar-consent.php` adapter then boots it.

Only `assets/*.css|js` and `blocks/*/index.js` are public. The Helm chart's nginx config returns 403 for everything else under that directory.

To add it to an existing site:

1. Copy the `packages/consent` path repository, the `mortar-digital/consent` requirement and the `wordpress-muplugin` installer path from `sites/_template/composer.json`.
2. Copy the adapter file.
3. Run `ddev composer update mortar-digital/consent`.

## Configuration

Settings come from three layers, with later ones winning:

1. **Defaults:** translated copy and four categories.
2. **Settings → Cookie consent:** also linked from the plugin's row under Plugins → Must-Use. An admin screen for the categories and cookie list, gated scripts, wording, policy version and log settings. It needs `manage_options`, which the `mortar_consent_capability` filter can change.
3. **Code overrides:** any key returned from `mortar_consent_overrides` wins, and the admin screen shows it read-only. Values the admin saved earlier are kept, so they come back if the override is removed.

```php
add_filter('mortar_consent_overrides', fn (): array => [
    // Script handles to gate. Dependants of a gated handle are gated too.
    'scripts' => [
        'google-analytics' => 'analytics',
        'meta-pixel' => ['marketing'],
    ],
]);
```

`mortar_consent_config` still runs last on the merged array as an escape hatch. The admin screen does not reflect changes made there.

The admin screen stores only values that differ from the defaults. Untouched categories and wording therefore keep following translations.

| Key | Default | Notes |
|---|---|---|
| `policy_version` | `1` | Positive integer. Changing it re-prompts every visitor. |
| `lifetime_days` | `365` | 1–395. Visitors are asked again after this. |
| `categories` | necessary, functional, analytics, marketing | `id`, `label`, `description`, `required`, and `cookies`. Each cookie is a name or `{name, provider, purpose, duration}`. A trailing `*` in the name matches a prefix. |
| `scripts` | `[]` | Script handle → category id, or a list of ids (all are required). |
| `texts` | English copy | Every key in `Config::TEXT_KEYS`, as plain text. |
| `privacy_policy_url` | WP privacy page | An http(s) URL or a site-relative path. |
| `cookie_domain` | host only | For example `.example.com` to share consent across subdomains. |
| `reopen_button` | `true` | A floating "Cookie settings" button. Turn it off when the theme provides its own link. |
| `log_enabled` | `true` | Keep the consent log. |
| `log_retention_days` | `1095` | 30–3650. A daily cron job deletes older records. |
| `consent_mode` | off, standard mapping | `enabled`, plus `signals`: a Consent Mode v2 signal → category ids. |
| `embeds` | `category: functional` | The category third-party embeds need. `""` loads them freely. `providers` overrides it per provider slug, e.g. `youtube`. |
| `gpc_categories` | `['marketing']` | Categories "Accept all" leaves off when the browser sends Global Privacy Control. |
| `wp_consent_api` | standard mapping | WP Consent API category → category ids. |

Configuration is validated strictly. If it is invalid, a warning is logged and the defaults are used. Every handle the bad config tried to gate stays gated and needs all optional categories, so a typo cannot let a tracker through.

## Gating scripts

**Registered scripts:** map the handle in `scripts`, as in the example above.

**Hand-written tags:** use `type="text/plain"` plus the categories. Put the real type in `data-mortar-consent-type`, for example `module`:

```html
<script type="text/plain" data-mortar-consent="analytics" src="https://example.com/tracker.js"></script>
<script type="text/plain" data-mortar-consent="analytics marketing">/* needs both */</script>
```

The runtime activates these in document order once consent is given. When a visitor withdraws a category that was already allowed, the page reloads, because scripts that have already run cannot be unloaded.

## Consent log

Each choice is sent to `POST /wp-json/mortar-consent/v1/records` and stored in `{prefix}mortar_consent_log`. A record holds:

- A random consent ID. The same ID is in the visitor's cookie and shown in their preferences dialog.
- The categories allowed.
- The policy version.
- How the choice was made: accept all, reject all or custom.
- The server time.

No IP address, user agent or user ID is stored.

The endpoint is public by design, because visitors are anonymous. It is hardened as follows:

- **Strict validation:** known fields only, the current policy version, and known optional categories.
- **Size cap:** a 1 KB body limit.
- **Rate limit:** 20 records per client per 10 minutes, plus a site-wide ceiling of 2000. Both are set with the `mortar_consent_rate_limits` filter.
  - Clients are keyed by an HMAC of the IP, with IPv6 grouped by /64. The IP is never stored.
  - With a persistent object cache, counters are atomic. Without one they use transients and can overshoot slightly under bursts.

**Behind a proxy, set the real client IP.** Otherwise every visitor shares one bucket, and real records get rejected once the limit is hit. In the Helm chart, set `nginx.trustedProxies` to the ingress controller's pod CIDR, for example `["10.42.0.0/16"]`. Anywhere else, configure the web server's real-IP module, or the `mortar_consent_client_ip` filter.

Limits of the log as evidence: the endpoint is public, so anyone can add records for a made-up consent ID. A record shows that a choice was submitted under that ID and policy version. It does not prove who made it.

Admins can see 30-day totals, look up a consent ID and download a CSV from the **Consent log** tab. The CSV escapes values that spreadsheets would read as formulas.

## Cookie declaration

The **Cookie declaration** block, or the `[mortar_consent_declaration]` shortcode, lists every category with its cookies' name, provider, purpose and duration. By default it ends with a cookie settings button. Put it on the cookie policy page.

## Google Consent Mode v2

Turn it on under **Integrations**, or with `consent_mode.enabled` in code.

A tiny inline script is printed first in `<head>`. It runs `gtag('consent', 'default', …)` before any Google tag:

- Each signal is `denied` unless the visitor's stored consent already grants it, so returning visitors start `granted` without waiting for the runtime.
- It sets `wait_for_update: 500`.
- It turns on `ads_data_redaction`.

The runtime then sends `gtag('consent', 'update', …)` whenever the visitor's choice changes.

A signal is granted only when it lists at least one category and the visitor allows every listed one. So an empty mapping, for example after a category is deleted, stays denied.

There are two ways to run it:

- **Basic mode:** also gate the Google tag's script handle under `scripts`, so nothing loads until consent.
- **Advanced mode:** leave the tag ungated and let Consent Mode send cookieless pings.

Tags hard-coded in a theme's `header.php` before `wp_head()` run before the defaults. Move them after it.

The snippet is printed with `wp_print_inline_script_tag()`, so CSP plugins can add nonces through `wp_inline_script_attributes`.

## Embedded content

`core/embed` blocks, iframes in other blocks, and iframes in post content are replaced with a placeholder. Post content includes classic posts and auto-embedded URLs. The original markup is kept inside an inert `<template>`, so nothing loads.

- **Known providers** are named in the placeholder. They include YouTube, Vimeo, Google Maps, Spotify, SoundCloud, social networks, Calendly and Typeform.
- **Unknown third-party hosts** are gated too, under the provider slug `other`, and the placeholder names the host.
- **Same-site iframes** are left alone.
- **Per-provider overrides:** `embeds.providers`, for example `['youtube' => 'marketing', 'other' => '']`.

The placeholder's buttons only work inside genuine placeholders, which hold a `<template>` that post filtering strips from contributor content. So a look-alike "Always allow" button in a post cannot grant consent.

The placeholder offers two buttons:

- **Load this content:** that embed only. Nothing is stored.
- **Always allow {category}:** saves consent for the category, loading every embed in it.

Embeds load directly for visitors who have already allowed the category.

## Global Privacy Control

When the browser sends GPC (`navigator.globalPrivacyControl`):

- A notice appears in the banner and the dialog.
- "Accept all" leaves the `gpc_categories` off.

Visitors can still switch those categories on individually, so an explicit choice wins.

## WP Consent API

When the [WP Consent API](https://wordpress.org/plugins/wp-consent-api/) plugin is active, the site declares the `optin` consent type. Each choice is mirrored to `wp_set_consent()` through the `wp_consent_api` mapping, so plugins that check `wp_has_consent()` follow the visitor's choice. Nothing happens when the plugin is not installed.

## Letting visitors change their mind

- The floating button, on by default.
- The **Cookie settings button** block, or the `[mortar_consent_settings label="…"]` shortcode.
- Any link to `#cookie-settings`, such as a menu item, or `/cookie-policy/#cookie-settings` from other pages.

## Styling

`assets/consent.css` is scoped under `.mortar-consent`. Restyle it from the theme with custom properties:

```css
.mortar-consent {
    --mortar-consent-bg: var(--wp--preset--color--background);
    --mortar-consent-fg: var(--wp--preset--color--ink);
    --mortar-consent-button-bg: var(--wp--preset--color--brand-primary);
    --mortar-consent-button-fg: var(--wp--preset--color--on-brand);
    --mortar-consent-radius: var(--wp--custom--radius--control);
}
```

For full control, return `false` from `mortar_consent_load_styles`. Keep the three choice buttons visually equal.

## JavaScript API

```js
window.mortarConsent.has('analytics'); // boolean
window.mortarConsent.get();            // { policyVersion, timestamp, consentId, granted } | null
window.mortarConsent.open();           // open the preferences dialog
document.addEventListener('mortar-consent:change', (e) => e.detail.categories);
```

On the server, `mortar_consent_has('analytics')` reads the same cookie. Output that varies on it cannot be full-page cached, so prefer client-side gating.

## Standalone plugin

```sh
bin/build-plugin   # requires Composer + Node dev dependencies; build/mortar-consent-<version>.zip
```

The zip has its own autoloader, so it does not need Composer. Install it like any plugin. If the MU copy is also loaded, the standalone copy stays inactive and shows a notice.

Deactivating the plugin clears its cron job. Deleting it keeps the settings and the consent log, unless `MORTAR_CONSENT_DELETE_DATA_ON_UNINSTALL` is defined as `true`.

## Development

```sh
composer install && pnpm install
pnpm build          # after editing assets/consent.js (commit consent.min.js)
composer test       # PHPUnit + node --test (codec parity, min build freshness, 5 KB gzip budget)
composer analyse    # PHPStan level 8 with WordPress stubs
composer lint       # PHP/JavaScript syntax + ESLint
pnpm lint           # ESLint recommended rules, zero warnings
pnpm lint:fix       # apply safe ESLint auto-fixes
composer format     # apply PSR-12 with PHP CS Fixer
composer check      # lint + formatting check + PHPStan + unit/JS tests
MORTAR_CONSENT_E2E_SITE=../../sites/<site> pnpm test:e2e
```

Sites load `consent.min.js`. When `SCRIPT_DEBUG` is on, they load the readable `consent.js` instead.

`tests/fixtures/cookie-codec.json` is run against both the PHP codec and both JS builds, so the cookie parsers cannot drift apart.

The e2e suite needs a running local DDEV site with the package installed. It adds a fixture MU-plugin and test pages, then runs Playwright specs:

- **Visitor:** flows, axe accessibility checks, the consent log and endpoint abuse.
- **Integrations:** Consent Mode, embeds, GPC, the WP Consent API and hardening cases.
- **Admin:** the real admin app rendered on a stand-in page, with the settings it saves sent through the real server validation.

The suite never contacts third parties. Afterwards it removes everything it added, including log rows written during the run. Screenshots go to `tests/e2e/artifacts/`.

## Known limits

- **Optimisation plugins:** plugins that combine or rewrite script tags, for example through `script_loader_tag`, can drop the `type="text/plain"` gate. Exclude gated handles from them.
- **Lazy-loaded iframes:** iframes that load through JavaScript (`data-src`) rather than markup are not detected.
- **Shared cookie domain:** with `cookie_domain` set to a parent domain, any subdomain can set the consent cookie.
- **Capability:** whoever holds `mortar_consent_capability` controls what runs on the site. Keep it at `manage_options`.

## Release review

Released under GPL-2.0-or-later; see [LICENSE](LICENSE). The standalone ZIP includes the licence and readable JavaScript source.

Before submitting to WordPress.org:

- Run `composer check` and the DDEV browser suite, then `bin/build-plugin`. The build rejects a stale minified runtime.
- Install the ZIP on a clean WordPress site without the MU adapter. Check activation, settings, blocks, consent changes, deactivation and uninstall.
- Test on PHP 8.2 (the runtime minimum) as well as a current PHP version. PHPUnit 12 requires PHP 8.3 or newer for development.
- Run the official [Plugin Check](https://wordpress.org/plugins/plugin-check/) against the built plugin and address its findings.
- Confirm the contributor account and set `Tested up to` in `readme.txt` to the WordPress version actually tested. The existing value is not a substitute for a compatibility run.
- Review the readme's privacy disclosure and cookie inventory against the release behaviour.

The formatter uses only the PSR-12 preset, with no WordPress PHPCS ruleset or risky fixers. PHPStan checks WordPress API usage separately. `composer format:check` reports changes without writing files. All quality tools are development dependencies and are excluded from the plugin ZIP.

JavaScript uses ESLint's recommended rules with browser globals for shipped scripts and Node globals for test tooling. Playwright specs also allow browser globals for page callbacks. The generated `assets/consent.min.js` is excluded from ESLint; the existing build tests verify it matches the linted source. Vendor, build and test artifact directories are excluded too. ESLint runs through `pnpm lint`, `composer lint` and `composer check`. It does not impose a separate formatting preset or require WordPress's larger JavaScript toolchain.
