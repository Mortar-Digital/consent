// Consent Mode v2, click-to-load embeds, WP Consent API mirroring and
// Global Privacy Control. Third-party requests are stubbed by lib.mjs.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { BASE, ARTIFACTS, launch, context, ready } from './lib.mjs';

const EMBEDS = `${BASE}/mortar-consent-e2e-embeds/`;
const DENIED = {
	ad_storage: 'denied',
	ad_user_data: 'denied',
	ad_personalization: 'denied',
	analytics_storage: 'denied',
	functionality_storage: 'granted',
	personalization_storage: 'denied',
	security_storage: 'granted',
};

let browser, page, errors, thirdParty, cookies;
const consentEvents = () =>
	page.evaluate(() =>
		(window.dataLayer || []).filter((e) => e[0] === 'consent').map((e) => [e[1], JSON.parse(JSON.stringify(e[2]))]),
	);
const consentCookie = async () => (await cookies()).mortar_consent;

before(async () => {
	browser = await launch();
	({ page, errors, thirdParty, cookies } = await context(browser));
});

after(async () => {
	await browser?.close();
});

test('first visit: Consent Mode defaults denied before any tag; embeds are placeholders', async () => {
	await page.goto(EMBEDS);
	await ready(page);

	assert.equal(await page.evaluate(() => document.head.querySelector('script')?.id), 'mortar-consent-mode');

	const [first] = await consentEvents();
	assert.deepEqual(first, ['default', { ...DENIED, wait_for_update: 500 }]);
	assert.equal(await page.locator('.mortar-consent-embed').count(), 2);
	assert.equal(await page.locator('iframe').count(), 0);
	assert.deepEqual(thirdParty, []);
	assert.match(await page.textContent('.mortar-consent-embed__notice'), /hosted by YouTube/);

	await page.waitForTimeout(400);
	await page.screenshot({ path: `${ARTIFACTS}/embeds.png`, fullPage: true });
});

test('"Load this content" loads one embed, focuses it and stores nothing', async () => {
	await page.locator('.mortar-consent-embed').first().getByRole('button', { name: 'Load this content' }).click();
	await page.waitForSelector('#raw-yt');

	assert.equal(await page.evaluate(() => document.activeElement?.id), 'raw-yt');
	assert.equal(await page.locator('.mortar-consent-embed').count(), 1);
	assert.equal(thirdParty.length, 1);
	assert.equal(await consentCookie(), undefined);
});

test('"Always allow" grants the category, loads the rest and updates Consent Mode', async () => {
	await page
		.locator('.mortar-consent-embed')
		.getByRole('button', { name: /Always allow/ })
		.click();
	await page.waitForFunction(() => document.querySelectorAll('.mortar-consent-embed').length === 0);

	assert.equal((await consentCookie()).split('|')[4], 'functional');
	assert.equal(await page.isVisible('#mortar-consent-banner'), false);
	assert.deepEqual((await consentEvents()).at(-1), ['update', { ...DENIED, personalization_storage: 'granted' }]);
});

test('accept all: Consent Mode granted and WP Consent API set to allow', async () => {
	await page.click('.mortar-consent__reopen');
	await page.click('#mortar-consent-dialog [data-mortar-consent-action="accept-all"]');
	const last = (await consentEvents()).at(-1)[1];

	assert.equal(last.ad_storage, 'granted');
	assert.equal(last.analytics_storage, 'granted');

	const wp = await page.evaluate(() => Object.fromEntries(window.__wpConsent.slice(-5)));
	assert.deepEqual(wp, {
		functional: 'allow',
		preferences: 'allow',
		statistics: 'allow',
		'statistics-anonymous': 'allow',
		marketing: 'allow',
	});
});

test('returning visitor: head defaults already granted; embeds load directly', async () => {
	await page.reload();
	await ready(page);

	const [def] = await consentEvents();
	assert.equal(def[0], 'default');
	assert.equal(def[1].ad_storage, 'granted');
	assert.equal(def[1].analytics_storage, 'granted');
	assert.equal(await page.locator('.mortar-consent-embed').count(), 0);
	assert.deepEqual(errors, []);
});

test('GPC: notice shown, accept all leaves Marketing off, explicit opt-in still possible', async () => {
	const gpc = await context(browser, { gpc: true });
	const p = gpc.page;
	await p.goto(EMBEDS);
	await ready(p);

	assert.equal(await p.isVisible('#mortar-consent-banner [data-mortar-consent-gpc]'), true);
	assert.match(await p.textContent('#mortar-consent-banner [data-mortar-consent-gpc]'), /Global Privacy Control/);

	await p.click('#mortar-consent-banner [data-mortar-consent-action="accept-all"]');
	assert.equal((await gpc.cookies()).mortar_consent.split('|')[4], 'functional.analytics');

	await p.click('.mortar-consent__reopen');
	await p.check('#mortar-consent-category-marketing');
	await p.click('#mortar-consent-dialog [data-mortar-consent-action="save"]');
	assert.equal((await gpc.cookies()).mortar_consent.split('|')[4], 'functional.analytics.marketing');
	assert.deepEqual(gpc.errors, []);

	await gpc.ctx.close();
});

test('hardening: classic auto-embeds stay gated despite marker strings; unknown hosts gated; same-site left alone', async () => {
	const h = await context(browser);
	await h.page.goto(`${BASE}/mortar-consent-e2e-hardening/`);
	await ready(h.page);
	const providers = await h.page.evaluate(() =>
		[...document.querySelectorAll('.mortar-consent-embed')].map((e) => e.getAttribute('data-mortar-consent-provider')),
	);

	assert.deepEqual(providers.sort(), ['other', 'youtube']);
	assert.match(
		await h.page.textContent('[data-mortar-consent-provider="other"] .mortar-consent-embed__notice'),
		/player\.example-video\.test/,
	);
	assert.equal(await h.page.locator('#same-site').count(), 1, 'same-site iframe is not gated');
	assert.equal(await h.page.locator('iframe:not(#same-site)').count(), 0);
	assert.deepEqual(h.thirdParty, []);

	await h.ctx.close();
});

test('hardening: a look-alike "allow" button in post content cannot grant consent', async () => {
	const h = await context(browser);

	await h.page.goto(`${BASE}/mortar-consent-e2e-hardening/`);
	await ready(h.page);
	await h.page.click('#forged');
	await h.page.waitForTimeout(300);

	assert.equal((await h.cookies()).mortar_consent, undefined);
	assert.equal(await h.page.isVisible('#mortar-consent-banner'), true);

	await h.ctx.close();
});
