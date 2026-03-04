/**
 * Product Filter by WBW - Price Thousands Separator
 *
 * @version 2.9.3
 * @since   2.9.3
 *
 * @author  woobewoo
 */

jQuery( document ).ready(
	function () {

		// Hide inputs
		jQuery( '#wpfMinPrice' ).hide();
		jQuery( '#wpfMaxPrice' ).hide();

		// Add spans
		jQuery( '#wpfMinPrice' ).after(
			'<span id="wpfMinPriceSpan">' +
				parseFloat( jQuery( '#wpfMinPrice' ).val() ).toLocaleString() +
			'</span>'
		);
		jQuery( '#wpfMaxPrice' ).after(
			'<span id="wpfMaxPriceSpan">' +
				parseFloat( jQuery( '#wpfMaxPrice' ).val() ).toLocaleString() +
			'</span>'
		);

		// On `slidechange`
		jQuery( 'body' ).on(
			'slidechange',
			'#wpfSliderRange',
			function () {
				jQuery( '#wpfMinPriceSpan' ).text(
					parseFloat( jQuery( '#wpfMinPrice' ).val() ).toLocaleString()
				);
				jQuery( '#wpfMaxPriceSpan' ).text(
					parseFloat( jQuery( '#wpfMaxPrice' ).val() ).toLocaleString()
				);
			}
		);

	}
);
