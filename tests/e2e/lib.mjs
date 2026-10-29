// Shared helpers for the e2e specs. run.mjs sets MORTAR_CONSENT_E2E_URL; the
// environment (wp-env or DDEV) and its WP-CLI come from env.mjs.
import { mkdirSync } from 'node:fs';
import { chromium } from 'playwright';
import { env, wp } from './env.mjs';

export { wp };
export const BASE = env.url.replace(/\/$/, '');
export const FIXTURES = env.fixtures;
export const ARTIFACTS = new URL('./artifacts/', import.meta.url).pathname;
mkdirSync(ARTIFACTS, { recursive: true });

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

export const ready = (page) => page.waitForFunction(() => window.mortarConsent);
