import { Button, Card, CardBody, TextControl, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

import { Badge, Fields, Section } from '../components/layout.js';
import { useSettings } from '../context.js';
import { toInt } from '../settings.js';

export default function GeneralTab() {
	return (
		<>
			<PolicyVersion />
			<VisitorChoices />
			<ConsentLogSettings />
		</>
	);
}

function PolicyVersion() {
	const { draft, data, set, isLocked, lockHelp } = useSettings();
	const locked = isLocked('policy_version');
	const bumped = draft.policy_version > data.effective.policy_version;

	const askAgain = () => {
		// Re-prompting every visitor needs an explicit confirmation.
		if (
			window.confirm(__('Every visitor will be asked for consent again after you save. Continue?', 'mortar-consent'))
		) {
			set('policy_version', draft.policy_version + 1);
		}
	};

	return (
		<Section
			title={__('Cookie policy version', 'mortar-consent')}
			description={__(
				'Raise the version when you add a new tracker or change how cookies are used. Every visitor is asked for consent again.',
				'mortar-consent',
			)}
		>
			<Card>
				<CardBody>
					<div className="mortar-consent-version">
						<div>
							<span className="mortar-consent-version__label">{__('Current version', 'mortar-consent')}</span>
							<span className="mortar-consent-version__value">
								{sprintf(
									/* translators: %d: cookie policy version number */
									__('Version %d', 'mortar-consent'),
									draft.policy_version,
								)}
								{bumped && <Badge tone="warning">{__('Visitors asked again on save', 'mortar-consent')}</Badge>}
							</span>
						</div>
						<Button
							variant="secondary"
							disabled={locked || bumped}
							accessibleWhenDisabled
							__next40pxDefaultSize
							onClick={askAgain}
						>
							{__('Ask all visitors again', 'mortar-consent')}
						</Button>
					</div>
					{locked && <p className="description">{lockHelp('policy_version')}</p>}
				</CardBody>
			</Card>
		</Section>
	);
}

function VisitorChoices() {
	const { draft, set, isLocked, lockHelp } = useSettings();

	return (
		<Section
			title={__('Visitor choices', 'mortar-consent')}
			description={__(
				'How long a choice lasts, where the banner links to, and how visitors reopen their preferences.',
				'mortar-consent',
			)}
		>
			<Fields>
				<TextControl
					className="mortar-consent-field--short"
					label={__('Consent lasts (days)', 'mortar-consent')}
					type="number"
					min={1}
					max={395}
					value={String(draft.lifetime_days)}
					disabled={isLocked('lifetime_days')}
					help={lockHelp(
						'lifetime_days',
						__('Visitors are asked again after this. Up to 395 days (13 months).', 'mortar-consent'),
					)}
					onChange={(value) => set('lifetime_days', toInt(value))}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<TextControl
					label={__('Cookie policy URL', 'mortar-consent')}
					type="url"
					value={draft.privacy_policy_url}
					placeholder="/cookie-policy/"
					disabled={isLocked('privacy_policy_url')}
					help={lockHelp(
						'privacy_policy_url',
						__('Linked from the banner. Use a full URL or a path such as /cookie-policy/.', 'mortar-consent'),
					)}
					onChange={(value) => set('privacy_policy_url', value.trim())}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<TextControl
					label={__('Cookie domain', 'mortar-consent')}
					value={draft.cookie_domain}
					placeholder={__('This host only', 'mortar-consent')}
					disabled={isLocked('cookie_domain')}
					help={lockHelp(
						'cookie_domain',
						__(
							'Leave empty for this host only. Use .example.com to share consent across subdomains.',
							'mortar-consent',
						),
					)}
					onChange={(value) => set('cookie_domain', value.trim().toLowerCase())}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<ToggleControl
					label={__('Show the floating "Cookie settings" button', 'mortar-consent')}
					checked={draft.reopen_button}
					disabled={isLocked('reopen_button')}
					help={lockHelp(
						'reopen_button',
						__(
							'Only turn this off if every page has another way to reopen preferences, such as the Cookie settings button block in the footer.',
							'mortar-consent',
						),
					)}
					onChange={(value) => set('reopen_button', value)}
					__nextHasNoMarginBottom
				/>
			</Fields>
		</Section>
	);
}

function ConsentLogSettings() {
	const { draft, set, isLocked, lockHelp } = useSettings();

	return (
		<Section
			title={__('Consent log', 'mortar-consent')}
			description={__(
				'Evidence of each choice, should you need to show when and how a visitor consented.',
				'mortar-consent',
			)}
		>
			<Fields>
				<ToggleControl
					label={__('Keep a consent log', 'mortar-consent')}
					checked={draft.log_enabled}
					disabled={isLocked('log_enabled')}
					help={lockHelp(
						'log_enabled',
						__(
							'Records each choice with a random ID, the categories, the policy version and the time. No IP address or browser details are stored.',
							'mortar-consent',
						),
					)}
					onChange={(value) => set('log_enabled', value)}
					__nextHasNoMarginBottom
				/>
				<TextControl
					className="mortar-consent-field--short"
					label={__('Keep log records for (days)', 'mortar-consent')}
					type="number"
					min={30}
					max={3650}
					value={String(draft.log_retention_days)}
					disabled={isLocked('log_retention_days') || !draft.log_enabled}
					help={lockHelp('log_retention_days', __('Older records are deleted daily.', 'mortar-consent'))}
					onChange={(value) => set('log_retention_days', toInt(value))}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</Fields>
		</Section>
	);
}
