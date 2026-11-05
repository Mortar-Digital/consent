# Mortar Consent

[![CI](https://github.com/Mortar-Digital/consent/actions/workflows/ci.yml/badge.svg)](https://github.com/Mortar-Digital/consent/actions/workflows/ci.yml)

Self-hosted cookie consent for WordPress. Opt-in by default, with "Reject all" beside "Accept all", per-category choices that visitors can change at any time, and no calls to third-party services.

Made by [Mortar Digital](https://mortar.digital/), who design, build, host and look after WordPress sites. This is the development repository: site owners should install the plugin from WordPress.org and read the [documentation](https://mortar.digital/plugins/consent/docs/). [readme.txt](readme.txt) is the WordPress.org description.

## Features

- **Consent banner and preferences dialog.** "Accept all", "Reject all" and "Manage preferences" have equal weight. The dialog uses native `<dialog>`, is keyboard operable and passes axe WCAG 2.2 AA checks.
- **Script gating.** Map script handles to categories, or mark hand-written tags with `type="text/plain" data-mortar-consent="analytics"`. Scripts that depend on a gated handle are gated too.
- **Click-to-load embeds.** YouTube, Vimeo, Maps and other iframes become placeholders until the visitor allows them. Unknown third-party hosts are gated as well.
- **Google Consent Mode v2**, **Global Privacy Control** and the **WP Consent API**.
- **Consent log.** A random consent ID, the choice, the policy version and the time. No IP address, user agent or user ID. CSV export.
- **Blocks and shortcodes.** A cookie declaration and a "Cookie settings" button.
- **Page-cache safe.** The markup is identical for every visitor, and the runtime decides what to show.
- **Small.** The visitor runtime is under 5 KB gzipped and has no dependencies.

## Installation

**From WordPress.org:** Plugins → Add New, search for "Mortar Consent", then Settings → Cookie consent.

**From a release zip:** download `mortar-consent-<version>.zip` from the [releases](https://github.com/Mortar-Digital/consent/releases) and upload it under Plugins → Add New → Upload Plugin.

**As a must-use plugin**, for example in a Bedrock site that tracks it as a Git submodule or Composer path package, place the repository at `mu-plugins/mortar-consent/` and add a loader, `mu-plugins/mortar-consent.php`:

```php
<?php
/**
 * Plugin Name: Mortar Consent
 */

use MortarDigital\Consent\WordPress\Plugin;

// Composer's autoloader provides the classes. Pass the MU paths explicitly,
// because a symlinked package would otherwise resolve to its real location.
if (class_exists(Plugin::class)) {
    Plugin::boot(WPMU_PLUGIN_DIR . '/mortar-consent', WPMU_PLUGIN_URL . '/mortar-consent');
    require_once WPMU_PLUGIN_DIR . '/mortar-consent/functions.php';
}
```

Without Composer, `require WPMU_PLUGIN_DIR . '/mortar-consent/mortar-consent.php';` is enough: the main file has its own autoloader. If the plugin is loaded both ways, the regular copy stays inactive and shows a notice.

Only `assets/` and `blocks/` need to be reachable over HTTP; you can deny the rest of the plugin directory at the web server.

## Configuration

Settings come from three layers, with later ones winning:

1. **Defaults:** translated wording and four categories (necessary, functional, analytics, marketing).
2. **Settings → Cookie consent:** categories and cookies, gated scripts, integrations, wording, the policy version and the log. It needs `manage_options`, which the `mortar_consent_capability` filter can change.
3. **Code overrides:** any key returned from `mortar_consent_overrides` wins, and the settings screen shows it read-only. Values saved earlier come back if the override is removed.

```php
add_filter('mortar_consent_overrides', fn (): array => [
    // Script handles to gate. Dependants of a gated handle are gated too.
    'scripts' => [
        'google-analytics' => 'analytics',
        'meta-pixel' => ['marketing'],
    ],
]);
```

`mortar_consent_config` runs last on the merged array as an escape hatch. The settings screen does not reflect changes made there.

The settings screen stores only values that differ from the defaults, so untouched categories and wording keep following translations.

| Key | Default | Notes |
|---|---|---|
| `policy_version` | `1` | Positive integer. Raising it asks every visitor again. |
| `lifetime_days` | `365` | 1–395. Visitors are asked again after this. |
| `categories` | necessary, functional, analytics, marketing | Each has `id`, `label`, `description`, `required` and `cookies`. A cookie is a name or `{name, provider, purpose, duration}`; a trailing `*` matches a prefix. |
| `scripts` | `[]` | Script handle → category id, or a list of ids (all are required). |
| `texts` | English wording | Every key in `Config::TEXT_KEYS`, as plain text. |
| `privacy_policy_url` | the WordPress privacy page | An http(s) URL or a site-relative path. |
| `cookie_domain` | this host only | For example `.example.com` to share consent across subdomains. |
| `reopen_button` | `true` | The floating "Cookie settings" button. Turn it off when the theme links to `#cookie-settings` instead. |
| `log_enabled` | `true` | Keep the consent log. |
| `log_retention_days` | `1095` | 30–3650. A daily cron job deletes older records. |
| `consent_mode` | off, standard mapping | `enabled`, plus `signals`: Consent Mode v2 signal → category ids. |
| `embeds` | `category: functional` | The category third-party embeds need; `""` loads them freely. `providers` overrides it per provider slug, such as `youtube` or `other`. |
| `gpc_categories` | `['marketing']` | Categories "Accept all" leaves off when the browser sends Global Privacy Control. |
| `wp_consent_api` | standard mapping | WP Consent API category → category ids. |

Configuration is validated strictly. If it is invalid, a warning is logged and the defaults are used. Every handle the bad configuration tried to gate stays gated and needs all optional categories, so a typo cannot let a tracker through.

## Gating scripts

**Registered scripts:** map the handle under `scripts`, as above. Inline `before`/`after` scripts and translations attached to the handle are gated with it.

**Hand-written tags:** use `type="text/plain"` plus the categories. Put the real type in `data-mortar-consent-type`, for example `module`:

```html
<script type="text/plain" data-mortar-consent="analytics" src="https://example.com/tracker.js"></script>
<script type="text/plain" data-mortar-consent="analytics marketing">/* needs both */</script>
```

The runtime activates these in document order once consent is given. When a visitor withdraws a category they had allowed, the page reloads, because scripts that have already run cannot be unloaded. Known cookies in the withdrawn categories are deleted.

## Embedded content

`core/embed` blocks, iframes in other blocks and iframes in post content (classic posts and auto-embedded URLs) are replaced with a placeholder. The original markup is kept inside an inert `<template>`, so nothing loads.

- **Known providers** are named in the placeholder: YouTube, Vimeo, Dailymotion, Spotify, SoundCloud, X, Instagram, Facebook, TikTok, Google Maps, OpenStreetMap, Calendly and Typeform.
- **Unknown third-party hosts** are gated under the provider slug `other`, and the placeholder names the host.
- **Same-site iframes** are left alone.

The placeholder offers **Load this content** (that embed only, nothing stored) and **Always allow {category}** (saves consent and loads every embed in the category). Its buttons only work inside genuine placeholders, so a look-alike button in post content cannot grant consent.

## Google Consent Mode v2

Turn it on under **Integrations**, or with `consent_mode.enabled` in code.

A small inline script is printed first in `<head>` and runs `gtag('consent', 'default', …)` before any Google tag. Each signal is `denied` unless the visitor's stored consent already grants it, so returning visitors start `granted` without waiting for the runtime. It also sets `wait_for_update: 500` and `ads_data_redaction`. The runtime sends `gtag('consent', 'update', …)` whenever the choice changes.

A signal is granted only when it lists at least one category and the visitor allows every listed one, so an empty mapping stays denied.

- **Basic mode:** also gate the Google tag's script handle under `scripts`, so nothing loads before consent.
- **Advanced mode:** leave the tag ungated and let Consent Mode send cookieless pings.

Tags hard-coded before `wp_head()` run before the defaults; move them after it. The snippet is printed with `wp_print_inline_script_tag()`, so CSP plugins can add nonces through `wp_inline_script_attributes`.

## Global Privacy Control

When the browser sends GPC (`navigator.globalPrivacyControl`), a notice appears in the banner and dialog, and "Accept all" leaves the `gpc_categories` off. Visitors can still switch those categories on individually.

## WP Consent API

When the [WP Consent API](https://wordpress.org/plugins/wp-consent-api/) plugin is active, the site declares the `optin` consent type and each choice is mirrored to `wp_set_consent()` through the `wp_consent_api` mapping. Plugins that check `wp_has_consent()` then follow the visitor's choice.

## Letting visitors change their mind

- The floating "Cookie settings" button, on by default.
- The **Cookie settings button** block, or `[mortar_consent_settings label="…"]`.
- Any link to `#cookie-settings`, such as a menu item, or `/cookie-policy/#cookie-settings` from other pages.

## Cookie declaration

The **Cookie declaration** block, or `[mortar_consent_declaration]`, lists every category with each cookie's name, provider, purpose and duration, followed by a cookie settings button. Put it on the cookie policy page.

## Consent log

Each choice is sent to `POST /wp-json/mortar-consent/v1/records` and stored in `{prefix}mortar_consent_log`: the consent ID (also in the visitor's cookie and shown in their preferences dialog), the categories allowed, the policy version, how the choice was made and the server time.

The endpoint is public, because visitors are anonymous. It accepts known fields only, the current policy version and known optional categories, with a 1 KB body limit. It is rate limited to 20 records per client per 10 minutes, plus a site-wide ceiling of 2000 (`mortar_consent_rate_limits`). Clients are keyed by an HMAC of the IP address, with IPv6 grouped by /64; the address is never stored.

**Behind a reverse proxy or load balancer, make sure PHP sees the real client IP**, through the web server's real-IP module or the `mortar_consent_client_ip` filter. Otherwise every visitor shares one rate-limit bucket.

Because the endpoint is public, anyone can add a record for a made-up consent ID. A record shows that a choice was submitted under that ID and policy version; it does not prove who made it.

Admins can see 30-day totals, look up a consent ID and download a CSV from the **Consent log** tab. The CSV neutralises values that spreadsheets would read as formulas.

## Styling

`assets/consent.css` is scoped under `.mortar-consent`. Restyle it with custom properties:

```css
.mortar-consent {
    --mortar-consent-bg: var(--wp--preset--color--base);
    --mortar-consent-fg: var(--wp--preset--color--contrast);
    --mortar-consent-button-bg: var(--wp--preset--color--accent-1);
    --mortar-consent-button-fg: var(--wp--preset--color--base);
    --mortar-consent-radius: 4px;
}
```

The full set is `--mortar-consent-` followed by `bg`, `fg`, `muted`, `border`, `button-bg`, `button-fg`, `focus`, `radius`, `shadow`, `gap`, `font`, `banner-width`, `backdrop` and `z-index`.

For full control, return `false` from `mortar_consent_load_styles`. Keep the three choice buttons visually equal.

## Developer reference

### Filters

| Filter | Receives | Use |
|---|---|---|
| `mortar_consent_overrides` | `array` | Settings that win over the settings screen and show there read-only. |
| `mortar_consent_config` | `array` | Final say over the merged configuration. Not reflected in the settings screen. |
| `mortar_consent_capability` | `'manage_options'` | Capability for the settings screen, consent log and REST routes. |
| `mortar_consent_load_styles` | `true` | Return `false` to drop the fallback stylesheet. |
| `mortar_consent_rate_limits` | `['per_client' => 20, 'global' => 2000]` | Consent log limits per 10 minutes. |
| `mortar_consent_client_ip` | `string` | The client address used for rate limiting. |

### PHP

```php
mortar_consent_has('analytics'); // bool, from the visitor's cookie
```

Output that varies on this cannot be full-page cached, so prefer client-side gating.

### JavaScript

```js
window.mortarConsent.has('analytics'); // boolean
window.mortarConsent.get();            // { policyVersion, timestamp, consentId, granted } | null
window.mortarConsent.open();           // open the preferences dialog
window.mortarConsent.acceptAll();
window.mortarConsent.rejectAll();
window.mortarConsent.set(['analytics']); // save a custom choice
document.addEventListener('mortar-consent:change', (e) => e.detail.categories); // { id: boolean }
```

### Uninstall

Deactivating the plugin clears its cron job. Deleting it keeps the settings and the consent log, which you may need as evidence, unless `MORTAR_CONSENT_DELETE_DATA_ON_UNINSTALL` is defined as `true` in `wp-config.php`. On multisite this applies to every site.

## Known limits

- **Optimisation plugins** that combine or rewrite script tags (for example through `script_loader_tag`) can drop the `type="text/plain"` gate. Exclude gated handles from them.
- **Iframes inserted by JavaScript**, or lazy-loaded through `data-src`, are not detected.
- **Shared cookie domain:** with `cookie_domain` set to a parent domain, any subdomain can set the consent cookie.
- **Capability:** whoever holds `mortar_consent_capability` controls what runs on the site. Keep it at `manage_options`.

## Development

Requires PHP 8.2+, Composer, Node 24+ and pnpm. The integration and browser tests also need Docker.

```sh
composer install && pnpm install
composer check         # syntax lint, ESLint, formatting, PHPCS, PHPStan, PHPUnit and JS tests
composer format        # format PHP (PHP CS Fixer) and JS, JSON, CSS, YAML (Prettier)
pnpm build             # after editing assets/consent.js; commit consent.min.js
bin/build-plugin       # build/mortar-consent-<version>.zip
```

`composer check` runs:

- **Formatting checks:** PHP CS Fixer (PER-CS plus blank lines before control statements, trailing commas and ordered imports) and Prettier.
- **PHPCS** with the WordPress security, database, i18n and prefix sniffs (formatting is PHP CS Fixer's job).
- **PHPStan** at level 9 with WordPress stubs.
- **PHPUnit** for the framework-agnostic classes in `src/`.
- **Node tests** that check `consent.min.js` is current, under the 5 KB gzip budget, and parses cookies exactly like the PHP codec (`tests/fixtures/cookie-codec.json`).

### Integration tests

`tests/Integration` loads the real WordPress in wp-env, with the plugin active, and tests the WordPress-facing classes against it: script tags as WordPress prints them, embed gating, the REST routes and their validation, the fail-closed configuration fallback, escaping in the banner and declaration, and the consent log. Each test restores what it changes.

```sh
pnpm exec wp-env start
composer test:integration   # runs PHPUnit inside the wp-env container
```

### Browser tests

The Playwright suite runs against [wp-env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/), with the plugin active as a regular plugin:

```sh
pnpm exec playwright install chromium   # once
pnpm exec wp-env start
pnpm test:e2e
```

It covers visitor flows with axe accessibility checks, the consent log and its abuse limits, Consent Mode, embeds, GPC, the WP Consent API, and the settings screen. It adds a fixture MU-plugin and test pages, never contacts third parties, and removes everything it added afterwards. Screenshots go to `tests/e2e/artifacts/`.

To run it against a local DDEV site that loads the plugin as an MU-plugin instead:

```sh
MORTAR_CONSENT_E2E_ENV=ddev MORTAR_CONSENT_E2E_SITE=../path/to/site pnpm test:e2e
```

CI runs all of the above on every push: unit tests on PHP 8.2–8.5, and the integration and browser tests on WordPress 6.5 and the latest release.

## Contributing and security

See [CONTRIBUTING.md](CONTRIBUTING.md). Please report security issues privately as described in [SECURITY.md](SECURITY.md), not in public issues.

## Licence

GPL-2.0-or-later. See [LICENSE](LICENSE). © Mortar Digital.
