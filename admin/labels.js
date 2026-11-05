import { __ } from '@wordpress/i18n';

export const TEXT_LABELS = {
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
	/* translators: Field label. Keep %s as typed: it shows where the provider name goes. */
	embed_notice: __('Embed placeholder text (%s = provider)', 'mortar-consent'),
	embed_load: __('"Load this content" button', 'mortar-consent'),
	/* translators: Field label. Keep %s as typed: it shows where the category name goes. */
	embed_allow: __('"Always allow" button (%s = category)', 'mortar-consent'),
	/* translators: Field label. Keep %s as typed: it shows where the category names go. */
	gpc_notice: __('Global Privacy Control notice (%s = categories)', 'mortar-consent'),
};

/** Wording grouped by where visitors see it; unknown keys join the last group. */
export const TEXT_GROUPS = [
	{
		title: __('Banner', 'mortar-consent'),
		description: __('The first thing visitors see, until they make a choice.', 'mortar-consent'),
		keys: ['banner_title', 'banner_body', 'accept_all', 'reject_all', 'manage', 'privacy_link'],
	},
	{
		title: __('Preferences dialog', 'mortar-consent'),
		description: __(
			'Where visitors choose category by category, and come back to change their mind.',
			'mortar-consent',
		),
		keys: ['dialog_title', 'dialog_body', 'save', 'close', 'always_active', 'consent_id'],
	},
	{
		title: __('Buttons, embeds and notices', 'mortar-consent'),
		description: __(
			'The floating settings button, click-to-load embed placeholders and the Global Privacy Control notice.',
			'mortar-consent',
		),
		keys: ['settings_button', 'embed_notice', 'embed_load', 'embed_allow', 'gpc_notice'],
	},
];

/** Wording long enough for a textarea. */
export const LONG_TEXTS = ['banner_body', 'dialog_body'];

export const SIGNAL_LABELS = {
	ad_storage: __('Advertising storage', 'mortar-consent'),
	ad_user_data: __('Send user data to Google for ads', 'mortar-consent'),
	ad_personalization: __('Personalised advertising', 'mortar-consent'),
	analytics_storage: __('Analytics storage', 'mortar-consent'),
	functionality_storage: __('Functionality storage', 'mortar-consent'),
	personalization_storage: __('Personalisation storage', 'mortar-consent'),
	security_storage: __('Security storage', 'mortar-consent'),
};

export const METHOD_LABELS = {
	'accept-all': __('Accepted all', 'mortar-consent'),
	'reject-all': __('Rejected all', 'mortar-consent'),
	custom: __('Chose categories', 'mortar-consent'),
};

export const METHOD_TONES = {
	'accept-all': 'success',
	'reject-all': 'error',
	custom: 'info',
};
