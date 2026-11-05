import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner, TabPanel } from '@wordpress/components';
import { useEffect, useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

import { SettingsProvider, settingsValue } from './context.js';
import { fromDraft, toDraft } from './settings.js';
import CategoriesTab from './tabs/CategoriesTab.js';
import GeneralTab from './tabs/GeneralTab.js';
import IntegrationsTab from './tabs/IntegrationsTab.js';
import LogTab from './tabs/LogTab.js';
import ScriptsTab from './tabs/ScriptsTab.js';
import TextsTab from './tabs/TextsTab.js';

const SETTINGS_PATH = '/mortar-consent/v1/settings';

const TABS = [
	{ name: 'general', title: __('General', 'mortar-consent'), Tab: GeneralTab },
	{ name: 'categories', title: __('Categories & cookies', 'mortar-consent'), Tab: CategoriesTab },
	{ name: 'scripts', title: __('Scripts', 'mortar-consent'), Tab: ScriptsTab },
	{ name: 'integrations', title: __('Integrations', 'mortar-consent'), Tab: IntegrationsTab },
	{ name: 'texts', title: __('Wording', 'mortar-consent'), Tab: TextsTab },
	{ name: 'log', title: __('Consent log', 'mortar-consent'), Tab: LogTab },
];

/** Loads the settings, keeps the edited draft, and saves it back. */
export default function App() {
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
		apiFetch({ path: SETTINGS_PATH })
			.then(load)
			.catch((error) => setNotice({ status: 'error', message: error.message }));
	}, []);

	const dirty = useMemo(() => draft !== null && JSON.stringify(fromDraft(draft)) !== saved, [draft, saved]);

	useUnsavedChangesWarning(dirty, () => setJustSaved(false));

	if (!draft) {
		return notice ? (
			<Notice status={notice.status} isDismissible={false}>
				{notice.message}
			</Notice>
		) : (
			<p className="mortar-consent-loading">
				<Spinner />
				{__('Loading settings…', 'mortar-consent')}
			</p>
		);
	}

	const save = () => {
		setSaving(true);
		setNotice(null);
		apiFetch({ path: SETTINGS_PATH, method: 'PUT', data: { settings: fromDraft(draft) } })
			.then((payload) => {
				load(payload);
				setJustSaved(true);
			})
			.catch((error) => setNotice({ status: 'error', message: error.message }))
			.finally(() => setSaving(false));
	};

	return (
		<SettingsProvider value={settingsValue(draft, data, setDraft)}>
			<div className="mortar-consent-admin">
				{notice && (
					<Notice status={notice.status} onRemove={() => setNotice(null)}>
						{notice.message}
					</Notice>
				)}
				<TabPanel tabs={TABS} className="mortar-consent-admin__tabs">
					{({ Tab }) => (
						<div className="mortar-consent-admin__panel">
							<Tab />
						</div>
					)}
				</TabPanel>
				<SaveBar saving={saving} dirty={dirty} justSaved={justSaved} onSave={save} />
			</div>
		</SettingsProvider>
	);
}

/** While there are unsaved edits, hides the last "saved" message and warns before leaving the page. */
function useUnsavedChangesWarning(dirty, onDirty) {
	useEffect(() => {
		if (!dirty) {
			return undefined;
		}

		onDirty();
		const warn = (event) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener('beforeunload', warn);

		return () => window.removeEventListener('beforeunload', warn);
		// onDirty only resets a message, so a new function each render is not a new dependency.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [dirty]);
}

function SaveBar({ saving, dirty, justSaved, onSave }) {
	let status = null;

	if (saving) {
		status = <span className="mortar-consent-savebar__status">{__('Saving…', 'mortar-consent')}</span>;
	} else if (dirty) {
		status = (
			<span className="mortar-consent-savebar__status mortar-consent-savebar__status--dirty">
				{__('You have unsaved changes.', 'mortar-consent')}
			</span>
		);
	} else if (justSaved) {
		status = (
			<span className="mortar-consent-savebar__status mortar-consent-savebar__status--saved">
				{__('Settings saved.', 'mortar-consent')}
			</span>
		);
	}

	return (
		<div className="mortar-consent-savebar">
			<p role="status" className="mortar-consent-savebar__live">
				{status}
			</p>
			<Button
				variant="primary"
				onClick={onSave}
				isBusy={saving}
				disabled={saving || !dirty}
				accessibleWhenDisabled
				__next40pxDefaultSize
			>
				{__('Save settings', 'mortar-consent')}
			</Button>
		</div>
	);
}
