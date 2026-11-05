import { Button, Notice, TextControl, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import { Badge, Fields, Section } from '../components/layout.js';
import { useSettings } from '../context.js';
import { LONG_TEXTS, TEXT_GROUPS, TEXT_LABELS } from '../labels.js';

export default function TextsTab() {
	const { draft, data, lockHelp } = useSettings();
	const grouped = TEXT_GROUPS.flatMap((group) => group.keys);
	// Keys the server knows but the groups don't list go in the last group.
	const groups = TEXT_GROUPS.map((group, i) =>
		i === TEXT_GROUPS.length - 1
			? { ...group, keys: [...group.keys, ...data.textKeys.filter((k) => !grouped.includes(k))] }
			: group,
	);

	return (
		<>
			<Notice status="info" isDismissible={false}>
				{lockHelp(
					'texts',
					__(
						"Fields you leave unchanged use the built-in wording, which is translated into the site's language. Anything you change is shown exactly as you type it and is not translated.",
						'mortar-consent',
					),
				)}
			</Notice>
			{groups.map((group) => (
				<Section key={group.title} title={group.title} description={group.description}>
					<Fields>
						{group.keys
							.filter((key) => key in draft.texts)
							.map((key) => (
								<TextField key={key} textKey={key} />
							))}
					</Fields>
				</Section>
			))}
		</>
	);
}

/** One piece of wording, with a reset when it differs from the default. */
function TextField({ textKey }) {
	const { draft, data, set, isLocked } = useSettings();
	const locked = isLocked('texts');
	const Control = LONG_TEXTS.includes(textKey) ? TextareaControl : TextControl;
	const fallback = data.defaults.texts[textKey];
	const setText = (value) => set('texts', { ...draft.texts, [textKey]: value });

	return (
		<div>
			<Control
				label={TEXT_LABELS[textKey] || textKey}
				value={draft.texts[textKey]}
				placeholder={fallback}
				rows={3}
				disabled={locked}
				onChange={setText}
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			{draft.texts[textKey] !== fallback && (
				<div className="mortar-consent-text-field__tools">
					<Badge tone="info">{__('Customised', 'mortar-consent')}</Badge>
					{!locked && (
						<Button variant="link" onClick={() => setText(fallback)}>
							{__('Reset to default', 'mortar-consent')}
						</Button>
					)}
				</div>
			)}
		</div>
	);
}
