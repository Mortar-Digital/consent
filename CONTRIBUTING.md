# Contributing

Thanks for helping. Bug reports, fixes and translations are all welcome.

- **Bugs and ideas:** open an issue with your WordPress and PHP versions, the theme and other plugins involved, and what you expected to happen.
- **Security problems:** do not open an issue. Follow [SECURITY.md](SECURITY.md).
- **Translations:** use [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/mortar-consent/) once the plugin is listed there.
- **Support questions** from site owners belong in the plugin's WordPress.org support forum.

## Pull requests

1. Set up the tools described under **Development** in the [README](README.md).
2. Keep changes focused, and add or update tests for behaviour you change.
3. Run `composer check`, and the browser tests if you touched the runtime, the settings screen or anything visitors see.
4. If you edited `assets/consent.js`, run `pnpm build` and commit `assets/consent.min.js` with it.

CI runs the same checks on every pull request.

### Ground rules

- **Fair choices.** "Reject all" must stay as prominent as "Accept all", and nothing optional may be on by default.
- **No outside calls.** The plugin must never contact a third party or send telemetry.
- **Fail closed.** If configuration is invalid or something is unknown, block rather than allow.
- **Cache-safe output.** Visitor-facing markup must be identical for every visitor.
- **Translatable and escaped.** Every visible string goes through the `mortar-consent` text domain, and every output is escaped.
- **Accessible.** Changes to the banner, dialog or placeholders must keep the axe checks in the browser tests passing.

## Releasing

Maintainers only.

1. Set the new version in the `mortar-consent.php` header and as `Stable tag` in `readme.txt`. Add the release to the readme changelog (and upgrade notice if needed) and to `CHANGELOG.md`.
2. Check `Tested up to` in `readme.txt` against the WordPress version the browser tests ran on.
3. Run `bin/build-plugin`. It fails if the readme and header versions disagree or the minified runtime is stale.
4. Install the zip on a clean site and run the official [Plugin Check](https://wordpress.org/plugins/plugin-check/) against it.
5. Tag the release (`vX.Y.Z`) and push the tag.
