(function (blocks, element, blockEditor, serverSideRender, i18n) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;

	blocks.registerBlockType('agentid-sso/login-button', {
		edit: function (props) {
			var InspectorControls = blockEditor.InspectorControls;
			var PanelBody = wp.components.PanelBody;
			var TextControl = wp.components.TextControl;

			return el(
				element.Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __('Button', 'agent-sso-for-agentid'), initialOpen: true },
						el(TextControl, {
							label: __('Label (blank = default)', 'agent-sso-for-agentid'),
							value: props.attributes.label || '',
							onChange: function (value) {
								props.setAttributes({ label: value });
							}
						})
					)
				),
				el(serverSideRender, {
					block: 'agentid-sso/login-button',
					attributes: props.attributes
				})
			);
		},
		save: function () {
			return null; // Server-rendered.
		}
	});
})(window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.serverSideRender, window.wp.i18n);
