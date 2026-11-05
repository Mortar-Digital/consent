// The settings screen's pure form-state helpers (admin/settings.js).
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { fromDraft, isCategoryId, toDraft, toggle, toInt, withoutCategory } from '../../admin/settings.js';

const effective = {
	categories: [
		{ id: 'necessary', required: true },
		{ id: 'analytics', required: false },
		{ id: 'marketing', required: false },
	],
	scripts: { 'google-analytics': 'analytics', ads: ['analytics', 'marketing'] },
	consent_mode: { enabled: true, signals: { ad_storage: ['marketing'], analytics_storage: ['analytics'] } },
	wp_consent_api: { marketing: ['marketing'], statistics: 'analytics' },
	embeds: { category: 'marketing', providers: { youtube: 'marketing', vimeo: 'analytics' } },
	gpc_categories: ['marketing'],
};

test('toDraft turns the script map into editable rows, with single ids as lists', () => {
	assert.deepEqual(toDraft(effective).scripts, [
		{ handle: 'google-analytics', categories: ['analytics'] },
		{ handle: 'ads', categories: ['analytics', 'marketing'] },
	]);
});

test('fromDraft turns rows back into a map, trimming handles and dropping blank ones', () => {
	const draft = {
		...toDraft(effective),
		scripts: [
			{ handle: '  google-analytics ', categories: ['analytics'] },
			{ handle: '   ', categories: ['marketing'] },
		],
	};

	assert.deepEqual(fromDraft(draft).scripts, { 'google-analytics': ['analytics'] });
});

test('toDraft and fromDraft round-trip the settings', () => {
	const roundTrip = fromDraft(toDraft(effective));

	assert.deepEqual(roundTrip.scripts, { 'google-analytics': ['analytics'], ads: ['analytics', 'marketing'] });
	assert.deepEqual(roundTrip.consent_mode, effective.consent_mode);
});

test('withoutCategory removes a deleted category from every setting that refers to it', () => {
	const draft = withoutCategory(toDraft(effective), 'marketing');

	assert.deepEqual(
		draft.categories.map((c) => c.id),
		['necessary', 'analytics'],
	);
	assert.deepEqual(draft.scripts, [
		{ handle: 'google-analytics', categories: ['analytics'] },
		{ handle: 'ads', categories: ['analytics'] },
	]);
	assert.deepEqual(draft.consent_mode, {
		enabled: true,
		signals: { ad_storage: [], analytics_storage: ['analytics'] },
	});
	assert.deepEqual(draft.wp_consent_api, { marketing: [], statistics: ['analytics'] });
	assert.deepEqual(draft.embeds, { category: '', providers: { youtube: '', vimeo: 'analytics' } });
	assert.deepEqual(draft.gpc_categories, []);
});

test('isCategoryId matches what the server accepts', () => {
	for (const id of ['social', 'a', 'video_2', 'third-party']) {
		assert.equal(isCategoryId(id), true, id);
	}

	for (const id of ['', 'Social', '2fa', '-x', 'a'.repeat(33), 'has space']) {
		assert.equal(isCategoryId(id), false, id);
	}
});

test('toInt reads number fields, with 0 for anything else', () => {
	assert.equal(toInt('365'), 365);
	assert.equal(toInt(''), 0);
	assert.equal(toInt('abc'), 0);
});

test('toggle adds or removes an id', () => {
	assert.deepEqual(toggle(['a'], 'b', true), ['a', 'b']);
	assert.deepEqual(toggle(['a', 'b'], 'a', false), ['b']);
});
