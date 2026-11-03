/**
 * Product Filter by WBW - block editor script for woobewoo/product-filter (4.0)
 *
 * Plain JS, no build step: wp.element.createElement, a filter picker fed by REST wpf/v1/filters-list,
 * a "Block theme mode" toggle and a server side preview.
 */
( function ( wp ) {
	'use strict';
	if ( ! wp || ! wp.blocks || ! wp.element ) {
		return;
	}
	var el = wp.element.createElement,
		useState = wp.element.useState,
		useEffect = wp.element.useEffect,
		__ = wp.i18n.__,
		registerBlockType = wp.blocks.registerBlockType,
		InspectorControls = ( wp.blockEditor || wp.editor ).InspectorControls,
		useBlockProps = ( wp.blockEditor || wp.editor ).useBlockProps,
		PanelBody = wp.components.PanelBody,
		SelectControl = wp.components.SelectControl,
		ToggleControl = wp.components.ToggleControl,
		Placeholder = wp.components.Placeholder,
		Spinner = wp.components.Spinner,
		Button = wp.components.Button,
		ServerSideRender = wp.serverSideRender,
		apiFetch = wp.apiFetch;

	function Edit( props ) {
		var attributes = props.attributes,
			setAttributes = props.setAttributes,
			state = useState( { loading: true, filters: [], error: '' } ),
			data = state[ 0 ],
			setData = state[ 1 ];

		useEffect( function () {
			var alive = true;
			apiFetch( { path: '/wpf/v1/filters-list' } ).then( function ( list ) {
				if ( alive ) {
					setData( { loading: false, filters: list || [], error: '' } );
				}
			} ).catch( function () {
				if ( alive ) {
					setData( { loading: false, filters: [], error: __( 'Could not load the filter list.', 'woo-product-filter' ) } );
				}
			} );
			return function () {
				alive = false;
			};
		}, [] );

		var options = [ { value: 0, label: __( 'Pick a filter', 'woo-product-filter' ) } ].concat(
			data.filters.map( function ( f ) {
				return { value: f.id, label: f.title + ' (#' + f.id + ')' };
			} )
		);

		var blockProps = useBlockProps( { className: 'wpf-block-edit' } );
		var inspector = el(
			InspectorControls,
			{},
			el(
				PanelBody,
				{ title: __( 'Filter', 'woo-product-filter' ), initialOpen: true },
				data.loading ? el( Spinner, {} ) : el( SelectControl, {
					label: __( 'Which filter', 'woo-product-filter' ),
					value: attributes.filterId || 0,
					options: options,
					onChange: function ( v ) {
						setAttributes( { filterId: parseInt( v, 10 ) || 0 } );
					},
					__nextHasNoMarginBottom: true
				} ),
				data.error ? el( 'p', { className: 'wpf-block-edit__error' }, data.error ) : null,
				el( ToggleControl, {
					label: __( 'Block theme mode', 'woo-product-filter' ),
					help: __( 'Wraps the filter so it sits beside the Product Collection block and the core Product Filters block.', 'woo-product-filter' ),
					checked: !! attributes.blockTheme,
					onChange: function ( v ) {
						setAttributes( { blockTheme: !! v } );
					},
					__nextHasNoMarginBottom: true
				} ),
				el( Button, {
					variant: 'secondary',
					href: ( window.wpfBlockEditor && window.wpfBlockEditor.adminUrl ) || 'admin.php?page=woofilters-wpf',
					target: '_blank'
				}, __( 'Open the filters screen', 'woo-product-filter' ) )
			)
		);

		var body;
		if ( ! attributes.filterId ) {
			body = el( Placeholder, {
				icon: 'filter',
				label: __( 'Product Filter', 'woo-product-filter' ),
				instructions: data.loading ? __( 'Loading your filters', 'woo-product-filter' ) : ( data.filters.length ? __( 'Pick which filter to show.', 'woo-product-filter' ) : __( 'No filter yet. Create one under Product Filter > Setup.', 'woo-product-filter' ) )
			}, data.loading ? el( Spinner, {} ) : el( SelectControl, {
				value: 0,
				options: options,
				onChange: function ( v ) {
					setAttributes( { filterId: parseInt( v, 10 ) || 0 } );
				},
				__nextHasNoMarginBottom: true
			} ) );
		} else if ( ServerSideRender ) {
			body = el( 'div', { className: 'wpf-block-edit__preview', inert: '' }, el( ServerSideRender, { block: 'woobewoo/product-filter', attributes: attributes } ) );
		} else {
			body = el( 'div', { className: 'wpf-block-edit__preview' }, __( 'Filter #', 'woo-product-filter' ) + attributes.filterId );
		}

		return el( 'div', blockProps, inspector, body );
	}

	registerBlockType( 'woobewoo/product-filter', {
		edit: Edit,
		save: function () {
			return null; // Server rendered.
		}
	} );
} )( window.wp );
