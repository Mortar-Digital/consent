#!/usr/bin/env node
// End-to-end suite. By default it runs against wp-env (start it first):
//
//   pnpm exec wp-env start && pnpm test:e2e
//
// or against a local DDEV site that loads the plugin as an MU-plugin:
//
//   MORTAR_CONSENT_E2E_ENV=ddev MORTAR_CONSENT_E2E_SITE=../sites/mortar-digital pnpm test:e2e
//
// Installs a temporary fixture MU-plugin and test pages, runs each spec
// with a clean rate limiter, then removes everything it added (including
// consent records written during the run). See env.mjs for the URL and
// WP-CLI each environment uses.
import { spawnSync } from 'node:child_process';
import { copyFileSync, mkdirSync, readFileSync, rmSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { env, wp as cli } from './env.mjs';

const here = dirname(fileURLToPath(import.meta.url));
const wp = (...args) => cli(args);

if (env.name === 'wp-env') {
	// wp-env: activate the plugin as a site owner would, with pretty permalinks for the test pages.
	wp('plugin', 'activate', 'mortar-consent');
	wp('rewrite', 'structure', '/%postname%/', '--hard');
}

mkdirSync(env.muPlugins, { recursive: true });
const muPlugin = resolve(env.muPlugins, 'zz-mortar-consent-e2e.php');
const pages = [];
const started = wp('eval', 'echo gmdate("Y-m-d H:i:s");');
let failed = false;

try {
	copyFileSync(resolve(here, 'fixture/mu-plugin.php'), muPlugin);
	const createPage = (slug, title, content) => {
		const existing = wp('post', 'list', '--post_type=page', `--name=${slug}`, '--field=ID', '--post_status=any');
		if (existing) {
			wp('post', 'delete', ...existing.split(/\s+/), '--force');
		}
		pages.push(wp('post', 'create', '--post_type=page', '--post_status=publish', `--post_title=${title}`, `--post_name=${slug}`, `--post_content=${content}`, '--porcelain'));
	};
	createPage('mortar-consent-e2e-declaration', 'Consent e2e declaration', '<!-- wp:mortar-consent/cookie-declaration /-->');
	createPage('mortar-consent-e2e-embeds', 'Consent e2e embeds', readFileSync(resolve(here, 'fixture/embeds.html'), 'utf8'));
	createPage('mortar-consent-e2e-hardening', 'Consent e2e hardening', readFileSync(resolve(here, 'fixture/hardening.html'), 'utf8'));

	for (const spec of ['visitor', 'integrations', 'admin']) {
		wp('transient', 'delete', '--all');
		const r = spawnSync(process.execPath, ['--test', '--test-reporter=spec', resolve(here, `${spec}.test.mjs`)], {
			stdio: 'inherit',
			env: { ...process.env, MORTAR_CONSENT_E2E_URL: env.url },
		});
		failed ||= r.status !== 0;
	}
} finally {
	rmSync(muPlugin, { force: true });
	if (pages.length) {
		wp('post', 'delete', ...pages, '--force');
	}
	wp('eval', `global $wpdb; $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE created_at >= %s', $wpdb->prefix . 'mortar_consent_log', ${JSON.stringify(started)}));`);
	wp('transient', 'delete', '--all');
}
console.log(failed ? '\nE2E: failures above.' : '\nE2E: all specs passed. Screenshots in tests/e2e/artifacts/.');
process.exit(failed ? 1 : 0);
