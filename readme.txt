=== Mortar Consent ===
Contributors: mortardigital
Tags: cookie consent, gdpr, cookies, privacy, consent mode
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Cookie consent that is opt-in, lets visitors choose and change their mind, and never calls third-party services.

== Description ==

Mortar Consent asks visitors before your site sets optional cookies or loads third-party content, and keeps their choice. It runs entirely on your own WordPress site: no account, no external service and no telemetry.

**Fair by design**

* "Accept all", "Reject all" and "Manage preferences" sit side by side with the same styling.
* Nothing optional is pre-ticked, and the page stays usable while the banner is open.
* Visitors choose per category (functional, analytics, marketing, or your own) and can change their mind at any time from a "Cookie settings" button or link.
* Visitors are asked again when your cookie policy version changes or their consent expires.

**Blocks what needs consent**

* Scripts wait for consent: pick them by script handle in the settings, or mark hand-written tags with `type="text/plain" data-mortar-consent="analytics"`. Scripts that depend on a blocked script wait too.
* YouTube, Vimeo, Google Maps, Spotify and other embeds become click-to-load placeholders. Embeds from unknown third-party hosts are held back as well.
* When a visitor withdraws consent, known cookies in that category are deleted.

**Works with the rest of your stack**

* Google Consent Mode v2, with defaults set before any Google tag runs.
* Global Privacy Control: when the browser sends it, "Accept all" leaves marketing off.
* The WP Consent API, so other plugins can follow the visitor's choice.
* Safe with full-page caching: every visitor gets the same HTML.

**Records and declares**

* A consent log stores each choice with a random consent ID, the policy version and the time, but no IP address, browser details or user account. Look up a consent ID or export the log as CSV.
* A Cookie declaration block lists every category and cookie for your cookie policy page.
* Suggested privacy policy text is added to Settings → Privacy → Policy Guide.

**Accessible and light**

* Built on the native dialog element, keyboard operable and tested against WCAG 2.2 AA.
* The visitor script is under 5 KB gzipped, with no dependencies.
* Restyle it from your theme with CSS custom properties, or replace the styles entirely.

Mortar Consent is a tool, not legal advice. It does not find trackers by itself, and you are responsible for configuring it to match the cookies and services your site uses.

Mortar Consent is made by [Mortar Digital](https://mortar.digital/), a WordPress studio that designs, builds, hosts and looks after WordPress sites. It runs on every site we build.

* [Documentation](https://mortar.digital/plugins/consent/docs/)
* [Source code and developer hooks](https://github.com/Mortar-Digital/consent)

== Installation ==

1. Install Mortar Consent from Plugins → Add New, or upload the zip under Plugins → Add New → Upload Plugin, then activate it.
2. Go to Settings → Cookie consent.
3. Under **Categories & cookies**, list the cookies your site sets. Under **Scripts**, add the handles of the tracking scripts that need consent.
4. Set your cookie policy link, and add the **Cookie declaration** block to your cookie policy page.
5. Visit your site in a private window and test accepting, rejecting and changing your choice. Check your browser's developer tools to confirm that no optional cookies or requests happen before consent.

== Frequently Asked Questions ==

= Does it find and block trackers automatically? =

No. You tell it which scripts need consent. Scripts added through WordPress (with `wp_enqueue_script`) are blocked by handle; in your page source their tag has an id of `handle-js`. Scripts pasted into your theme need `type="text/plain"` and a `data-mortar-consent` attribute. Trackers that another script inserts later need that script to be blocked.

= Does it make my site GDPR compliant? =

No plugin can do that on its own. Mortar Consent gives visitors a fair choice and keeps a record of it, but compliance depends on your configuration, your content and the services you use. Test the cookies and requests your site actually makes.

= Does it work with page caching? =

Yes. The banner markup is the same for every visitor, and the visitor's browser decides what to show and load. Plugins that combine or rewrite script tags can undo the blocking, so exclude blocked scripts from them.

= How do visitors change their mind? =

From the floating "Cookie settings" button (which you can turn off), the Cookie settings button block, the `[mortar_consent_settings]` shortcode, or any link to `#cookie-settings`, such as a menu item.

= How does it work with Google Analytics and Google Ads? =

Turn on Google Consent Mode v2 under Integrations. To load nothing until consent (basic mode), also add the Google tag's script handle under Scripts. To let Google send cookieless pings before consent (advanced mode), leave the tag unblocked.

= What data does it store? =

The `mortar_consent` cookie on the visitor's device holds a random consent ID, the policy version, the time of the choice and the allowed categories. When the consent log is on, the same details and how the choice was made are stored on your server. No IP address, browser details or WordPress user ID are stored. To limit abuse, the log endpoint briefly counts requests under a keyed hash of the IP address, which expires within about ten minutes. The plugin sends nothing to any outside service.

= How long is data kept? =

Consent lasts 365 days by default, and log records 1095 days (three years). You can change both in the settings. Older records are deleted daily.

= Can visitors export or erase their consent record? =

Consent records cannot be linked to a person, email address or user account, so WordPress's personal data export and erase tools have nothing to find. A visitor can quote the consent ID shown in their cookie preferences, and you can look it up in the Consent log tab.

= What happens when I delete the plugin? =

Its scheduled clean-up task is removed. Your settings and the consent log are kept, because you may need the log as evidence. To delete them as well, add `define('MORTAR_CONSENT_DELETE_DATA_ON_UNINSTALL', true);` to wp-config.php before deleting the plugin. On multisite this applies to every site.

= Does it support multisite? =

Yes. Each site has its own settings and consent log.

= Can I change how it looks? =

Yes. Set the `--mortar-consent-*` CSS custom properties in your theme, for example `--mortar-consent-button-bg`, or turn off the built-in styles with the `mortar_consent_load_styles` filter. Keep the three choice buttons equally prominent.

== Screenshots ==

1. The consent banner: Accept all, Reject all and Manage preferences carry equal weight. Shown here styled by the Mortar Digital theme.
2. The preferences dialog, with a switch per category.
3. A YouTube embed held back until the visitor loads it or allows the category.
4. Scripts: choose which registered scripts wait for consent, or mark up hand-written tags.
5. Integrations: Google Consent Mode v2 signals mapped to your categories.
6. The consent log: totals for the last 30 days, records by consent ID and a CSV export.
7. The preferences dialog on a phone.

== Changelog ==

= 0.1.0 =
* First public release.

== Upgrade Notice ==

= 0.1.0 =
First public release.
