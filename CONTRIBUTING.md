# Contributing

Thanks for helping. Bug reports, fixes and translations are all welcome.

- **Bugs and ideas:** open an issue with your WordPress and PHP versions, the theme and other plugins involved, and what you expected to happen.
- **Security problems:** do not open an issue. Follow [SECURITY.md](SECURITY.md).
- **Translations:** use [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/mortar-consent/) once the plugin is listed there.
- **Support questions** from site owners belong in the plugin's WordPress.org support forum.

## Pull requests

1. Set up the tools described under **Development** in the [README](README.md).
2. Keep changes focused, and add or update tests for behaviour you change.
3. Run `composer check` and `composer format`. If you changed WordPress-facing code (`src/WordPress/`), also run `composer test:integration`; if you touched the runtime, the settings screen or anything visitors see, run the browser tests too.
4. If you edited `assets/consent.js` or the settings app in `admin/`, run `pnpm build` and commit the built `assets/consent.min.js` or `assets/admin/` with it. CI fails if they are out of date.

CI runs the same checks on every pull request.

### Ground rules

- **Fair choices.** "Reject all" must stay as prominent as "Accept all", and nothing optional may be on by default.
- **No outside calls.** The plugin must never contact a third party or send telemetry.
- **Fail closed.** If configuration is invalid or something is unknown, block rather than allow.
- **Cache-safe output.** Visitor-facing markup must be identical for every visitor.
- **Translatable and escaped.** Every visible string goes through the `mortar-consent` text domain, and every output is escaped.
- **Accessible.** Changes to the banner, dialog or placeholders must keep the axe checks in the browser tests passing.

## Releasing

Maintainers only. Releases are built and deployed by `.github/workflows/release.yml` when a version tag is pushed.

1. Set the new version in the `mortar-consent.php` header and as `Stable tag` in `readme.txt`. Add it to the readme changelog (and upgrade notice if needed) and to `CHANGELOG.md` as a dated entry, `## [X.Y.Z] - YYYY-MM-DD`; that entry becomes the GitHub release notes.
2. Check `Tested up to` in `readme.txt` against the WordPress version the browser tests ran on.
3. Run `bin/build-plugin` and run the official [Plugin Check](https://wordpress.org/plugins/plugin-check/) against the zip on a clean site.
4. Optional: run the **Release** workflow by hand from the Actions tab. It is a dry run by default: it builds and stages everything but commits nothing, and keeps the zip as an artifact.
5. Commit, then tag and push:

   ```sh
   git tag -a vX.Y.Z -m "Mortar Consent X.Y.Z"
   git push origin vX.Y.Z
   ```

   The workflow checks the tag matches the header and readme, builds the zip, deploys it to WordPress.org (SVN `trunk`, `tags/X.Y.Z` and the listing artwork) and creates the GitHub release with the zip attached.

To change only the listing between releases (artwork, description, FAQ, `Tested up to`), run **Update WordPress.org listing** from the Actions tab. It refuses to run if `readme.txt` already describes an unreleased version.

Both workflows need the repository secrets `SVN_USERNAME` and `SVN_PASSWORD` (the SVN password from the WordPress.org profile's Account & Security page).
