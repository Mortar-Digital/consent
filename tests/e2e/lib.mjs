// Shared helpers for the e2e specs. Environment (set by run.mjs):
//   MD_CONSENT_E2E_URL   site home URL, e.g. https://example.dev.skelton.digital
//   MD_CONSENT_E2E_SITE  path to the site checkout (for `ddev wp`)
import { spawnSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { chromium } from 'playwright';

export const BASE = (process.env.MD_CONSENT_E2E_URL || '').replace(/\/$/, '');
export const SITE = process.env.MD_CONSENT_E2E_SITE || '';
export const ARTIFACTS = new URL('./artifacts/', import.meta.url).pathname;
mkdirSync(ARTIFACTS, { recursive: true });

if (!BASE || !SITE) {
	throw new Error('Run the e2e suite through tests/e2e/run.mjs (it sets MD_CONSENT_E2E_URL and MD_CONSENT_E2E_SITE).');
}

/** Runs WP-CLI in the site's DDEV project. */
export function wp(args, input) {
	const result = spawnSync('ddev', ['wp', ...args], { cwd: SITE, input, encoding: 'utf8', maxBuffer: 32 * 1024 * 1024 });
	if (result.status !== 0) {
		throw new Error(`ddev wp ${args.join(' ')} failed: ${result.stderr || result.stdout}`);
	}
	return result.stdout.trim();
}

export const launch = () => chromium.launch();

/** A fresh browser context that never reaches third parties. */
export async function context(browser, { gpc = false, viewport = { width: 1280, height: 900 } } = {}) {
	const ctx = await browser.newContext({ ignoreHTTPSErrors: true, viewport });
	const thirdParty = [];
	await ctx.route((url) => !url.href.startsWith(BASE), (route) => {
		thirdParty.push(route.request().url());
		route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>stub</title>' });
	});
	await ctx.addInitScript((gpcOn) => {
		window.__wpConsent = [];
		window.wp_set_consent = (category, value) => window.__wpConsent.push([category, value]);
		if (gpcOn) {
			Object.defineProperty(Navigator.prototype, 'globalPrivacyControl', { get: () => true });
		}
	}, gpc);
	const page = await ctx.newPage();
	const errors = [];
	page.on('pageerror', (e) => errors.push(e.message));
	const cookies = async () => Object.fromEntries((await ctx.cookies()).map((c) => [c.name, c.value]));
	return { ctx, page, errors, thirdParty, cookies };
}

export const ready = (page) => page.waitForFunction(() => window.mdConsent);
