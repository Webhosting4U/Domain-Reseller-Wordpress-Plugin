/**
 * WH4U Domain Lookup - Gutenberg Block (Editor Script)
 *
 * Server-side rendered with InspectorControls for customization.
 * The edit function wraps ServerSideRender in a div with useBlockProps()
 * so the block is selectable, movable, and deletable in the editor.
 */
( function( wp ) {
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps      = wp.blockEditor.useBlockProps;
	var PanelBody          = wp.components.PanelBody;
	var TextControl        = wp.components.TextControl;
	var ToggleControl      = wp.components.ToggleControl;
	var SelectControl      = wp.components.SelectControl;
	var RangeControl       = wp.components.RangeControl;
	var ColorPalette       = wp.components.ColorPalette;
	var ServerSideRender   = wp.serverSideRender;
	var createElement      = wp.element.createElement;
	var __                 = wp.i18n.__;

	registerBlockType( 'wh4u/domain-lookup', {
		edit: function( props ) {
			var attributes = props.attributes;
			var blockProps = useBlockProps();

			return createElement(
				wp.element.Fragment,
				null,
				createElement(
					InspectorControls,
					null,
					createElement(
						PanelBody,
						{ title: __( 'Search Settings', 'wh4u-domains' ), initialOpen: true },
						createElement( TextControl, {
							label: __( 'Placeholder Text', 'wh4u-domains' ),
							value: attributes.placeholder,
							onChange: function( val ) {
								props.setAttributes( { placeholder: val } );
							}
						} ),
						createElement( TextControl, {
							label: __( 'Button Text', 'wh4u-domains' ),
							value: attributes.buttonText,
							onChange: function( val ) {
								props.setAttributes( { buttonText: val } );
							}
						} ),
						createElement( ToggleControl, {
							label: __( 'Show Suggestions', 'wh4u-domains' ),
							checked: attributes.showSuggestions,
							onChange: function( val ) {
								props.setAttributes( { showSuggestions: val } );
							}
						} )
					),
					createElement(
						PanelBody,
						{ title: __( 'Registration Form', 'wh4u-domains' ), initialOpen: false },
						createElement( TextControl, {
							label: __( 'Form Title', 'wh4u-domains' ),
							value: attributes.formTitle,
							onChange: function( val ) {
								props.setAttributes( { formTitle: val } );
							}
						} ),
						createElement( TextControl, {
							label: __( 'Form Description', 'wh4u-domains' ),
							value: attributes.formDescription,
							onChange: function( val ) {
								props.setAttributes( { formDescription: val } );
							}
						} )
					),
					createElement(
						PanelBody,
						{ title: __( 'Appearance', 'wh4u-domains' ), initialOpen: false },
						createElement( SelectControl, {
							label: __( 'Style Variant', 'wh4u-domains' ),
							value: attributes.styleVariant,
							options: [
								{ label: __( 'Elevated (Shadow)', 'wh4u-domains' ), value: 'elevated' },
								{ label: __( 'Flat', 'wh4u-domains' ), value: 'flat' },
								{ label: __( 'Bordered', 'wh4u-domains' ), value: 'bordered' },
								{ label: __( 'Minimal', 'wh4u-domains' ), value: 'minimal' }
							],
							onChange: function( val ) {
								props.setAttributes( { styleVariant: val } );
							}
						} ),
						createElement( RangeControl, {
							label: __( 'Border Radius (px)', 'wh4u-domains' ),
							value: parseInt( attributes.borderRadius, 10 ) || 12,
							onChange: function( val ) {
								props.setAttributes( { borderRadius: String( val ) } );
							},
							min: 0,
							max: 32,
							step: 2
						} ),
						createElement( 'div', { style: { marginTop: '12px' } },
							createElement( 'label', {
								style: {
									display: 'block',
									marginBottom: '8px',
									fontWeight: '500',
									fontSize: '11px',
									textTransform: 'uppercase'
								}
							}, __( 'Accent Color', 'wh4u-domains' ) ),
							createElement( ColorPalette, {
								value: attributes.accentColor,
								onChange: function( val ) {
									props.setAttributes( { accentColor: val || '' } );
								},
								clearable: true
							} )
						)
					)
				),
				createElement( 'div', blockProps,
					createElement( ServerSideRender, {
						block: 'wh4u/domain-lookup',
						attributes: attributes
					} )
				)
			);
		},

		save: function() {
			return null;
		}
	} );
} )( window.wp );
