import js from '@eslint/js';
import globals from 'globals';

export default [
	{ ignores: ['node_modules/**', 'vendor/**', 'build/**', 'tests/e2e/artifacts/**', 'assets/consent.min.js'] },
	js.configs.recommended,
	{
		files: ['assets/**/*.js', 'blocks/**/*.js', 'tests/e2e/fixture/**/*.js'],
		languageOptions: {
			ecmaVersion: 2020,
			sourceType: 'script',
			globals: globals.browser,
		},
	},
	{
		files: ['eslint.config.js', 'tests/**/*.mjs'],
		languageOptions: { globals: globals.node },
	},
	{
		// Playwright evaluate/init callbacks execute in the page, not Node.
		files: ['tests/e2e/**/*.mjs'],
		languageOptions: { globals: globals.browser },
	},
];
