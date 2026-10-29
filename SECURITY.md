# Security policy

## Reporting a vulnerability

Please report security problems privately through GitHub: open the repository's **Security** tab and choose **Report a vulnerability**. Do not open a public issue or support forum topic.

Include the plugin version, the steps to reproduce, and what an attacker could achieve. We will acknowledge your report, keep you updated, and agree a disclosure date with you once a fix is ready.

## Supported versions

Security fixes go into the latest release only. Please update before reporting.

## Scope

Of particular interest:

- Any way to run a script or load an embed before the visitor has consented to its category.
- Cross-site scripting through settings, blocks, shortcodes, embeds or the consent log.
- Bypassing the capability checks on the settings screen, REST routes or CSV export.
- Ways to store personal data such as IP addresses in the consent log.

The public consent record endpoint accepts anonymous submissions by design, within its validation and rate limits. Adding records under made-up consent IDs is a documented limitation, not a vulnerability.
