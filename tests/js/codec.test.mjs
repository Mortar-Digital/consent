// Runs the browser runtime's codec against the fixtures PHP uses, so the
// two implementations of the cookie format cannot drift apart.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const source = readFileSync(new URL('../../assets/consent.js', import.meta.url), 'utf8');
const fixture = JSON.parse(readFileSync(new URL('../fixtures/cookie-codec.json', import.meta.url), 'utf8'));

const context = {};
context.globalThis = context;
runInNewContext(source, context);
const { decode, encode, isCurrent, pattern } = context.mortarConsentCodec;

test('cookie regex matches the PHP codec (also reused by the Consent Mode head snippet)', () => {
	assert.equal(pattern, fixture.pattern);
});

for (const { name, value, expect } of fixture.cases) {
	test(`decode: ${name}`, () => {
		const state = decode(value, fixture.categories);

		assert.deepEqual(state === null ? null : JSON.parse(JSON.stringify(state)), expect);
	});
}

test('encode round-trips', () => {
	const state = {
		policyVersion: 2,
		timestamp: 1761480000,
		consentId: '3f1c2a9e-8b7d-4c2e-9f10-2a3b4c5d6e7f',
		granted: ['marketing'],
	};

	assert.equal(encode(state), '1|2|1761480000|3f1c2a9e-8b7d-4c2e-9f10-2a3b4c5d6e7f|marketing');
	assert.deepEqual(JSON.parse(JSON.stringify(decode(encode(state), fixture.categories))), state);
});

test('isCurrent matches the PHP policy rules', () => {
	const config = { policyVersion: 2, lifetime: 180 * 86400 };
	const now = 1761480000;
	const state = (policyVersion, timestamp) => ({ policyVersion, timestamp, consentId: '', granted: [] });

	assert.equal(isCurrent(state(2, now), config, now), true);
	assert.equal(isCurrent(state(1, now), config, now), false);
	assert.equal(isCurrent(state(2, now), config, now + config.lifetime), true);
	assert.equal(isCurrent(state(2, now), config, now + config.lifetime + 1), false);
	assert.equal(isCurrent(state(2, now + 300), config, now), true);
	assert.equal(isCurrent(state(2, now + 301), config, now), false);
});
