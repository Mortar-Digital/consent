import { CheckboxControl, SelectControl, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { Badge, Fields, Section } from '../components/layout.js';
import { useSettings } from '../context.js';
import { SIGNAL_LABELS } from '../labels.js';
import { toggle } from '../settings.js';

export default function IntegrationsTab() {
	return (
		<>
			<ConsentMode />
			<Embeds />
			<GlobalPrivacyControl />
		</>
	);
}

/** The categories visitors can switch on and off. */
function useOptionalCategories() {
	return useSettings().draft.categories.filter((c) => !c.required);
}

function ConsentMode() {
	const { draft, set, isLocked, lockHelp } = useSettings();
	const consentMode = draft.consent_mode;
	const locked = isLocked('consent_mode');

	return (
		<Section
			title={__('Google Consent Mode v2', 'mortar-consent')}
			aside={
				consentMode.enabled ? (
					<Badge tone="success">{__('On', 'mortar-consent')}</Badge>
				) : (
					<Badge>{__('Off', 'mortar-consent')}</Badge>
				)
			}
			description={__(
				'Sets "denied" defaults before any Google tag runs and updates them when visitors choose. Turn on if the site uses Google Analytics, Ads or Tag Manager.',
				'mortar-consent',
			)}
		>
			<Fields>
				<ToggleControl
					label={__('Send consent signals to Google tags', 'mortar-consent')}
					checked={consentMode.enabled}
					disabled={locked}
					help={locked ? lockHelp('consent_mode') : undefined}
					onChange={(enabled) => set('consent_mode', { ...consentMode, enabled })}
					__nextHasNoMarginBottom
				/>
				{consentMode.enabled && <SignalTable locked={locked} />}
			</Fields>
		</Section>
	);
}

/** Which categories grant each Consent Mode signal. */
function SignalTable({ locked }) {
	const { draft, set } = useSettings();
	const optional = useOptionalCategories();
	const consentMode = draft.consent_mode;
	const setSignal = (signal, ids) =>
		set('consent_mode', { ...consentMode, signals: { ...consentMode.signals, [signal]: ids } });
	const isRequired = (id) => draft.categories.some((c) => c.id === id && c.required);

	return (
		<div className="mortar-consent-table-wrap">
			<table className="mortar-consent-table">
				<thead>
					<tr>
						<th scope="col">{__('Signal', 'mortar-consent')}</th>
						<th scope="col">{__('Granted when the visitor allows', 'mortar-consent')}</th>
					</tr>
				</thead>
				<tbody>
					{Object.keys(SIGNAL_LABELS).map((signal) => {
						const ids = consentMode.signals[signal] || [];
						const alwaysGranted = ids.length > 0 && ids.every(isRequired);

						return (
							<tr key={signal}>
								<th scope="row">
									{SIGNAL_LABELS[signal]}
									<code className="mortar-consent-table__sub">{signal}</code>
								</th>
								<td>
									{alwaysGranted ? (
										<Badge tone="success">{__('Always granted (strictly necessary)', 'mortar-consent')}</Badge>
									) : (
										<fieldset className="mortar-consent-checks">
											<legend className="screen-reader-text">{SIGNAL_LABELS[signal]}</legend>
											{optional.map((c) => (
												<CheckboxControl
													key={c.id}
													label={c.label}
													checked={ids.includes(c.id)}
													disabled={locked}
													onChange={(on) => setSignal(signal, toggle(ids, c.id, on))}
													__nextHasNoMarginBottom
												/>
											))}
										</fieldset>
									)}
								</td>
							</tr>
						);
					})}
				</tbody>
			</table>
		</div>
	);
}

function Embeds() {
	const { draft, set, isLocked, lockHelp } = useSettings();
	const optional = useOptionalCategories();
	const locked = isLocked('embeds');

	return (
		<Section
			title={__('Embedded content', 'mortar-consent')}
			description={__(
				'Until then, visitors see a placeholder with buttons to load that one embed or allow the category.',
				'mortar-consent',
			)}
		>
			<Fields>
				<SelectControl
					label={__('Embeds (YouTube, Vimeo, maps…) need', 'mortar-consent')}
					value={draft.embeds.category}
					disabled={locked}
					options={[
						{ label: __('Nothing: load them straight away', 'mortar-consent'), value: '' },
						...optional.map((c) => ({ label: c.label, value: c.id })),
					]}
					help={locked ? lockHelp('embeds') : undefined}
					onChange={(category) => set('embeds', { ...draft.embeds, category })}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</Fields>
		</Section>
	);
}

function GlobalPrivacyControl() {
	const { draft, set, isLocked, lockHelp } = useSettings();
	const optional = useOptionalCategories();

	return (
		<Section
			title={__('Global Privacy Control', 'mortar-consent')}
			description={lockHelp(
				'gpc_categories',
				__(
					'When a visitor\'s browser sends the GPC signal, "Accept all" leaves these categories off. Visitors can still switch them on themselves.',
					'mortar-consent',
				),
			)}
		>
			<Fields>
				{optional.length === 0 ? (
					<p className="mortar-consent-empty">{__('There are no optional categories yet.', 'mortar-consent')}</p>
				) : (
					<fieldset className="mortar-consent-checks mortar-consent-checks--stack">
						<legend className="screen-reader-text">
							{__('Categories left off for GPC visitors', 'mortar-consent')}
						</legend>
						{optional.map((c) => (
							<CheckboxControl
								key={c.id}
								label={c.label}
								checked={draft.gpc_categories.includes(c.id)}
								disabled={isLocked('gpc_categories')}
								onChange={(on) => set('gpc_categories', toggle(draft.gpc_categories, c.id, on))}
								__nextHasNoMarginBottom
							/>
						))}
					</fieldset>
				)}
			</Fields>
		</Section>
	);
}
