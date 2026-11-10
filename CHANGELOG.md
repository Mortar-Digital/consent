# Changelog

All notable changes to Mortar Consent. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).

## [0.1.0] - 2026-11-10

First public release.

### Added

- Consent banner and preferences dialog with equal "Accept all", "Reject all" and "Manage preferences" choices.
- Script gating by handle or `data-mortar-consent` markup, including dependants of gated scripts.
- Click-to-load placeholders for embeds, including unknown third-party hosts.
- Google Consent Mode v2, Global Privacy Control and WP Consent API support.
- Consent log without IP addresses, with lookup by consent ID and CSV export.
- Cookie declaration and Cookie settings button blocks and shortcodes.
- Settings → Cookie consent screen, with code overrides through `mortar_consent_overrides`.
- Suggested privacy policy text.
