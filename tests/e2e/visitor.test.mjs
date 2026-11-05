// Visitor flows: banner, choices, script gating, withdrawal, robustness,
// declaration block, consent log and endpoint abuse. Steps share one page
// and run in order.
import { test, before, after } from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { BASE, ARTIFACTS, wp, launch, context, ready } from './lib.mjs';

const require = createRequire(import.meta.url);
const AXE = require.resolve('axe-core');
const HOME = `${BASE}/`;

let browser, ctx, page, errors, cookies;
const trackerRequests = [];
const records = [];
let consentId;

const log = () => page.evaluate(() => window.mdTestLog ?? null);
const axe = async (selector) => {
	await page.addScriptTag({ path: AXE });

	return page.evaluate(
		async (s) =>
			(await window.axe.run(s, { runOnly: ['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa'] })).violations.map(
				(v) => `${v.id}: ${v.nodes.length}`,
			),
		selector,
	);
};
const waitRecords = async (n) => {
	for (let i = 0; i < 50 && records.length < n; i++) {
		await page.waitForTimeout(100);
	}

	assert.equal(records.length, n, `expected ${n} consent records`);
};
// The record is posted with keepalive just before a reload, so check that
// the server stored it rather than waiting for a response the page may lose.
const storedFor = async (id, n) => {
	let count = 0;
	for (let t = 0; t < 50; t++) {
		count = Number(
			wp([
				'eval',
				`global $wpdb; echo (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE consent_id = %s', $wpdb->prefix . 'mortar_consent_log', ${JSON.stringify(id)}));`,
			]),
		);

		if (count >= n) {
			break;
		}

		await page.waitForTimeout(100);
	}
	return count;
};

before(async () => {
	browser = await launch();
	({ ctx, page, errors, cookies } = await context(browser));

	page.on('request', (r) => {
		if (r.url().includes('fixture/tracker.js')) {
			trackerRequests.push(r.url());
		}
	});

	// Count records when they are sent: the page often reloads straight after
	// a keepalive POST, so its response may never reach this page.
	page.on('request', (r) => {
		if (r.url().includes('mortar-consent/v1/records') && r.method() === 'POST') {
			records.push({ body: JSON.parse(r.postData() || 'null') });
		}
	});
});

after(async () => {
	await browser?.close();
});

test('first visit: banner shown, no optional scripts, requests or cookies, no a11y violations', async () => {
	await page.goto(HOME);
	await ready(page);

	assert.equal(await page.isVisible('#mortar-consent-banner'), true);
	assert.equal(await page.isVisible('.mortar-consent__reopen'), false);
	assert.deepEqual(trackerRequests, []);
	assert.equal(await log(), null);
	assert.equal(
		Object.keys(await cookies()).some((n) => n.startsWith('_sdtest') || n === 'mortar_consent'),
		false,
	);

	await page.waitForTimeout(400);
	await page.screenshot({ path: `${ARTIFACTS}/banner.png` });
	assert.deepEqual(await axe('#mortar-consent'), []);
});

test('reject all is remembered, loads nothing and is logged', async () => {
	await page.click('#mortar-consent-banner [data-mortar-consent-action="reject-all"]');
	const c = await cookies();

	assert.match(c.mortar_consent, /^1\|\d+\|\d+\|[0-9a-f-]{36}\|$/);

	consentId = c.mortar_consent.split('|')[3];
	assert.equal(await page.isVisible('#mortar-consent-banner'), false);
	assert.equal(await page.isVisible('.mortar-consent__reopen'), true);

	await page.reload();
	await ready(page);

	assert.equal(await page.isVisible('#mortar-consent-banner'), false);
	assert.deepEqual(trackerRequests, []);
	assert.equal(await log(), null);

	await waitRecords(1);

	assert.equal(await storedFor(consentId, 1), 1);
	assert.deepEqual(records[0].body, {
		consentId,
		policyVersion: records[0].body.policyVersion,
		granted: [],
		method: 'reject-all',
	});
});

test('granting analytics runs gated scripts in order; marketing stays blocked', async () => {
	await page.click('.mortar-consent__reopen');

	assert.equal(await page.isVisible('#mortar-consent-dialog'), true);
	assert.deepEqual(await axe('#mortar-consent-dialog'), []);
	assert.equal(await page.textContent('[data-mortar-consent-id]'), consentId, 'dialog shows the consent id');
	assert.equal(await page.isChecked('#mortar-consent-category-analytics'), false);

	await page.check('#mortar-consent-category-analytics');
	await page.click('#mortar-consent-dialog [data-mortar-consent-action="save"]');
	await page.waitForFunction(() => (window.mdTestLog ?? []).length >= 4);

	assert.deepEqual(await log(), ['before', 'external:number', 'after', 'external:number']);

	const c = await cookies();
	assert.equal(c.mortar_consent.split('|')[3], consentId, 'consent id is kept');
	assert.equal(c.mortar_consent.split('|')[4], 'analytics');
	assert.equal(c._sdtest_a, '1');

	await waitRecords(2);

	assert.deepEqual(records[1].body.granted, ['analytics']);
	assert.equal(records[1].body.method, 'custom');
});

test('stored consent activates scripts on load', async () => {
	await page.reload();
	await page.waitForFunction(() => (window.mdTestLog ?? []).length >= 4);

	assert.deepEqual(await log(), ['before', 'external:number', 'after', 'external:number']);
});

test('withdrawal reloads, removes owned cookies, stops scripts and is logged', async () => {
	await page.click('.mortar-consent__reopen');

	assert.equal(await page.isChecked('#mortar-consent-category-analytics'), true);

	await page.uncheck('#mortar-consent-category-analytics');
	await Promise.all([
		page.waitForNavigation(),
		page.click('#mortar-consent-dialog [data-mortar-consent-action="save"]'),
	]);
	await ready(page);

	const c = await cookies();
	assert.equal(c._sdtest_a, undefined);
	assert.equal(c._sdtest_b, undefined);
	assert.equal(await log(), null);

	await waitRecords(3);

	assert.deepEqual(records[2].body.granted, []);
	assert.equal(await storedFor(consentId, 3), 3);
});

test('Escape closes the dialog without saving and returns focus', async () => {
	const before = (await cookies()).mortar_consent;
	await page.click('.mortar-consent__reopen');
	await page.keyboard.press('Escape');

	assert.equal(await page.isVisible('#mortar-consent-dialog'), false);
	assert.equal((await cookies()).mortar_consent, before);
	assert.equal(await page.evaluate(() => document.activeElement?.className), 'mortar-consent__reopen');
});

test('clicking the backdrop closes the dialog without saving; clicks inside do not', async () => {
	const before = (await cookies()).mortar_consent;
	await page.click('.mortar-consent__reopen');
	await page.click('#mortar-consent-dialog .mortar-consent__title');

	assert.equal(await page.isVisible('#mortar-consent-dialog'), true, 'a click inside keeps it open');

	await page.mouse.click(5, 5);

	assert.equal(await page.isVisible('#mortar-consent-dialog'), false);
	assert.equal((await cookies()).mortar_consent, before);
	assert.equal(await page.evaluate(() => document.activeElement?.className), 'mortar-consent__reopen');
});

test('malformed, tampered or expired cookies show the banner and load nothing', async () => {
	for (const bad of ['1|1|1|not-a-uuid|analytics', `1|1|1000|${consentId}|analytics.marketing`, 'script']) {
		await ctx.addCookies([{ name: 'mortar_consent', value: bad, url: HOME }]);
		await page.reload();
		await ready(page);

		assert.equal(await page.isVisible('#mortar-consent-banner'), true, `banner for ${bad}`);
		assert.equal(await log(), null);
	}
});

test('accept all runs everything; #cookie-settings opens preferences', async () => {
	await page.click('#mortar-consent-banner [data-mortar-consent-action="accept-all"]');
	await page.waitForFunction(() => (window.mdTestLog ?? []).includes('marketing-inline'));

	assert.equal((await cookies()).mortar_consent.split('|')[4], 'functional.analytics.marketing');

	await page.goto(`${HOME}#cookie-settings`);
	await ready(page);

	assert.equal(await page.isVisible('#mortar-consent-dialog'), true);
});

test('mobile: banner and dialog fit without horizontal scrolling', async () => {
	const mobile = await context(browser, { viewport: { width: 375, height: 812 } });
	await mobile.page.goto(HOME);
	await ready(mobile.page);
	await mobile.page.waitForTimeout(400);
	await mobile.page.screenshot({ path: `${ARTIFACTS}/mobile-banner.png` });
	await mobile.page.click('[data-mortar-consent-action="manage"]');
	await mobile.page.screenshot({ path: `${ARTIFACTS}/mobile-dialog.png` });

	assert.equal(await mobile.page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);

	await mobile.ctx.close();
});

test('cookie declaration lists categories and cookies, escapes text, opens preferences', async () => {
	await page.goto(`${BASE}/mortar-consent-e2e-declaration/`);
	await ready(page);

	const headings = await page.evaluate(() =>
		[...document.querySelectorAll('.mortar-consent-declaration h3')].map((h) => h.textContent.trim()),
	);

	assert.deepEqual(headings, ['Strictly necessary (Always on)', 'Functional', 'Analytics', 'Marketing']);
	assert.equal(
		await page.textContent('.mortar-consent-declaration tbody tr:has(code:text("_sdtest_*")) td'),
		'Test & Co <b>',
	);

	await page.click('.mortar-consent-declaration [data-mortar-consent-open]');

	assert.equal(await page.isVisible('#mortar-consent-dialog'), true);

	await page.keyboard.press('Escape');
});

test('records endpoint rejects oversized bodies and rate limits', async () => {
	const abuse = await page.evaluate(async () => {
		const url = JSON.parse(document.getElementById('mortar-consent-config').textContent).log;
		const policyVersion = JSON.parse(document.getElementById('mortar-consent-config').textContent).policyVersion;
		const post = (body) =>
			fetch(url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(body),
				credentials: 'omit',
			}).then((r) => r.status);
		const valid = { consentId: crypto.randomUUID(), policyVersion, granted: [], method: 'reject-all' };
		const big = await post({ ...valid, granted: Array(300).fill('analytics') });
		const statuses = [];
		for (let i = 0; i < 25; i++) {
			statuses.push(await post(valid));
		}
		return { big, statuses };
	});

	assert.equal(abuse.big, 413);
	assert.ok(abuse.statuses.includes(201), JSON.stringify(abuse.statuses));
	assert.equal(abuse.statuses.at(-1), 429, JSON.stringify(abuse.statuses));
	assert.deepEqual(errors, []);
});
