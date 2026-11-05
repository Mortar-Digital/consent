(function (wp) {
	'use strict';

	const {
		createElement: el,
		Fragment,
		useState,
		useEffect,
		useMemo,
		createRoot
	} = wp.element;

	const {
		TabPanel, Panel, PanelBody, TextControl, TextareaControl, ToggleControl, CheckboxControl, SelectControl,
		Button, Notice, Spinner, Card, CardBody,
	} = wp.components;

	const apiFetch = wp.apiFetch;
	const { __, _n, sprintf } = wp.i18n;
	const boot = window.mortarConsentAdmin || {};

	const TEXT_LABELS = {
		banner_title: __('Banner title', 'mortar-consent'),
		banner_body: __('Banner text', 'mortar-consent'),
		accept_all: __('"Accept all" button', 'mortar-consent'),
		reject_all: __('"Reject all" button', 'mortar-consent'),
		manage: __('"Manage preferences" button', 'mortar-consent'),
		dialog_title: __('Preferences title', 'mortar-consent'),
		dialog_body: __('Preferences text', 'mortar-consent'),
		save: __('"Save choices" button', 'mortar-consent'),
		close: __('Close button label (screen readers)', 'mortar-consent'),
		always_active: __('Label for required categories', 'mortar-consent'),
		privacy_link: __('Cookie policy link text', 'mortar-consent'),
		settings_button: __('Cookie settings button', 'mortar-consent'),
		consent_id: __('Consent ID label', 'mortar-consent'),
		embed_notice: /* translators: Field label. Keep %s as typed: it shows where the provider name goes. */ __('Embed placeholder text (%s = provider)', 'mortar-consent'),
		embed_load: __('"Load this content" button', 'mortar-consent'),
		embed_allow: /* translators: Field label. Keep %s as typed: it shows where the category name goes. */ __('"Always allow" button (%s = category)', 'mortar-consent'),
		gpc_notice: /* translators: Field label. Keep %s as typed: it shows where the category names go. */ __('Global Privacy Control notice (%s = categories)', 'mortar-consent'),
	};

	/** Wording grouped by where visitors see it; unknown keys join the last group. */
	const TEXT_GROUPS = [
		{
			title: __('Banner', 'mortar-consent'),
			description: __('The first thing visitors see, until they make a choice.', 'mortar-consent'),
			keys: ['banner_title', 'banner_body', 'accept_all', 'reject_all', 'manage', 'privacy_link'],
		},
		{
			title: __('Preferences dialog', 'mortar-consent'),
			description: __('Where visitors choose category by category, and come back to change their mind.', 'mortar-consent'),
			keys: ['dialog_title', 'dialog_body', 'save', 'close', 'always_active', 'consent_id'],
		},
		{
			title: __('Buttons, embeds and notices', 'mortar-consent'),
			description: __('The floating settings button, click-to-load embed placeholders and the Global Privacy Control notice.', 'mortar-consent'),
			keys: ['settings_button', 'embed_notice', 'embed_load', 'embed_allow', 'gpc_notice'],
		},
	];

	const SIGNAL_LABELS = {
		ad_storage: __('Advertising storage', 'mortar-consent'),
		ad_user_data: __('Send user data to Google for ads', 'mortar-consent'),
		ad_personalization: __('Personalised advertising', 'mortar-consent'),
		analytics_storage: __('Analytics storage', 'mortar-consent'),
		functionality_storage: __('Functionality storage', 'mortar-consent'),
		personalization_storage: __('Personalisation storage', 'mortar-consent'),
		security_storage: __('Security storage', 'mortar-consent'),
	};

	const METHOD_LABELS = {
		'accept-all': __('Accepted all', 'mortar-consent'),
		'reject-all': __('Rejected all', 'mortar-consent'),
		custom: __('Chose categories', 'mortar-consent'),
	};

	const METHOD_TONES = {
		'accept-all': 'success',
		'reject-all': 'error',
		custom: 'info',
	};

	/** Removes a deleted category from every setting that refers to it. */
	function withoutCategory(draft, id) {
		const strip = (map) => Object.fromEntries(Object.entries(map || {}).map(([k, ids]) => [k, (Array.isArray(ids) ? ids : [ids]).filter((x) => x !== id)]));

		return {
			...draft,
			categories: draft.categories.filter((c) => c.id !== id),
			scripts: draft.scripts.map((r) => ({ ...r, categories: r.categories.filter((x) => x !== id) })),
			consent_mode: { ...draft.consent_mode, signals: strip(draft.consent_mode.signals) },
			wp_consent_api: strip(draft.wp_consent_api),
			embeds: {
				category: draft.embeds.category === id ? '' : draft.embeds.category,
				providers: Object.fromEntries(Object.entries(draft.embeds.providers || {}).map(([k, v]) => [k, v === id ? '' : v])),
			},
			gpc_categories: draft.gpc_categories.filter((x) => x !== id),
		};
	}

	const LONG_TEXTS = ['banner_body', 'dialog_body'];

	/** Settings as the server returns them → editable form state. */
	function toDraft(effective) {
		return {
			...effective,
			scripts: Object.entries(effective.scripts || {}).map(([handle, cats]) => ({
				handle,
				categories: Array.isArray(cats) ? cats : [cats],
			})),
		};
	}

	/** Form state → the settings object the server validates. */
	function fromDraft(draft) {
		const scripts = {};
		for (const row of draft.scripts) {
			const handle = row.handle.trim();
			if (handle !== '') {
				scripts[handle] = row.categories;
			}
		}
		return { ...draft, scripts };
	}

	const toInt = (value) => {
		const n = parseInt(value, 10);
		return Number.isNaN(n) ? 0 : n;
	};

	const badge = (text, tone) => el('span', { className: 'mortar-consent-badge' + (tone ? ` mortar-consent-badge--${tone}` : '') }, text);

	/** A settings group: heading and explanation beside its controls (stacked on narrow screens). */
	function Section({ title, description, aside, wide, children }) {
		return el(
			'section',
			{ className: 'mortar-consent-section' + (wide ? ' mortar-consent-section--wide' : '') },
			el('div', { className: 'mortar-consent-section__intro' },
				el('h2', null, title, aside && ' ', aside),
				description && el('p', null, description)
			),
			el('div', { className: 'mortar-consent-section__body' }, children)
		);
	}

	const Fields = ({ children }) => el(Card, null, el(CardBody, { className: 'mortar-consent-fields' }, children));

	function App() {
		const [data, setData] = useState(null);
		const [draft, setDraft] = useState(null);
		const [saved, setSaved] = useState('');
		const [notice, setNotice] = useState(null);
		const [justSaved, setJustSaved] = useState(false);
		const [saving, setSaving] = useState(false);

		const load = (payload) => {
			const next = toDraft(payload.effective);
			setData(payload);
			setDraft(next);
			setSaved(JSON.stringify(fromDraft(next)));
		};

		useEffect(() => {
			apiFetch({ path: '/mortar-consent/v1/settings' })
				.then(load)
				.catch((error) => setNotice({ status: 'error', message: error.message }));
		}, []);

		const dirty = useMemo(() => draft !== null && JSON.stringify(fromDraft(draft)) !== saved, [draft, saved]);

		// Unsaved edits: hide the last "saved" message and warn before leaving.
		useEffect(() => {
			if (!dirty) {
				return undefined;
			}
			setJustSaved(false);
			const warn = (event) => {
				event.preventDefault();
				event.returnValue = '';
			};
			window.addEventListener('beforeunload', warn);
			return () => window.removeEventListener('beforeunload', warn);
		}, [dirty]);

		if (!draft) {
			return notice
				? el(Notice, { status: notice.status, isDismissible: false }, notice.message)
				: el('p', { className: 'mortar-consent-loading' }, el(Spinner), __('Loading settings…', 'mortar-consent'));
		}

		const isLocked = (key) => data.locked.includes(key);
		const set = (key, value) => setDraft((current) => ({ ...current, [key]: value }));
		const lockHelp = (key, help) => (isLocked(key) ? __('Set in code: change it in the site configuration.', 'mortar-consent') : help);

		const save = () => {
			setSaving(true);
			setNotice(null);
			apiFetch({ path: '/mortar-consent/v1/settings', method: 'PUT', data: { settings: fromDraft(draft) } })
				.then((payload) => {
					load(payload);
					setJustSaved(true);
				})
				.catch((error) => setNotice({ status: 'error', message: error.message }))
				.finally(() => setSaving(false));
		};

		const props = { draft, data, set, setDraft, isLocked, lockHelp };
		const tabs = [
			{ name: 'general', title: __('General', 'mortar-consent'), render: () => el(GeneralTab, props) },
			{ name: 'categories', title: __('Categories & cookies', 'mortar-consent'), render: () => el(CategoriesTab, props) },
			{ name: 'scripts', title: __('Scripts', 'mortar-consent'), render: () => el(ScriptsTab, props) },
			{ name: 'integrations', title: __('Integrations', 'mortar-consent'), render: () => el(IntegrationsTab, props) },
			{ name: 'texts', title: __('Wording', 'mortar-consent'), render: () => el(TextsTab, props) },
			{ name: 'log', title: __('Consent log', 'mortar-consent'), render: () => el(LogTab, props) },
		];

		let status = null;
		if (saving) {
			status = el('span', { className: 'mortar-consent-savebar__status' }, __('Saving…', 'mortar-consent'));
		} else if (dirty) {
			status = el('span', { className: 'mortar-consent-savebar__status mortar-consent-savebar__status--dirty' }, __('You have unsaved changes.', 'mortar-consent'));
		} else if (justSaved) {
			status = el('span', { className: 'mortar-consent-savebar__status mortar-consent-savebar__status--saved' }, __('Settings saved.', 'mortar-consent'));
		}

		return el(
			'div',
			{ className: 'mortar-consent-admin' },
			notice && el(Notice, { status: notice.status, onRemove: () => setNotice(null) }, notice.message),
			el(TabPanel, { tabs, className: 'mortar-consent-admin__tabs' }, (tab) => el('div', { className: 'mortar-consent-admin__panel' }, tab.render())),
			el(
				'div',
				{ className: 'mortar-consent-savebar' },
				el('p', { role: 'status', className: 'mortar-consent-savebar__live' }, status),
				el(Button, { variant: 'primary', onClick: save, isBusy: saving, disabled: saving || !dirty, accessibleWhenDisabled: true, __next40pxDefaultSize: true }, __('Save settings', 'mortar-consent'))
			)
		);
	}

	function GeneralTab({ draft, data, set, isLocked, lockHelp }) {
		const bumped = draft.policy_version > data.effective.policy_version;
		return el(
			Fragment,
			null,
			el(Section, {
				title: __('Cookie policy version', 'mortar-consent'),
				description: __('Raise the version when you add a new tracker or change how cookies are used. Every visitor is asked for consent again.', 'mortar-consent'),
			},
			el(Card, null, el(CardBody, null,
				el('div', { className: 'mortar-consent-version' },
					el('div', null,
						el('span', { className: 'mortar-consent-version__label' }, __('Current version', 'mortar-consent')),
						el('span', { className: 'mortar-consent-version__value' },
							sprintf(/* translators: %d: cookie policy version number */ __('Version %d', 'mortar-consent'), draft.policy_version),
							bumped && badge(__('Visitors asked again on save', 'mortar-consent'), 'warning')
						)
					),
					el(Button, {
						variant: 'secondary',
						disabled: isLocked('policy_version') || bumped,
						accessibleWhenDisabled: true,
						__next40pxDefaultSize: true,
						onClick: () => {
							// Re-prompting every visitor needs an explicit confirmation.
							if (window.confirm(__('Every visitor will be asked for consent again after you save. Continue?', 'mortar-consent'))) {
								set('policy_version', draft.policy_version + 1);
							}
						},
					}, __('Ask all visitors again', 'mortar-consent'))
				),
				isLocked('policy_version') && el('p', { className: 'description' }, lockHelp('policy_version'))
			))),
			el(Section, {
				title: __('Visitor choices', 'mortar-consent'),
				description: __('How long a choice lasts, where the banner links to, and how visitors reopen their preferences.', 'mortar-consent'),
			},
			el(Fields, null,
				el(TextControl, {
					className: 'mortar-consent-field--short',
					label: __('Consent lasts (days)', 'mortar-consent'),
					type: 'number',
					min: 1,
					max: 395,
					value: String(draft.lifetime_days),
					disabled: isLocked('lifetime_days'),
					help: lockHelp('lifetime_days', __('Visitors are asked again after this. Up to 395 days (13 months).', 'mortar-consent')),
					onChange: (v) => set('lifetime_days', toInt(v)),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(TextControl, {
					label: __('Cookie policy URL', 'mortar-consent'),
					type: 'url',
					value: draft.privacy_policy_url,
					placeholder: '/cookie-policy/',
					disabled: isLocked('privacy_policy_url'),
					help: lockHelp('privacy_policy_url', __('Linked from the banner. Use a full URL or a path such as /cookie-policy/.', 'mortar-consent')),
					onChange: (v) => set('privacy_policy_url', v.trim()),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(TextControl, {
					label: __('Cookie domain', 'mortar-consent'),
					value: draft.cookie_domain,
					placeholder: __('This host only', 'mortar-consent'),
					disabled: isLocked('cookie_domain'),
					help: lockHelp('cookie_domain', __('Leave empty for this host only. Use .example.com to share consent across subdomains.', 'mortar-consent')),
					onChange: (v) => set('cookie_domain', v.trim().toLowerCase()),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(ToggleControl, {
					label: __('Show the floating "Cookie settings" button', 'mortar-consent'),
					checked: draft.reopen_button,
					disabled: isLocked('reopen_button'),
					help: lockHelp('reopen_button', __('Only turn this off if every page has another way to reopen preferences, such as the Cookie settings button block in the footer.', 'mortar-consent')),
					onChange: (v) => set('reopen_button', v),
					__nextHasNoMarginBottom: true,
				})
			)),
			el(Section, {
				title: __('Consent log', 'mortar-consent'),
				description: __('Evidence of each choice, should you need to show when and how a visitor consented.', 'mortar-consent'),
			},
			el(Fields, null,
				el(ToggleControl, {
					label: __('Keep a consent log', 'mortar-consent'),
					checked: draft.log_enabled,
					disabled: isLocked('log_enabled'),
					help: lockHelp('log_enabled', __('Records each choice with a random ID, the categories, the policy version and the time. No IP address or browser details are stored.', 'mortar-consent')),
					onChange: (v) => set('log_enabled', v),
					__nextHasNoMarginBottom: true,
				}),
				el(TextControl, {
					className: 'mortar-consent-field--short',
					label: __('Keep log records for (days)', 'mortar-consent'),
					type: 'number',
					min: 30,
					max: 3650,
					value: String(draft.log_retention_days),
					disabled: isLocked('log_retention_days') || !draft.log_enabled,
					help: lockHelp('log_retention_days', __('Older records are deleted daily.', 'mortar-consent')),
					onChange: (v) => set('log_retention_days', toInt(v)),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				})
			))
		);
	}

	function CategoriesTab({ draft, set, setDraft, isLocked, lockHelp }) {
		const [newId, setNewId] = useState('');
		const locked = isLocked('categories');
		const update = (index, patch) => set('categories', draft.categories.map((c, i) => (i === index ? { ...c, ...patch } : c)));
		const remove = (index) => setDraft((current) => withoutCategory(current, current.categories[index].id));
		const updateCookie = (index, cookieIndex, patch) =>
			update(index, { cookies: draft.categories[index].cookies.map((c, i) => (i === cookieIndex ? { ...c, ...patch } : c)) });
		const idTaken = draft.categories.some((c) => c.id === newId);
		const idValid = /^[a-z][a-z0-9_-]{0,31}$/.test(newId);
		const cookieFields = [
			['name', __('Cookie name', 'mortar-consent'), '_ga_*'],
			['provider', __('Provider', 'mortar-consent'), __('This site', 'mortar-consent')],
			['purpose', __('Purpose', 'mortar-consent'), ''],
			['duration', __('Duration', 'mortar-consent'), __('1 year', 'mortar-consent')],
		];

		const title = (category) => el('span', { className: 'mortar-consent-category-title' },
			category.label || category.id,
			' ',
			el('code', null, category.id),
			' ',
			category.required && badge(__('Always on', 'mortar-consent'), 'success'),
			category.required && ' ',
			el('span', { className: 'mortar-consent-category-title__meta' }, sprintf(/* translators: %d: number of cookies */ _n('%d cookie', '%d cookies', category.cookies.length, 'mortar-consent'), category.cookies.length))
		);

		return el(
			Fragment,
			null,
			locked && el(Notice, { status: 'info', isDismissible: false }, lockHelp('categories')),
			el(Section, {
				title: __('Categories', 'mortar-consent'),
				description: __('Categories appear in the preferences dialog. Cookies listed here appear in the Cookie declaration block, and are deleted when a visitor refuses or withdraws that category.', 'mortar-consent'),
			},
			el(Panel, { className: 'mortar-consent-categories' }, draft.categories.map((category, index) =>
				el(
					PanelBody,
					{ key: category.id, title: title(category), initialOpen: false },
					el(TextControl, { label: __('Name', 'mortar-consent'), value: category.label, disabled: locked, onChange: (v) => update(index, { label: v }), __nextHasNoMarginBottom: true, __next40pxDefaultSize: true }),
					el(TextareaControl, { label: __('Description', 'mortar-consent'), value: category.description, rows: 3, disabled: locked, onChange: (v) => update(index, { description: v }), __nextHasNoMarginBottom: true }),
					category.required && el('p', { className: 'description' }, __('Strictly necessary: always on, visitors cannot switch it off.', 'mortar-consent')),
					el('div', { className: 'mortar-consent-table-wrap' },
						el('table', { className: 'mortar-consent-table mortar-consent-table--edit' },
							el('thead', null, el('tr', null,
								cookieFields.map(([field, label]) => el('th', { key: field, scope: 'col' }, label)),
								el('th', { className: 'mortar-consent-table__action' }, el('span', { className: 'screen-reader-text' }, __('Actions', 'mortar-consent')))
							)),
							el('tbody', null, category.cookies.length === 0
								? el('tr', null, el('td', { colSpan: 5 }, el('p', { className: 'mortar-consent-empty' }, __('No cookies listed yet. End a name with * to match a prefix, for example _ga_*.', 'mortar-consent'))))
								: category.cookies.map((cookie, cookieIndex) =>
									el('tr', { key: cookieIndex },
										cookieFields.map(([field, label, placeholder]) =>
											el('td', { key: field, className: field === 'name' ? 'mortar-consent-mono' : undefined }, el(TextControl, {
												label,
												hideLabelFromVision: true,
												value: cookie[field],
												placeholder,
												disabled: locked,
												onChange: (v) => updateCookie(index, cookieIndex, { [field]: v }),
												__nextHasNoMarginBottom: true,
												__next40pxDefaultSize: true,
											}))
										),
										el('td', { className: 'mortar-consent-table__action' }, el(Button, {
											variant: 'tertiary',
											isDestructive: true,
											size: 'compact',
											disabled: locked,
											'aria-label': sprintf(/* translators: %s: cookie name, or its position in the list */ __('Remove cookie %s', 'mortar-consent'), cookie.name || cookieIndex + 1),
											onClick: () => update(index, { cookies: category.cookies.filter((_, i) => i !== cookieIndex) }),
										}, __('Remove', 'mortar-consent')))
									)
								))
						)
					),
					el('div', { className: 'mortar-consent-actions' },
						el(Button, {
							variant: 'secondary',
							disabled: locked,
							__next40pxDefaultSize: true,
							onClick: () => update(index, { cookies: [...category.cookies, { name: '', provider: '', purpose: '', duration: '' }] }),
						}, __('Add cookie', 'mortar-consent')),
						!category.required && el(Button, {
							variant: 'tertiary',
							isDestructive: true,
							disabled: locked,
							__next40pxDefaultSize: true,
							onClick: () => remove(index),
						}, __('Remove category', 'mortar-consent'))
					)
				)
			))),
			!locked && el(Section, {
				title: __('Add a category', 'mortar-consent'),
				description: __('For example "social" for share buttons. Then tick it for the scripts and embeds it covers.', 'mortar-consent'),
			},
			el(Card, null, el(CardBody, null,
				el('form', {
					className: 'mortar-consent-inline-form',
					onSubmit: (e) => {
						e.preventDefault();
						if (idValid && !idTaken) {
							set('categories', [...draft.categories, { id: newId, label: newId, description: '', required: false, cookies: [] }]);
							setNewId('');
						}
					},
				},
				el(TextControl, {
					label: __('ID', 'mortar-consent'),
					help: idTaken
						? __('A category with this ID already exists.', 'mortar-consent')
						: __('Lowercase letters, digits, - or _. It cannot be changed later.', 'mortar-consent'),
					value: newId,
					onChange: (v) => setNewId(v.trim().toLowerCase()),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(Button, { variant: 'secondary', type: 'submit', disabled: !idValid || idTaken, accessibleWhenDisabled: true, __next40pxDefaultSize: true }, __('Add category', 'mortar-consent'))
				)
			)))
		);
	}

	function IntegrationsTab({ draft, set, isLocked, lockHelp }) {
		const optional = draft.categories.filter((c) => !c.required);
		const cm = draft.consent_mode;
		const cmLocked = isLocked('consent_mode');
		const setSignal = (signal, ids) => set('consent_mode', { ...cm, signals: { ...cm.signals, [signal]: ids } });

		return el(
			Fragment,
			null,
			el(Section, {
				title: __('Google Consent Mode v2', 'mortar-consent'),
				aside: cm.enabled ? badge(__('On', 'mortar-consent'), 'success') : badge(__('Off', 'mortar-consent')),
				description: __('Sets "denied" defaults before any Google tag runs and updates them when visitors choose. Turn on if the site uses Google Analytics, Ads or Tag Manager.', 'mortar-consent'),
			},
			el(Fields, null,
				el(ToggleControl, {
					label: __('Send consent signals to Google tags', 'mortar-consent'),
					checked: cm.enabled,
					disabled: cmLocked,
					help: cmLocked ? lockHelp('consent_mode') : undefined,
					onChange: (enabled) => set('consent_mode', { ...cm, enabled }),
					__nextHasNoMarginBottom: true,
				}),
				cm.enabled && el('div', { className: 'mortar-consent-table-wrap' },
					el('table', { className: 'mortar-consent-table' },
						el('thead', null, el('tr', null,
							el('th', { scope: 'col' }, __('Signal', 'mortar-consent')),
							el('th', { scope: 'col' }, __('Granted when the visitor allows', 'mortar-consent'))
						)),
						el('tbody', null, Object.keys(SIGNAL_LABELS).map((signal) => {
							const ids = cm.signals[signal] || [];
							const required = ids.length > 0 && ids.every((id) => draft.categories.some((c) => c.id === id && c.required));
							return el('tr', { key: signal },
								el('th', { scope: 'row' }, SIGNAL_LABELS[signal], el('code', { className: 'mortar-consent-table__sub' }, signal)),
								el('td', null, required
									? badge(__('Always granted (strictly necessary)', 'mortar-consent'), 'success')
									: el('fieldset', { className: 'mortar-consent-checks' },
										el('legend', { className: 'screen-reader-text' }, SIGNAL_LABELS[signal]),
										optional.map((c) => el(CheckboxControl, {
											key: c.id,
											label: c.label,
											checked: ids.includes(c.id),
											disabled: cmLocked,
											onChange: (on) => setSignal(signal, on ? [...ids, c.id] : ids.filter((x) => x !== c.id)),
											__nextHasNoMarginBottom: true,
										}))
									))
							);
						}))
					)
				)
			)),
			el(Section, {
				title: __('Embedded content', 'mortar-consent'),
				description: __('Until then, visitors see a placeholder with buttons to load that one embed or allow the category.', 'mortar-consent'),
			},
			el(Fields, null,
				el(SelectControl, {
					label: __('Embeds (YouTube, Vimeo, maps…) need', 'mortar-consent'),
					value: draft.embeds.category,
					disabled: isLocked('embeds'),
					options: [{ label: __('Nothing: load them straight away', 'mortar-consent'), value: '' }, ...optional.map((c) => ({ label: c.label, value: c.id }))],
					help: isLocked('embeds') ? lockHelp('embeds') : undefined,
					onChange: (category) => set('embeds', { ...draft.embeds, category }),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				})
			)),
			el(Section, {
				title: __('Global Privacy Control', 'mortar-consent'),
				description: lockHelp('gpc_categories', __('When a visitor\'s browser sends the GPC signal, "Accept all" leaves these categories off. Visitors can still switch them on themselves.', 'mortar-consent')),
			},
			el(Fields, null,
				optional.length === 0
					? el('p', { className: 'mortar-consent-empty' }, __('There are no optional categories yet.', 'mortar-consent'))
					: el('fieldset', { className: 'mortar-consent-checks mortar-consent-checks--stack' },
						el('legend', { className: 'screen-reader-text' }, __('Categories left off for GPC visitors', 'mortar-consent')),
						optional.map((c) => el(CheckboxControl, {
							key: c.id,
							label: c.label,
							checked: draft.gpc_categories.includes(c.id),
							disabled: isLocked('gpc_categories'),
							onChange: (on) => set('gpc_categories', on ? [...draft.gpc_categories, c.id] : draft.gpc_categories.filter((x) => x !== c.id)),
							__nextHasNoMarginBottom: true,
						}))
					)
			))
		);
	}

	function ScriptsTab({ draft, set, isLocked, lockHelp }) {
		const locked = isLocked('scripts');
		const optional = draft.categories.filter((c) => !c.required);
		const update = (index, patch) => set('scripts', draft.scripts.map((r, i) => (i === index ? { ...r, ...patch } : r)));

		return el(
			Fragment,
			null,
			locked && el(Notice, { status: 'info', isDismissible: false }, lockHelp('scripts')),
			el(Section, {
				title: __('Gated scripts', 'mortar-consent'),
				description: __('Scripts registered in WordPress (by handle) only run once the visitor allows every ticked category. Scripts that depend on them wait too.', 'mortar-consent'),
			},
			el('div', { className: 'mortar-consent-table-wrap' },
				el('table', { className: 'mortar-consent-table mortar-consent-table--edit' },
					el('thead', null, el('tr', null,
						el('th', { scope: 'col', style: { width: '40%' } }, __('Script handle', 'mortar-consent')),
						el('th', { scope: 'col' }, __('Runs once the visitor allows', 'mortar-consent')),
						el('th', { className: 'mortar-consent-table__action' }, el('span', { className: 'screen-reader-text' }, __('Actions', 'mortar-consent')))
					)),
					el('tbody', null, draft.scripts.length === 0
						? el('tr', null, el('td', { colSpan: 3 }, el('p', { className: 'mortar-consent-empty' }, __('No scripts are gated yet, so every registered script runs straight away.', 'mortar-consent'))))
						: draft.scripts.map((row, index) =>
							el('tr', { key: index },
								el('td', { className: 'mortar-consent-mono' }, el(TextControl, {
									label: __('Script handle', 'mortar-consent'),
									hideLabelFromVision: true,
									placeholder: 'google-analytics',
									value: row.handle,
									disabled: locked,
									onChange: (v) => update(index, { handle: v }),
									__nextHasNoMarginBottom: true,
									__next40pxDefaultSize: true,
								})),
								el('td', null, el('fieldset', { className: 'mortar-consent-checks' },
									el('legend', { className: 'screen-reader-text' }, __('Required categories', 'mortar-consent')),
									optional.map((c) => el(CheckboxControl, {
										key: c.id,
										label: c.label,
										checked: row.categories.includes(c.id),
										disabled: locked,
										onChange: (on) => update(index, { categories: on ? [...row.categories, c.id] : row.categories.filter((id) => id !== c.id) }),
										__nextHasNoMarginBottom: true,
									})),
									row.categories.length === 0 && badge(__('Not gated', 'mortar-consent'), 'warning')
								)),
								el('td', { className: 'mortar-consent-table__action' }, el(Button, {
									variant: 'tertiary',
									isDestructive: true,
									size: 'compact',
									disabled: locked,
									'aria-label': sprintf(/* translators: %s: script handle, or its position in the list */ __('Remove script %s', 'mortar-consent'), row.handle || index + 1),
									onClick: () => set('scripts', draft.scripts.filter((_, i) => i !== index)),
								}, __('Remove', 'mortar-consent')))
							)
						))
				)
			),
			el(Button, { variant: 'secondary', disabled: locked, __next40pxDefaultSize: true, onClick: () => set('scripts', [...draft.scripts, { handle: '', categories: [] }]) }, __('Add script', 'mortar-consent'))
			),
			el(Section, {
				title: __('Hand-written tags', 'mortar-consent'),
				description: __('Tags pasted into a template or a Custom HTML block are not registered, so mark them up to wait for consent instead.', 'mortar-consent'),
			},
			el(Card, null, el(CardBody, null,
				el('p', { style: { marginTop: 0 } }, __('Change the type to text/plain and name the categories it needs:', 'mortar-consent')),
				el('pre', { className: 'mortar-consent-code' }, el('code', null, '<script type="text/plain" data-mortar-consent="analytics" src="…"></script>'))
			)))
		);
	}

	function TextsTab({ draft, data, set, isLocked, lockHelp }) {
		const locked = isLocked('texts');
		const grouped = TEXT_GROUPS.flatMap((g) => g.keys);
		const groups = TEXT_GROUPS.map((g, i) => (i === TEXT_GROUPS.length - 1
			? { ...g, keys: [...g.keys, ...data.textKeys.filter((k) => !grouped.includes(k))] }
			: g));

		const field = (key) => {
			const Control = LONG_TEXTS.includes(key) ? TextareaControl : TextControl;
			const changed = draft.texts[key] !== data.defaults.texts[key];
			return el('div', { key },
				el(Control, {
					label: TEXT_LABELS[key] || key,
					value: draft.texts[key],
					placeholder: data.defaults.texts[key],
					rows: 3,
					disabled: locked,
					onChange: (v) => set('texts', { ...draft.texts, [key]: v }),
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				changed && el('div', { className: 'mortar-consent-text-field__tools' },
					badge(__('Customised', 'mortar-consent'), 'info'),
					!locked && el(Button, {
						variant: 'link',
						onClick: () => set('texts', { ...draft.texts, [key]: data.defaults.texts[key] }),
					}, __('Reset to default', 'mortar-consent'))
				)
			);
		};

		return el(
			Fragment,
			null,
			el(Notice, { status: 'info', isDismissible: false }, lockHelp('texts', __('Fields you leave unchanged use the built-in wording, which is translated into the site\'s language. Anything you change is shown exactly as you type it and is not translated.', 'mortar-consent'))),
			groups.map((g) => el(Section, { key: g.title, title: g.title, description: g.description },
				el(Fields, null, g.keys.filter((k) => k in draft.texts).map(field))
			))
		);
	}

	function LogTab({ draft }) {
		const [result, setResult] = useState(null);
		const [query, setQuery] = useState('');
		const [searched, setSearched] = useState('');
		const [error, setError] = useState(null);
		const [loading, setLoading] = useState(false);

		const fetchRecords = (consentId) => {
			setError(null);
			setLoading(true);
			const path = '/mortar-consent/v1/records' + (consentId ? `?consent_id=${encodeURIComponent(consentId)}` : '');
			apiFetch({ path })
				.then((payload) => {
					setResult(payload);
					setSearched(consentId);
				})
				.catch((e) => setError(e.message))
				.finally(() => setLoading(false));
		};
		useEffect(() => fetchRecords(''), []);

		const total = result ? Object.values(result.summary).reduce((sum, n) => sum + n, 0) : 0;
		const methods = result ? [...new Set([...Object.keys(METHOD_LABELS), ...Object.keys(result.summary)])] : [];

		return el(
			Fragment,
			null,
			!draft.log_enabled && el(Notice, { status: 'warning', isDismissible: false }, __('Logging is switched off, so new choices are not recorded. Turn it on under General.', 'mortar-consent')),
			error && el(Notice, { status: 'error', isDismissible: false }, error),
			el(Section, { wide: true, title: __('Last 30 days', 'mortar-consent') },
				result
					? el('dl', { className: 'mortar-consent-stats' },
						el('div', { className: 'mortar-consent-stat' }, el('dt', null, __('Choices recorded', 'mortar-consent')), el('dd', null, total.toLocaleString())),
						methods.map((method) => {
							const n = result.summary[method] || 0;
							return el('div', { key: method, className: 'mortar-consent-stat' },
								el('dt', null, METHOD_LABELS[method] || method),
								el('dd', null, n.toLocaleString(), total > 0 && el('span', { className: 'mortar-consent-stat__share' }, `${Math.round((n / total) * 100)}%`))
							);
						})
					)
					: el('p', { className: 'mortar-consent-loading' }, el(Spinner), __('Loading records…', 'mortar-consent'))
			),
			el(Section, {
				wide: true,
				title: __('Records', 'mortar-consent'),
				description: __('Newest first. Download the CSV for the full log.', 'mortar-consent'),
			},
			el('div', { className: 'mortar-consent-toolbar' },
				el('form', {
					role: 'search',
					onSubmit: (e) => {
						e.preventDefault();
						fetchRecords(query.trim().toLowerCase());
					},
				},
				el(TextControl, {
					label: __('Find a consent ID', 'mortar-consent'),
					help: __('Visitors see their ID in the preferences dialog.', 'mortar-consent'),
					value: query,
					onChange: setQuery,
					__nextHasNoMarginBottom: true,
					__next40pxDefaultSize: true,
				}),
				el(Button, { variant: 'secondary', type: 'submit', isBusy: loading, __next40pxDefaultSize: true }, __('Search', 'mortar-consent')),
				searched && el(Button, {
					variant: 'tertiary',
					__next40pxDefaultSize: true,
					onClick: () => {
						setQuery('');
						fetchRecords('');
					},
				}, __('Clear', 'mortar-consent'))
				),
				el(Button, { variant: 'secondary', href: boot.exportUrl, __next40pxDefaultSize: true }, __('Download CSV', 'mortar-consent'))
			),
			result && el('div', { className: 'mortar-consent-table-wrap' },
				el('table', { className: 'mortar-consent-table' },
					el('thead', null, el('tr', null,
						el('th', { scope: 'col' }, __('Time (UTC)', 'mortar-consent')),
						el('th', { scope: 'col' }, __('Consent ID', 'mortar-consent')),
						el('th', { scope: 'col' }, __('Choice', 'mortar-consent')),
						el('th', { scope: 'col' }, __('Allowed', 'mortar-consent')),
						el('th', { scope: 'col' }, __('Policy', 'mortar-consent'))
					)),
					el('tbody', null, result.records.length === 0
						? el('tr', null, el('td', { colSpan: 5 }, el('p', { className: 'mortar-consent-empty' }, searched
							? __('No records for that consent ID.', 'mortar-consent')
							: __('No records yet.', 'mortar-consent'))))
						: result.records.map((r) => el('tr', { key: r.id },
							el('td', { className: 'mortar-consent-log-time' }, r.createdAt.replace('T', ' ').replace('Z', '')),
							el('td', null, el('code', null, r.consentId)),
							el('td', null, badge(METHOD_LABELS[r.method] || r.method, METHOD_TONES[r.method])),
							el('td', null, r.granted.join(', ') || '—'),
							el('td', null, sprintf(/* translators: %d: cookie policy version number, shown as v1, v2… */ __('v%d', 'mortar-consent'), r.policyVersion))
						)))
				)
			))
		);
	}

	const root = document.getElementById('mortar-consent-admin');
	if (root) {
		createRoot(root).render(el(App));
	}
})(window.wp);
