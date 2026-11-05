// The minified runtime is committed (sites need no Node build), so check it
// is current, within the size budget and behaves like the source.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';
import { runInNewContext } from 'node:vm';
import { build } from 'esbuild';

const sourcePath = new URL('../../assets/consent.js', import.meta.url);
const minPath = new URL('../../assets/consent.min.js', import.meta.url);
const BUDGET = 5 * 1024;

test('consent.min.js is up to date (run `pnpm build`)', async () => {
	const result = await build({
		entryPoints: [sourcePath.pathname],
		minify: true,
		target: 'es2020',
		legalComments: 'none',
		write: false,
	});
	assert.equal(readFileSync(minPath, 'utf8'), result.outputFiles[0].text);
});

test(`consent.min.js is under ${BUDGET} bytes gzipped`, () => {
	const size = gzipSync(readFileSync(minPath), { level: 9 }).length;
	assert.ok(size < BUDGET, `${size} bytes`);
});

test('minified codec matches the source codec', () => {
	const fixture = JSON.parse(readFileSync(new URL('../fixtures/cookie-codec.json', import.meta.url), 'utf8'));
	const load = (path) => {
		const context = {};
		context.globalThis = context;
		runInNewContext(readFileSync(path, 'utf8'), context);
		return context.mortarConsentCodec;
	};
	const source = load(sourcePath);
	const min = load(minPath);
	assert.equal(min.pattern, source.pattern);
	for (const { value } of fixture.cases) {
		assert.deepEqual(
			JSON.stringify(min.decode(value, fixture.categories)),
			JSON.stringify(source.decode(value, fixture.categories)),
		);
	}
});
