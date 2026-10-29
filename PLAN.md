# Consent — implementation plan

A first-party cookie consent tool for Mortar Digital WordPress sites. Self-hosted, no third-party APIs, UK/EU opt-in compliant, page-cache safe.

## Decisions

- **Packaging: both.** Core library in `packages/consent`, loaded per site through an MU-plugin adapter. A build script also produces a standalone plugin zip from the same source.
- **Config: admin UI + code overrides.** A React settings screen built with `@wordpress/components` for clients. Developers can seed or lock any setting from code.
- **Scope: UK + EU opt-in.** Strict prior consent. Global Privacy Control (GPC) is honoured as "reject marketing".
- **Consent log: yes, minimal.** Stores a random ID, the choices, the policy version and a timestamp. No IP or user agent is stored.

## Compliance requirements (acceptance criteria)

1. No non-essential cookies, storage or network requests before consent, or after "Reject all".
2. "Accept all", "Reject all" and "Manage preferences" sit on the first layer with equal prominence.
3. Per-category choice, with no pre-ticked non-essential categories.
4. A persistent "Cookie settings" control on every page. Withdrawing consent removes known cookies in that category, and the change takes effect without a reload where possible.
5. Re-prompt when the policy version changes or consent expires (default 12 months).
6. No cookie wall: content stays usable while the banner is open.
7. WCAG 2.2 AA: native `<dialog>`, focus management, keyboard operable, labelled controls, reduced-motion respected.
8. A cookie declaration listing each cookie's name, provider, purpose, duration and category.

## Architecture

```
packages/consent/
  src/                 Framework-agnostic PHP (no WP globals), PHPUnit-tested
    Category, CategoryRegistry, ConsentState, ConsentCookieCodec,
    PolicyVersion, ScriptRule/ScriptMap, ConsentRecord, ConsentRecordValidator
  wordpress/           WP integration layer (hooks, REST, settings, blocks, DB)
  assets/src/          Frontend runtime (vanilla TS) + admin app (React)
  tests/               PHPUnit (unit) + WP integration + Playwright e2e
  bin/build-plugin     Produces standalone plugin zip
sites/_template/web/app/mu-plugins/consent.php   Thin adapter
```

The theme owns presentation through CSS custom properties and theme.json tokens. The package ships only a minimal fallback stylesheet.

### Consent state

- **Cookie:** first-party `mortar_consent`, strictly necessary, `Secure; SameSite=Lax; Path=/`, written client-side. It contains a compact JSON of `{v: policyVersion, c: {category: bool}, id: uuid, t: unixTime}`.
- **Server reads** the cookie only for `wp_has_consent()` checks. The HTML never varies per visitor, so full-page caches stay valid.

### Script gating

- **Registered scripts:** map each handle to a category. `wp_script_attributes` / `wp_inline_script_attributes` then rewrite the tag to `type="text/plain" data-consent="<category>"`.
- **Hand-written tags** use the same attributes.
- **Activation:** the runtime clones gated scripts into executable `<script>` elements when the category is granted, preserving order.
- **No MutationObserver auto-blocking.** It is fragile and slow, so mapping stays explicit.
- **Embeds:** an oEmbed / `render_block` filter swaps iframes for click-to-load placeholders until the category is granted.

### Integrations

- **Google Consent Mode v2:** a default-deny `gtag('consent','default',…)` is emitted early in `<head>`, and `update` is sent on change. Categories map to `ad_storage`, `ad_user_data`, `ad_personalization`, `analytics_storage`, and so on.
- **WP Consent API:** when the plugin is present, register as the consent provider so `wp_has_consent()` reflects our state.
- **JS API:** `window.mortarConsent.get()`, `.has(category)`, `.open()`, plus a `mortar-consent:change` event.

### Consent log

- **Endpoint:** `POST /wp-json/mortar-consent/v1/records`. It is public and cache-safe, needs no nonce, and accepts a JSON body only.
- **Validation:** strict schema, 2 KB body cap, and only known categories and the current policy version are accepted.
- **Rate limiting:** a transient keyed on HMAC(IP, salt). The IP is never persisted.
- **Storage:** a custom table `{prefix}mortar_consent_records` holding `id`, `consent_id`, `choices` (JSON), `policy_version`, `created_at` and `source` (banner/settings/gpc). Access is through `$wpdb->prepare` only.
- **Retention:** purged by WP-Cron (default 3 years, configurable).
- **Admin:** a capability-gated list and CSV export, with CSV formula-injection escaping.

### Admin & config

- **Settings:** `register_setting` with full REST schemas (`show_in_rest`), stored in one option.
- **Settings screen:** manage categories (name, description, required flag), the cookie declaration, banner copy, policy version bump, expiry, script handle → category map, and Consent Mode mapping.
- **Code overrides:** `mortar_consent_config` filter plus `MORTAR_CONSENT_LOCKED` constant/env. Locked fields render read-only in the UI.
- **Capability:** `manage_options` by default, filterable.
- **Blocks:** "Cookie settings button" and "Cookie declaration". Shortcode fallbacks are provided.
- **i18n:** all strings are translatable, and admin-entered copy is filterable for multilingual plugins.

### Security checklist

- Escape all output (`esc_html`, `esc_attr`, `wp_kses` with an allowlist for the rich-text banner copy).
- Run capability checks and nonces on every admin and REST write except the public log endpoint.
- **CSP-friendly:**
  - No inline JS apart from a `<script type="application/json">` config block.
  - The Consent Mode snippet supports a nonce through a filter, and can be moved to an external file.
- No `eval`, no `innerHTML` with untrusted data, and scripts are activated by cloning nodes.
- Uninstall routine (standalone plugin) drops the table and option only when the user opts in.

## Phases

Phase 4 is built (2026-10-26):

- **Security review:** an independent review found no XSS or SQL injection. Its findings are fixed and covered by tests:
  - Forged embed "allow" buttons in post content.
  - The rate limiter collapsing to one shared bucket behind the ingress. The chart now has opt-in `nginx.trustedProxies`, and there is a global ceiling, IPv6 /64 buckets and atomic counters.
  - Invalid config switching Consent Mode off. The fallback now keeps it on with every signal denied.
  - A list-shaped `scripts` typo failing open.
  - A marker string switching off embed gating for a whole post.
  - Unknown third-party iframes are now gated as well.
- **Runtime:** minified `consent.min.js` (about 3 KB gzipped), with tests for build freshness and the size budget.
- **Tests:** the Playwright e2e suite now lives in `tests/e2e/`. PHPStan runs at level 8.
- **Standalone plugin:** `bin/build-plugin` builds it. Deactivation clears the cron job. Uninstall keeps data unless you opt in. The suite was verified end-to-end against the standalone build.

Phase 3 is built (2026-10-26). Notes:

- **Consent Mode head snippet:** it reads the consent cookie itself, using the same regex as the PHP codec. A shared fixture keeps PHP and JS in sync. Returning visitors therefore start "granted".
- **Embed placeholders:** "Load this content" loads one embed without storing consent. "Always allow" stores consent for the category.
- **WP Consent API mapping:** it is code-level only, and it has not been tested against the real plugin. The browser test stubs `wp_set_consent()`.
- **Runtime size:** 5.3 KB gzipped unminified, above the 5 KB budget. Phase 4 minification should bring it back under.

Phase 2 is built (2026-10-26). Changes from the plan:

- Admin settings use their own `mortar-consent/v1/settings` route instead of the core settings endpoint, so validation errors reach the screen.
- Code overrides come from `mortar_consent_overrides`, and the keys it sets are locked in the UI.
- The admin screen is plain JS on the editor's bundled React, with no build step.

Phase 1 is built (2026-10-26). Two things changed from the plan:

- The frontend is plain JavaScript (`// @ts-check`) rather than compiled TypeScript, so the package needs no build step.
- The styles are scoped under `.mortar-consent` rather than a cascade layer. Theme element resets beat layered rules.

1. **Core:**
   - Package skeleton and the PHP domain model with tests.
   - MU adapter.
   - Frontend runtime: banner and preferences dialog, cookie, script gating, re-open block, re-prompt on policy version.
2. **Admin and proof:** settings REST and the React screen with code overrides and locks, consent log with retention and CSV, and the cookie declaration block.
3. **Integrations:** Consent Mode v2, WP Consent API, embed placeholders, GPC.
4. **Hardening and distribution:**
   - Playwright e2e that asserts no cookies or requests before consent, after reject and after withdraw.
   - axe checks.
   - Performance budget: runtime under 5 KB gz.
   - Standalone plugin build.

## Open items

- Product and plugin name (working slug: `mortar-consent`).
- Whether to add a local, opt-in cookie scanner later. The scanner would be crawl-based and must not call third-party services.
