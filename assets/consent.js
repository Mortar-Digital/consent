// @ts-check
(function () {
	'use strict';

	/** @typedef {{ id: string, required: boolean, cookies: string[] }} Category */
	/** @typedef {Record<string, string[]>} SignalMap signal => categories that must all be allowed */
	/** @typedef {{ cookie: { name: string, domain: string }, policyVersion: number, lifetime: number, categories: Category[], log?: string, consentMode?: SignalMap | null, wpConsentApi?: SignalMap, gpc?: string[] }} Config */
	/** @typedef {{ policyVersion: number, timestamp: number, consentId: string, granted: string[] }} State */

	const SCHEMA = 1;
	const MAX_LENGTH = 1024;
	const CLOCK_SKEW = 300;
	const LEGACY_COOKIE = 'sd_consent';
	const ID = '[a-z][a-z0-9_-]{0,31}';
	const UUID = '[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}';
	const PATTERN = new RegExp(`^${SCHEMA}\\|([1-9][0-9]{0,8})\\|([0-9]{1,12})\\|(${UUID})\\|((?:${ID})(?:\\.${ID})*)?$`);

	/**
	 * @param {unknown} value
	 * @param {Category[]} categories
	 * @returns {State | null}
	 */
	function decode(value, categories) {
		if (typeof value !== 'string' || value.length > MAX_LENGTH) {
			return null;
		}
		const m = PATTERN.exec(value);
		if (!m) {
			return null;
		}
		const optional = new Set(categories.filter((c) => !c.required).map((c) => c.id));

		return {
			policyVersion: Number(m[1]),
			timestamp: Number(m[2]),
			consentId: m[3],
			granted: [...new Set((m[4] || '').split('.').filter((id) => optional.has(id)))],
		};
	}

	/** @param {State} state */
	function encode(state) {
		return [SCHEMA, state.policyVersion, state.timestamp, state.consentId, state.granted.join('.')].join('|');
	}

	/**
	 * @param {State} state
	 * @param {Pick<Config, 'policyVersion' | 'lifetime'>} config
	 * @param {number} now unix seconds
	 */
	function isCurrent(state, config, now) {
		return state.policyVersion === config.policyVersion
			&& state.timestamp <= now + CLOCK_SKEW
			&& now - state.timestamp <= config.lifetime;
	}

	const codec = Object.freeze({ decode, encode, isCurrent, pattern: PATTERN.source });

	// Loaded outside a browser (unit tests): expose the codec only.
	if (typeof document === 'undefined') {
		globalThis.mdConsentCodec = codec;
		return;
	}

	const root = document.getElementById('md-consent');
	const configElement = document.getElementById('md-consent-config');
	if (!root || !configElement) {
		return;
	}

	/** @type {Config} */
	const config = JSON.parse(configElement.textContent || '{}');
	const banner = /** @type {HTMLElement | null} */ (root.querySelector('#md-consent-banner'));
	const dialog = /** @type {HTMLDialogElement | null} */ (root.querySelector('#md-consent-dialog'));
	const form = /** @type {HTMLFormElement | null} */ (root.querySelector('[data-md-consent-form]'));
	const reopen = /** @type {HTMLElement | null} */ (root.querySelector('.md-consent__reopen'));
	const optionalIds = config.categories.filter((c) => !c.required).map((c) => c.id);
	// Global Privacy Control: "Accept all" leaves these categories off. The
	// visitor can still switch them on individually in the preferences.
	const gpc = /** @type {any} */ (navigator).globalPrivacyControl === true;
	const acceptAllIds = gpc ? optionalIds.filter((id) => !(config.gpc || []).includes(id)) : optionalIds;
	if (gpc) {
		for (const notice of root.querySelectorAll('[data-md-consent-gpc]')) {
			/** @type {HTMLElement} */ (notice).hidden = false;
		}
	}

	/** @type {State | null} */
	let current = null;

	const now = () => Math.floor(Date.now() / 1000);

	function uuid() {
		if (typeof crypto.randomUUID === 'function') {
			return crypto.randomUUID();
		}
		// randomUUID needs a secure context; plain-HTTP dev sites fall back here.
		const b = crypto.getRandomValues(new Uint8Array(16));
		b[6] = (b[6] & 0x0f) | 0x40;
		b[8] = (b[8] & 0x3f) | 0x80;
		const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');

		return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
	}

	/** @param {string} name */
	function readCookie(name) {
		for (const part of document.cookie.split(';')) {
			const i = part.indexOf('=');
			if (i > -1 && part.slice(0, i).trim() === name) {
				return part.slice(i + 1).trim();
			}
		}

		return null;
	}

	/** @param {State} state */
	function writeCookie(state) {
		let cookie = `${config.cookie.name}=${encode(state)}; Max-Age=${config.lifetime}; Path=/; SameSite=Lax`;
		if (config.cookie.domain) {
			cookie += `; Domain=${config.cookie.domain}`;
		}
		if (location.protocol === 'https:') {
			cookie += '; Secure';
		}
		document.cookie = cookie;
	}

	/**
	 * Best-effort removal of cookies owned by categories the visitor has not
	 * allowed. HttpOnly cookies, or ones set on other paths, cannot be reached.
	 *
	 * @param {string[]} granted
	 */
	function removeDeniedCookies(granted) {
		const patterns = config.categories
			.filter((c) => !c.required && !granted.includes(c.id))
			.flatMap((c) => c.cookies);
		if (patterns.length === 0) {
			return;
		}
		const parts = location.hostname.split('.');
		const domains = [''];
		for (let i = 0; i < parts.length - 1; i++) {
			domains.push(`; Domain=.${parts.slice(i).join('.')}`);
		}
		for (const part of document.cookie.split(';')) {
			const name = part.split('=')[0].trim();
			const owned = patterns.some((p) => (p.endsWith('*') ? name.startsWith(p.slice(0, -1)) : name === p));
			if (name && owned) {
				for (const domain of domains) {
					document.cookie = `${name}=; Max-Age=0; Path=/${domain}`;
				}
			}
		}
	}

	/** @param {string} id */
	function has(id) {
		const category = config.categories.find((c) => c.id === id);
		if (!category) {
			return false;
		}

		return category.required || (current !== null && current.granted.includes(id));
	}

	// Activation keeps document order: external scripts are inserted with
	// async=false (the browser runs them in insertion order) and inline
	// scripts wait for every external script queued before them.
	let chain = Promise.resolve();
	/** @type {Promise<unknown>[]} */
	let pending = [];

	/** @param {HTMLScriptElement} placeholder */
	function activate(placeholder) {
		placeholder.removeAttribute('data-md-consent');
		chain = chain.then(() => {
			const script = document.createElement('script');
			for (const { name, value } of placeholder.attributes) {
				if (name !== 'type' && name !== 'data-md-type' && name !== 'nonce') {
					script.setAttribute(name, value);
				}
			}
			const type = placeholder.getAttribute('data-md-type');
			if (type) {
				script.type = type;
			}
			// The nonce attribute is hidden after parsing; the property survives.
			if (placeholder.nonce) {
				script.nonce = placeholder.nonce;
			}
			if (placeholder.hasAttribute('src')) {
				if (!placeholder.hasAttribute('async')) {
					script.async = false;
					pending.push(new Promise((resolve) => {
						script.addEventListener('load', resolve, { once: true });
						script.addEventListener('error', resolve, { once: true });
					}));
				}
				placeholder.replaceWith(script);
				return undefined;
			}
			script.text = placeholder.text;
			const waitFor = pending;
			pending = [];
			return Promise.all(waitFor).then(() => placeholder.replaceWith(script));
		});
	}

	function activateAllowed() {
		const placeholders = document.querySelectorAll('script[type="text/plain"][data-md-consent]');
		for (const placeholder of /** @type {NodeListOf<HTMLScriptElement>} */ (placeholders)) {
			const needs = (placeholder.getAttribute('data-md-consent') || '').split(/\s+/).filter(Boolean);
			if (needs.length > 0 && needs.every(has)) {
				activate(placeholder);
			}
		}
		for (const embed of document.querySelectorAll('[data-md-consent-embed]')) {
			if (has(embed.getAttribute('data-md-consent-embed') || '')) {
				activateEmbed(embed, false);
			}
		}
	}

	/**
	 * Swaps a click-to-load placeholder for the original embed kept in its
	 * inert <template>. Scripts are recreated so the browser runs them.
	 *
	 * @param {Element} container
	 * @param {boolean} focus move focus into the embed (after a click)
	 */
	function activateEmbed(container, focus) {
		const template = container.querySelector(':scope > template');
		if (!(template instanceof HTMLTemplateElement)) {
			return;
		}
		const fragment = /** @type {DocumentFragment} */ (template.content.cloneNode(true));
		for (const old of fragment.querySelectorAll('script')) {
			const script = document.createElement('script');
			for (const { name, value } of old.attributes) {
				script.setAttribute(name, value);
			}
			script.text = old.text;
			old.replaceWith(script);
		}
		const target = fragment.querySelector('iframe, a, button, [tabindex]');
		container.replaceWith(fragment);
		if (focus && target instanceof HTMLElement) {
			target.focus();
		}
	}

	/**
	 * @param {Element} container
	 * @param {Element} control
	 */
	function isGenuineEmbed(container, control) {
		const placeholder = control.closest('.md-consent-embed__placeholder');
		return container.querySelector(':scope > template') !== null
			&& placeholder !== null
			&& placeholder.parentElement === container;
	}

	/**
	 * @param {SignalMap} map
	 * @returns {Record<string, boolean>}
	 */
	function resolveSignals(map) {
		return Object.fromEntries(Object.entries(map).map(([signal, ids]) => [signal, ids.length > 0 && ids.every(has)]));
	}

	/** Mirrors the choice to Google Consent Mode and the WP Consent API. */
	function syncIntegrations() {
		if (config.consentMode) {
			const w = /** @type {any} */ (window);
			w.dataLayer = w.dataLayer || [];
			const gtag = typeof w.gtag === 'function' ? w.gtag : function () { w.dataLayer.push(arguments); };
			const state = Object.fromEntries(Object.entries(resolveSignals(config.consentMode)).map(([k, v]) => [k, v ? 'granted' : 'denied']));
			gtag('consent', 'update', state);
		}
		const setConsent = /** @type {any} */ (window).wp_set_consent;
		if (config.wpConsentApi && typeof setConsent === 'function') {
			for (const [category, allowed] of Object.entries(resolveSignals(config.wpConsentApi))) {
				setConsent(category, allowed ? 'allow' : 'deny');
			}
		}
	}

	/** @param {boolean} initial */
	function announce(initial) {
		const categories = Object.fromEntries(config.categories.map((c) => [c.id, has(c.id)]));
		syncIntegrations();
		document.dispatchEvent(new CustomEvent('md-consent:change', { detail: { categories, initial } }));
	}

	/** @param {boolean} visible */
	function showBanner(visible) {
		if (banner) {
			banner.hidden = !visible;
		}
		if (reopen) {
			reopen.hidden = visible;
		}
	}

	/**
	 * Proof of consent. Fire-and-forget: a failed request must never block
	 * the visitor's choice, which is already stored in their cookie.
	 *
	 * @param {State} state
	 * @param {'accept-all' | 'reject-all' | 'custom'} method
	 */
	function record(state, method) {
		if (!config.log) {
			return;
		}
		try {
			fetch(config.log, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ consentId: state.consentId, policyVersion: state.policyVersion, granted: state.granted, method }),
				credentials: 'omit',
				keepalive: true,
			}).catch(() => {});
		} catch {
			// Ignore: logging is best-effort.
		}
	}

	/**
	 * @param {string[]} granted
	 * @param {'accept-all' | 'reject-all' | 'custom'} method
	 */
	function save(granted, method) {
		const previous = current;
		current = {
			policyVersion: config.policyVersion,
			timestamp: now(),
			consentId: previous ? previous.consentId : uuid(),
			granted: optionalIds.filter((id) => granted.includes(id)),
		};
		writeCookie(current);
		record(current, method);
		removeDeniedCookies(current.granted);
		showBanner(false);
		if (dialog && dialog.open) {
			dialog.close();
		}

		// Scripts that already ran cannot be unloaded, so a withdrawal reloads
		// the page to stop them.
		const withdrawn = previous !== null && previous.granted.some((id) => !current?.granted.includes(id));
		if (withdrawn) {
			location.reload();
			return;
		}
		activateAllowed();
		announce(false);
	}

	function openPreferences() {
		if (!dialog || !form) {
			return;
		}
		for (const input of form.querySelectorAll('input[name="md-consent-category"]')) {
			const box = /** @type {HTMLInputElement} */ (input);
			box.checked = current !== null && current.granted.includes(box.value);
		}
		// Shown so visitors can quote it if they ask what they agreed to.
		const meta = /** @type {HTMLElement | null} */ (form.querySelector('[data-md-consent-meta]'));
		const idSlot = form.querySelector('[data-md-consent-id]');
		if (meta && idSlot) {
			meta.hidden = current === null;
			idSlot.textContent = current ? current.consentId : '';
		}
		if (!dialog.open) {
			dialog.showModal();
		}
	}

	function formChoices() {
		if (!form) {
			return [];
		}

		return [...form.querySelectorAll('input[name="md-consent-category"]:checked')]
			.map((input) => /** @type {HTMLInputElement} */ (input).value);
	}

	document.addEventListener('click', (event) => {
		const target = event.target instanceof Element ? event.target : null;
		if (!target) {
			return;
		}
		const opener = target.closest('[data-md-consent-open], a[href="#cookie-settings"]');
		if (opener) {
			event.preventDefault();
			openPreferences();
			return;
		}
		const embedControl = target.closest('[data-md-consent-embed-load], [data-md-consent-embed-allow]');
		const embed = embedControl && embedControl.closest('[data-md-consent-embed]');
		// Only real placeholders count: they hold a <template> (which post
		// content filtering strips) and the button sits in the generated
		// placeholder. Otherwise a contributor could put a look-alike
		// "allow" button in a post and forge consent with one click.
		if (embedControl && embed && isGenuineEmbed(embed, embedControl)) {
			if (embedControl.hasAttribute('data-md-consent-embed-load')) {
				// Consent for this one embed only; nothing is stored.
				activateEmbed(embed, true);
			} else {
				const category = embed.getAttribute('data-md-consent-embed') || '';
				save([...(current ? current.granted : []), category], 'custom');
			}
			return;
		}
		const control = target.closest('[data-md-consent-action]');
		if (!control || !root.contains(control)) {
			return;
		}
		switch (control.getAttribute('data-md-consent-action')) {
			case 'accept-all':
				save(acceptAllIds, 'accept-all');
				break;
			case 'reject-all':
				save([], 'reject-all');
				break;
			case 'manage':
				openPreferences();
				break;
			case 'close':
				dialog?.close();
				break;
		}
	});

	// Covers the Save button and Enter in the form; method="dialog" would
	// otherwise close the dialog without recording anything.
	form?.addEventListener('submit', (event) => {
		event.preventDefault();
		save(formChoices(), 'custom');
	});

	// A modal dialog ignores clicks on its backdrop; treat one like Escape
	// (close without saving). The press must start on the backdrop too, so a
	// text selection dragged out of the dialog does not close it.
	if (dialog) {
		/** @param {MouseEvent} event */
		const onBackdrop = (event) => {
			if (event.target !== dialog) {
				return false;
			}
			const box = dialog.getBoundingClientRect();

			return event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom;
		};
		let pressedBackdrop = false;
		dialog.addEventListener('pointerdown', (event) => {
			pressedBackdrop = onBackdrop(event);
		});
		dialog.addEventListener('click', (event) => {
			if (pressedBackdrop && onBackdrop(event)) {
				dialog.close();
			}
			pressedBackdrop = false;
		});
	}

	/** Public API for themes and integrations; defined before the first event fires. */
	Object.defineProperty(window, 'mdConsent', {
		value: Object.freeze({
			/** @param {string} id */
			has,
			get: () => (current ? { ...current, granted: [...current.granted] } : null),
			open: openPreferences,
			acceptAll: () => save(acceptAllIds, 'accept-all'),
			rejectAll: () => save([], 'reject-all'),
			/** @param {string[]} granted */
			set: (granted) => save(Array.isArray(granted) ? granted : [], 'custom'),
		}),
	});

	// Choices made before the rename live in the legacy cookie: carry them
	// over once so visitors are not asked again, then drop the old one.
	const legacy = readCookie(config.cookie.name) === null ? decode(readCookie(LEGACY_COOKIE), config.categories) : null;
	if (legacy) {
		writeCookie(legacy);
	}
	if (readCookie(LEGACY_COOKIE) !== null) {
		const domain = config.cookie.domain ? `; Domain=${config.cookie.domain}` : '';
		document.cookie = `${LEGACY_COOKIE}=; Max-Age=0; Path=/${domain}`;
	}
	const stored = decode(readCookie(config.cookie.name), config.categories);
	if (stored && isCurrent(stored, config, now())) {
		current = stored;
		showBanner(false);
		removeDeniedCookies(current.granted);
		activateAllowed();
	} else {
		showBanner(true);
		removeDeniedCookies([]);
	}
	announce(true);

	// Deep link, e.g. "Change your cookie settings" on a cookie policy page.
	const openFromHash = () => {
		if (location.hash === '#cookie-settings') {
			openPreferences();
		}
	};
	window.addEventListener('hashchange', openFromHash);
	openFromHash();
})();
