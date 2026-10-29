// Renders the real admin app with WordPress's own script bundles on a
// harness page (no sign-in needed), answers its REST calls from data
// captured with WP-CLI, then sends what it saves through the real server
// validation.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { BASE, ARTIFACTS, wp, launch, context } from './lib.mjs';

const fixture = (name) => `/var/www/html/web/app/mu-plugins/mortar-consent/tests/e2e/fixture/${name}`;

let browser, page, errors;
let putBody = null;

before(async () => {
	const { tags, settings } = JSON.parse(wp(['eval-file', fixture('admin-harness.php')]));
	browser = await launch();
	({ page, errors } = await context(browser, { viewport: { width: 1200, height: 900 } }));
	page.on('console', (m) => {
		if (m.type() === 'error') {
			errors.push(m.text());
		}
	});
	const split = tags.indexOf('<script');
	await page.route(`${BASE}/mortar-consent-e2e-admin`, (route) => route.fulfill({
		contentType: 'text/html',
		body: `<!doctype html><html><head><meta charset="utf-8">${tags.slice(0, split)}</head><body class="wp-admin wp-core-ui"><div class="wrap"><h1>Cookie consent</h1><div id="mortar-consent-admin"></div></div>${tags.slice(split)}</body></html>`,
	}));
	await page.route(/mortar-consent(\/|%2F)v1(\/|%2F)settings/, (route) => {
		if (route.request().method() !== 'GET') {
			putBody = JSON.parse(route.request().postData());
			return route.fulfill({ json: { ...settings, effective: putBody.settings } });
		}
		return route.fulfill({ json: settings });
	});
	await page.route(/mortar-consent(\/|%2F)v1(\/|%2F)records/, (route) => route.fulfill({ json: {
		summary: { 'accept-all': 12, 'reject-all': 7, custom: 3 },
		records: [{ id: 1, consentId: '3f1c2a9e-8b7d-4c2e-9f10-2a3b4c5d6e7f', policyVersion: 1, granted: ['analytics'], method: 'custom', createdAt: '2026-10-26T18:01:40Z' }],
	} }));
	await page.goto(`${BASE}/mortar-consent-e2e-admin`);
	await page.waitForSelector('.mortar-consent-admin');
});

after(async () => {
	await browser?.close();
});

test('general tab edits lifetime and bumps the policy version', async () => {
	await page.getByLabel('Consent lasts (days)').fill('180');
	page.once('dialog', (d) => d.accept());
	await page.getByRole('button', { name: 'Ask all visitors again' }).click();
	assert.match(await page.textContent('.mortar-consent-version'), /Version \d+\s*Visitors asked again on save/);
	await page.screenshot({ path: `${ARTIFACTS}/admin-general.png`, fullPage: true });
});

test('categories: add a cookie and a category', async () => {
	await page.getByRole('tab', { name: 'Categories & cookies' }).click();
	await page.getByRole('button', { name: /^Analytics analytics/ }).click();
	await page.getByRole('button', { name: 'Add cookie' }).first().click();
	const row = page.locator('tbody tr').last();
	await row.getByLabel('name').fill('_ga_*');
	await row.getByLabel('provider').fill('Google');
	await page.getByLabel('ID', { exact: true }).fill('social');
	await page.getByRole('button', { name: 'Add category' }).click();
});

test('scripts and integrations tabs', async () => {
	await page.getByRole('tab', { name: 'Scripts' }).click();
	await page.getByRole('button', { name: 'Add script' }).click();
	await page.getByLabel('Script handle').fill('google-analytics');
	await page.getByRole('checkbox', { name: 'Analytics' }).check();
	await page.getByRole('tab', { name: 'Integrations' }).click();
	await page.getByLabel('Send consent signals to Google tags').check();
	await page.getByLabel('Embeds (YouTube, Vimeo, maps…) need').selectOption('marketing');
	await page.screenshot({ path: `${ARTIFACTS}/admin-integrations.png`, fullPage: true });
});

test('removing a category clears every reference to it', async () => {
	await page.getByRole('tab', { name: 'Categories & cookies' }).click();
	await page.getByRole('button', { name: /^Marketing marketing/ }).click();
	await page.getByRole('button', { name: 'Remove category' }).click();
});

test('wording and consent log tabs', async () => {
	await page.getByRole('tab', { name: 'Wording' }).click();
	await page.getByLabel('"Accept all" button').fill('Allow all');
	assert.equal(await page.getByRole('button', { name: 'Reset to default' }).count(), 1);
	await page.getByRole('tab', { name: 'Consent log' }).click();
	await page.locator('.mortar-consent-stat', { hasText: 'Accepted all' }).getByText('12', { exact: false }).waitFor();
});

test('saved settings have the expected shape and pass server validation', async () => {
	await page.getByRole('button', { name: 'Save settings' }).click();
	await page.waitForSelector('text=Settings saved.');
	const s = putBody.settings;
	assert.equal(s.lifetime_days, 180);
	assert.deepEqual(s.scripts, { 'google-analytics': ['analytics'] });
	assert.equal(s.texts.accept_all, 'Allow all');
	assert.deepEqual(s.categories.find((c) => c.id === 'analytics').cookies, [{ name: '_ga_*', provider: 'Google', purpose: '', duration: '' }]);
	assert.ok(s.categories.some((c) => c.id === 'social'));
	assert.ok(!s.categories.some((c) => c.id === 'marketing'));
	assert.equal(s.consent_mode.enabled, true);
	assert.deepEqual(s.consent_mode.signals.ad_storage, []);
	assert.equal(s.embeds.category, '');
	assert.deepEqual(s.gpc_categories, []);
	assert.deepEqual(s.wp_consent_api.marketing, []);

	const result = JSON.parse(wp(['eval-file', fixture('validate-settings.php')], JSON.stringify(putBody)));
	assert.equal(result.status, 200, JSON.stringify(result.data));
	assert.deepEqual(errors.filter((e) => !e.includes('favicon')), []);
});
