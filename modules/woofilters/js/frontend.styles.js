/**
 * Product Filter by WBW - Look presets helper (4.0)
 *
 * Marks picked chips (.wpfChipOn) and adds a colour dot to swatch lists when the
 * term name is a CSS colour keyword (red, navy, "light blue"...). Vanilla JS.
 *
 * @version 4.0.0
 * @since   4.0.0
 */
( function () {
	'use strict';

	var probe = null;

	function cssColour( name ) {
		var key = String( name || '' ).toLowerCase().replace( /\(\d+\)/g, '' ).replace( /[^a-z\s-]/g, '' ).trim().replace( /[\s-]+/g, '' );
		if ( ! key ) {
			return '';
		}
		var alias = { multicolour: '', multicolor: '', multi: '', grey: 'gray', darkgrey: 'darkgray', lightgrey: 'lightgray', offwhite: '#f5f2ea', cream: '#f3ead7', nude: '#e3bc9a', rosegold: '#b76e79', champagne: '#f7e7ce', charcoal: '#36454f', camel: '#c19a6b', burgundy: '#800020', mustard: '#e1ad01', mint: '#98ff98', lilac: '#c8a2c8', taupe: '#483c32', denim: '#1560bd', sand: '#c2b280' };
		if ( Object.prototype.hasOwnProperty.call( alias, key ) ) {
			return alias[ key ];
		}
		if ( ! probe ) {
			probe = document.createElement( 'span' );
		}
		probe.style.color = '';
		probe.style.color = key;
		return probe.style.color ? key : '';
	}

	function decorate( wrapper ) {
		Array.prototype.forEach.call( wrapper.querySelectorAll( '.wpfFilterWrapper[data-wbw-style], .wpfStyle-chips .wpfFilterWrapper, .wpfStyle-swatches .wpfFilterWrapper' ), function ( block ) {
			var swatches = block.getAttribute( 'data-wbw-style' ) === 'swatches';
			Array.prototype.forEach.call( block.querySelectorAll( '.wpfLiLabel' ), function ( label ) {
				var input = label.querySelector( 'input' );
				if ( input ) {
					label.classList.toggle( 'wpfChipOn', input.checked );
				}
				if ( swatches && ! label.querySelector( '.wpfSwatchDot' ) ) {
					var nameEl = label.querySelector( '.wpfDisplay, .wpfAttrLabel' ) || label, colour = cssColour( nameEl.textContent );
					if ( colour ) {
						var dot = document.createElement( 'span' );
						dot.className = 'wpfSwatchDot';
						dot.style.background = colour;
						dot.setAttribute( 'aria-hidden', 'true' );
						label.insertBefore( dot, label.firstChild );
					}
				}
			} );
		} );
	}

	function run() {
		Array.prototype.forEach.call( document.querySelectorAll( '.wpfMainWrapper' ), decorate );
	}

	document.addEventListener( 'change', function ( e ) {
		var w = e.target.closest ? e.target.closest( '.wpfMainWrapper' ) : null;
		if ( w ) {
			decorate( w );
		}
	}, true );
	document.addEventListener( 'wpfAjaxSuccess', run );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
} )();
