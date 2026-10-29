#!/usr/bin/env node
// End-to-end suite against a local DDEV site:
//
//   MD_CONSENT_E2E_SITE=../../sites/mortar-digital pnpm test:e2e
//
// Installs a temporary fixture MU-plugin and test pages, runs each spec
// with a clean rate limiter, then removes everything it added (including
// consent records written during the run). The site URL comes from
// MD_CONSENT_E2E_URL or the site's .env WP_HOME.
import { spawnSync } from 'node:child_process';
import { copyFileSync, existsSync, readFileSync, rmSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const site = resolve(process.env.MD_CONSENT_E2E_SITE || resolve(here, '../../../../sites/mortar-digital'));
if (!existsSync(resolve(site, 'web/app/mu-plugins/md-consent'))) {
	console.error(`No consent package installed in ${site}. Set MD_CONSENT_E2E_SITE to a site checkout.`);
	process.exit(1);
}
const env = readFileSync(resolve(site, '.env'), 'utf8');
const url = process.env.MD_CONSENT_E2E_URL || (env.match(/^WP_HOME=['"]?([^'"\n]+)/m) || [])[1];
if (!url) {
	console.error('Set MD_CONSENT_E2E_URL or WP_HOME in the site .env.');
	process.exit(1);
}

const wp = (...args) => {
	const r = spawnSync('ddev', ['wp', ...args], { cwd: site, encoding: 'utf8' });
	if (r.status !== 0) {
		throw new Error(`ddev wp ${args.join(' ')}: ${r.stderr || r.stdout}`);
	}
	return r.stdout.trim();
};

const muPlugin = resolve(site, 'web/app/mu-plugins/zz-md-consent-e2e.php');
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
	createPage('md-consent-e2e-declaration', 'Consent e2e declaration', '<!-- wp:md-consent/cookie-declaration /-->');
	createPage('md-consent-e2e-embeds', 'Consent e2e embeds', readFileSync(resolve(here, 'fixture/embeds.html'), 'utf8'));
	createPage('md-consent-e2e-hardening', 'Consent e2e hardening', readFileSync(resolve(here, 'fixture/hardening.html'), 'utf8'));

	for (const spec of ['visitor', 'integrations', 'admin']) {
		wp('transient', 'delete', '--all');
		const r = spawnSync(process.execPath, ['--test', '--test-reporter=spec', resolve(here, `${spec}.test.mjs`)], {
			stdio: 'inherit',
			env: { ...process.env, MD_CONSENT_E2E_URL: url, MD_CONSENT_E2E_SITE: site },
		});
		failed ||= r.status !== 0;
	}
} finally {
	rmSync(muPlugin, { force: true });
	if (pages.length) {
		wp('post', 'delete', ...pages, '--force');
	}
	wp('eval', `global $wpdb; $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE created_at >= %s', $wpdb->prefix . 'md_consent_log', ${JSON.stringify(started)}));`);
	wp('transient', 'delete', '--all');
}
console.log(failed ? '\nE2E: failures above.' : '\nE2E: all specs passed. Screenshots in tests/e2e/artifacts/.');
process.exit(failed ? 1 : 0);
