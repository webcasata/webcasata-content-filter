( function ( blocks, element, blockEditor, components, i18n, serverSideRender ) {
	'use strict';

	var el = element.createElement;
	var Fragment = element.Fragment;
	var registerBlockType = blocks.registerBlockType;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var TextControl = components.TextControl;
	var Placeholder = components.Placeholder;
	var __ = i18n.__;

	var data = window.wbcfBlockData || { filters: [], templates: [] };

	var filterOptions = data.filters.map( function ( f ) {
		return { value: String( f.id ), label: f.title };
	} );

	var templateOptions = data.templates.map( function ( t ) {
		return { value: t.value, label: t.label };
	} );

	registerBlockType( 'webcasata/content-filter', {
		title: __( 'Webcasata Content Filter', 'webcasata-content-filter' ),
		description: __( 'Embed one of your tabbed, AJAX-filtered post showcases.', 'webcasata-content-filter' ),
		icon: 'filter',
		category: 'widgets',
		keywords: [ 'filter', 'tabs', 'showcase', 'webcasata' ],
		attributes: {
			filterId: { type: 'number', default: 0 },
			overridePostsPerPage: { type: 'number', default: 0 },
			overrideTemplate: { type: 'string', default: '' }
		},

		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			var inspector = el(
				InspectorControls,
				{},
				el(
					PanelBody,
					{ title: __( 'Filter Settings', 'webcasata-content-filter' ), initialOpen: true },
					el( SelectControl, {
						label: __( 'Choose Filter', 'webcasata-content-filter' ),
						value: String( attributes.filterId || 0 ),
						options: filterOptions,
						onChange: function ( val ) {
							setAttributes( { filterId: parseInt( val, 10 ) || 0 } );
						}
					} ),
					el( TextControl, {
						label: __( 'Override Posts Per Page', 'webcasata-content-filter' ),
						type: 'number',
						min: 1,
						max: 50,
						help: __( "Leave blank to use the filter's own setting.", 'webcasata-content-filter' ),
						value: attributes.overridePostsPerPage ? String( attributes.overridePostsPerPage ) : '',
						onChange: function ( val ) {
							setAttributes( { overridePostsPerPage: val ? parseInt( val, 10 ) : 0 } );
						}
					} ),
					el( SelectControl, {
						label: __( 'Override Layout', 'webcasata-content-filter' ),
						value: attributes.overrideTemplate || '',
						options: templateOptions,
						help: __( "Leave as \"Don't override\" to use the filter's own template.", 'webcasata-content-filter' ),
						onChange: function ( val ) {
							setAttributes( { overrideTemplate: val } );
						}
					} )
				)
			);

			var preview;
			if ( ! attributes.filterId ) {
				preview = el(
					Placeholder,
					{
						icon: 'filter',
						label: __( 'Webcasata Content Filter', 'webcasata-content-filter' )
					},
					__( 'Choose a filter in the block settings (sidebar) to preview it here.', 'webcasata-content-filter' )
				);
			} else if ( serverSideRender ) {
				var ServerSideRender = serverSideRender.default || serverSideRender;
				preview = el(
					'div',
					{ className: 'wbcf-block-editor-preview' },
					el( ServerSideRender, {
						block: 'webcasata/content-filter',
						attributes: attributes
					} )
				);
			}

			return el( Fragment, {}, inspector, preview );
		},

		// Server-rendered (dynamic) block -- render_callback in PHP does the work.
		save: function () {
			return null;
		}
	} );
} )(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.i18n,
	window.wp.serverSideRender
);
