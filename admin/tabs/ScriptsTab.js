import { Button, Card, CardBody, CheckboxControl, Notice, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { ActionHeader, Badge, EmptyRow, Section } from '../components/layout.js';
import { useSettings } from '../context.js';
import { toggle } from '../settings.js';

export default function ScriptsTab() {
	const { isLocked, lockHelp } = useSettings();
	const locked = isLocked('scripts');

	return (
		<>
			{locked && (
				<Notice status="info" isDismissible={false}>
					{lockHelp('scripts')}
				</Notice>
			)}
			<GatedScripts locked={locked} />
			<HandWrittenTags />
		</>
	);
}

function GatedScripts({ locked }) {
	const { draft, set } = useSettings();
	const optional = draft.categories.filter((c) => !c.required);
	const update = (index, patch) =>
		set(
			'scripts',
			draft.scripts.map((row, i) => (i === index ? { ...row, ...patch } : row)),
		);

	return (
		<Section
			title={__('Gated scripts', 'mortar-consent')}
			description={__(
				'Scripts registered in WordPress (by handle) only run once the visitor allows every ticked category. Scripts that depend on them wait too.',
				'mortar-consent',
			)}
		>
			<div className="mortar-consent-table-wrap">
				<table className="mortar-consent-table mortar-consent-table--edit">
					<thead>
						<tr>
							<th scope="col" style={{ width: '40%' }}>
								{__('Script handle', 'mortar-consent')}
							</th>
							<th scope="col">{__('Runs once the visitor allows', 'mortar-consent')}</th>
							<ActionHeader />
						</tr>
					</thead>
					<tbody>
						{draft.scripts.length === 0 ? (
							<EmptyRow columns={3}>
								{__('No scripts are gated yet, so every registered script runs straight away.', 'mortar-consent')}
							</EmptyRow>
						) : (
							draft.scripts.map((row, index) => (
								<tr key={index}>
									<td className="mortar-consent-mono">
										<TextControl
											label={__('Script handle', 'mortar-consent')}
											hideLabelFromVision
											placeholder="google-analytics"
											value={row.handle}
											disabled={locked}
											onChange={(handle) => update(index, { handle })}
											__nextHasNoMarginBottom
											__next40pxDefaultSize
										/>
									</td>
									<td>
										<fieldset className="mortar-consent-checks">
											<legend className="screen-reader-text">{__('Required categories', 'mortar-consent')}</legend>
											{optional.map((c) => (
												<CheckboxControl
													key={c.id}
													label={c.label}
													checked={row.categories.includes(c.id)}
													disabled={locked}
													onChange={(on) => update(index, { categories: toggle(row.categories, c.id, on) })}
													__nextHasNoMarginBottom
												/>
											))}
											{row.categories.length === 0 && <Badge tone="warning">{__('Not gated', 'mortar-consent')}</Badge>}
										</fieldset>
									</td>
									<td className="mortar-consent-table__action">
										<Button
											variant="tertiary"
											isDestructive
											size="compact"
											disabled={locked}
											aria-label={sprintf(
												/* translators: %s: script handle, or its position in the list */
												__('Remove script %s', 'mortar-consent'),
												row.handle || index + 1,
											)}
											onClick={() =>
												set(
													'scripts',
													draft.scripts.filter((_, i) => i !== index),
												)
											}
										>
											{__('Remove', 'mortar-consent')}
										</Button>
									</td>
								</tr>
							))
						)}
					</tbody>
				</table>
			</div>
			<Button
				variant="secondary"
				disabled={locked}
				__next40pxDefaultSize
				onClick={() => set('scripts', [...draft.scripts, { handle: '', categories: [] }])}
			>
				{__('Add script', 'mortar-consent')}
			</Button>
		</Section>
	);
}

function HandWrittenTags() {
	return (
		<Section
			title={__('Hand-written tags', 'mortar-consent')}
			description={__(
				'Tags pasted into a template or a Custom HTML block are not registered, so mark them up to wait for consent instead.',
				'mortar-consent',
			)}
		>
			<Card>
				<CardBody>
					<p style={{ marginTop: 0 }}>
						{__('Change the type to text/plain and name the categories it needs:', 'mortar-consent')}
					</p>
					<pre className="mortar-consent-code">
						<code>{'<script type="text/plain" data-mortar-consent="analytics" src="…"></script>'}</code>
					</pre>
				</CardBody>
			</Card>
		</Section>
	);
}
