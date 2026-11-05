// Where the e2e suite runs, chosen by MORTAR_CONSENT_E2E_ENV:
//
//   wp-env (default)  the throwaway site from .wp-env.json, with the plugin
//                     active as a regular plugin, as WordPress.org installs it.
//   ddev              a Bedrock site checkout (MORTAR_CONSENT_E2E_SITE) that
//                     loads the plugin as an MU-plugin from mu-plugins/mortar-consent.
import { spawnSync } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

function ddev() {
	const site = resolve(process.env.MORTAR_CONSENT_E2E_SITE || '');
	if (!process.env.MORTAR_CONSENT_E2E_SITE || !existsSync(resolve(site, 'web/app/mu-plugins/mortar-consent'))) {
		throw new Error(
			'Set MORTAR_CONSENT_E2E_SITE to a site checkout with the plugin in web/app/mu-plugins/mortar-consent.',
		);
	}

	const env = readFileSync(resolve(site, '.env'), 'utf8');

	return {
		name: 'ddev',
		url: process.env.MORTAR_CONSENT_E2E_URL || (env.match(/^WP_HOME=['"]?([^'"\n]+)/m) || [])[1],
		command: ['ddev', ['wp']],
		cwd: site,
		muPlugins: resolve(site, 'web/app/mu-plugins'),
		fixtures: '/var/www/html/web/app/mu-plugins/mortar-consent/tests/e2e/fixture',
	};
}

function wpEnv() {
	return {
		name: 'wp-env',
		url: process.env.MORTAR_CONSENT_E2E_URL || 'http://localhost:8888',
		command: [resolve(root, 'node_modules/.bin/wp-env'), ['run', 'cli', '--', 'wp']],
		cwd: root,
		muPlugins: resolve(root, 'tests/e2e/wp-env/mu-plugins'),
		fixtures: '/var/www/html/wp-content/plugins/mortar-consent/tests/e2e/fixture',
	};
}

export const env = (process.env.MORTAR_CONSENT_E2E_ENV || 'wp-env') === 'ddev' ? ddev() : wpEnv();

if (!env.url) {
	throw new Error('Set MORTAR_CONSENT_E2E_URL (or WP_HOME in the site .env).');
}

/** Runs WP-CLI against the e2e site and returns its trimmed output. */
export function wp(args, input) {
	const [bin, prefix] = env.command;
	const result = spawnSync(bin, [...prefix, ...args], {
		cwd: env.cwd,
		input,
		encoding: 'utf8',
		maxBuffer: 32 * 1024 * 1024,
	});

	if (result.status !== 0) {
		throw new Error(`wp ${args.join(' ')} failed: ${result.stderr || result.stdout}`);
	}

	return result.stdout.trim();
}
