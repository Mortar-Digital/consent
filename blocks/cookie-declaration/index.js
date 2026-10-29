// Editor UI for the dynamic "Cookie declaration" block
(function (wp) {
	'use strict';

	const { registerBlockType } = wp.blocks;
	const { useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, ToggleControl, Disabled } = wp.components;
	const { createElement: el, Fragment } = wp.element;
	const ServerSideRender = wp.serverSideRender;
	const { __ } = wp.i18n;

	registerBlockType('mortar-consent/cookie-declaration', {
		edit({ attributes, setAttributes }) {
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __('Settings', 'mortar-consent') },
						el(ToggleControl, {
							label: __('Show cookie settings button', 'mortar-consent'),
							checked: attributes.showSettingsButton,
							onChange: (showSettingsButton) => setAttributes({ showSettingsButton }),
						})
					)
				),
				el(
					'div',
					useBlockProps(),
					el(Disabled, null, el(ServerSideRender, { block: 'mortar-consent/cookie-declaration', attributes }))
				)
			);
		},
		save: () => null,
	});
})(window.wp);
