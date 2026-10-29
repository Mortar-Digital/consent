// Editor UI for the dynamic "Cookie settings button" block.
(function (wp) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { useBlockProps, RichText } = wp.blockEditor;
	const { createElement: el } = wp.element;
	const { __ } = wp.i18n;

	registerBlockType('mortar-consent/settings-button', {
		edit({ attributes, setAttributes }) {
			return el(RichText, {
				...useBlockProps({ className: 'mortar-consent-open' }),
				tagName: 'span',
				value: attributes.label,
				placeholder: __('Cookie settings', 'mortar-consent'),
				allowedFormats: [],
				withoutInteractiveFormatting: true,
				onChange: (label) => setAttributes({ label }),
			});
		},
		save: () => null,
	});
})(window.wp);
