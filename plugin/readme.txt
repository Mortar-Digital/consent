=== Mortar Consent ===
Contributors: mortardigital
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: __VERSION__
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent that is opt-in, lets visitors choose and change their mind, and never calls third-party services.

== Description ==

* "Reject all" sits beside "Accept all", with the same styling.
* Per-category choices, a persistent "Cookie settings" control, and a re-prompt when your cookie policy changes or consent expires.
* Blocks scripts until consent, by script handle or with `type="text/plain" data-md-consent="analytics"`.
* Turns YouTube, Vimeo, Maps and other embeds into click-to-load placeholders.
* Supports Google Consent Mode v2, Global Privacy Control and the WP Consent API.
* Keeps a consent log with no IP addresses, and offers a CSV export.
* Includes Cookie declaration and Cookie settings button blocks.
* Self-hosted: no external requests and no accounts.

Configure it under Settings → Cookie consent.

== Uninstall ==

Deleting the plugin keeps your settings and consent log unless `MD_CONSENT_DELETE_DATA_ON_UNINSTALL` is defined as `true`.

== Installation ==

1. Upload the mortar-consent directory to wp-content/plugins, or install the ZIP through Plugins → Add New → Upload Plugin.
2. Activate Mortar Consent.
3. Configure Settings → Cookie consent. Add your tracking script handles, cookie inventory and policy link before enabling trackers.
4. Add the Cookie declaration block to your cookie policy page and test accepting, rejecting and withdrawing consent.

== Frequently Asked Questions ==

= Does this automatically find every tracker? =

No. Site owners must configure script handles and their cookie inventory. Hard-coded scripts need the documented data-md-consent markup. JavaScript-inserted trackers and embeds need their own integration.

= Does this guarantee legal compliance? =

No. Consent depends on your site's configuration, content and third-party integrations. Test the actual requests and cookies on your site.

= What data is stored? =

The first-party md_consent cookie stores a random consent ID, policy version, timestamp and category choices. When logging is enabled, those choices and the submission time are stored on your WordPress server. No IP address, user agent or WordPress user ID is stored in the consent log. Rate limiting temporarily stores counters keyed by a salted hash of the client IP (IPv6 grouped by /64). The plugin sends no telemetry. Third-party embeds may contact their provider after the visitor allows them.

= How long is data kept? =

Consent lasts 365 days by default. Log records are retained for 1095 days by default; both values can be changed in settings. Deleting the plugin retains data unless you opt into deletion as described below.

== Changelog ==

= __VERSION__ =
* Initial standalone release.
