// Editor UI for the dynamic "Cookie settings button" block.
(function (wp) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { useBlockProps, RichText } = wp.blockEditor;
	const { createElement: el } = wp.element;
	const { __ } = wp.i18n;

	registerBlockType('md-consent/settings-button', {
		edit({ attributes, setAttributes }) {
			return el(RichText, {
				...useBlockProps({ className: 'md-consent-open' }),
				tagName: 'span',
				value: attributes.label,
				placeholder: __('Cookie settings', 'md-consent'),
				allowedFormats: [],
				withoutInteractiveFormatting: true,
				onChange: (label) => setAttributes({ label }),
			});
		},
		save: () => null,
	});
})(window.wp);
