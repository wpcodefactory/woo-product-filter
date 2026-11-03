<?php
/**
 * Product Filter by WBW - Admin page shell (4.0)
 *
 * Paper ground, 200px rail with pill items, main column. The builder (woofilters_edit)
 * declares `full_width` and gets the whole frame without the rail. WordPress moves other
 * plugins' notices after `.wp-header-end`, which sits above our frame.
 *
 * @version 4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$wbwReqTab    = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getActiveTab();
$wbwTab       = isset( $this->tabs[ $wbwReqTab ] ) ? $this->tabs[ $wbwReqTab ] : array();
$wbwFullWidth = ! empty( $wbwTab['full_width'] ) || 'woofilters_edit' === $wbwReqTab;
$wbwDiag      = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getView()->getDiagnosticData();
?>
	<div class="wrap wbw-wrap woobewoo-wrap">
		<h1 class="wbw-wp-heading"><?php esc_html_e( 'Product Filter', 'woo-product-filter' ); ?></h1>
		<hr class="wp-header-end">
		<div class="wbw wbw-admin woobewoo-plugin" id="wpfAdmin" data-tab="<?php echo esc_attr( $this->activeTab ); ?>"
			data-wpf-assets="<?php echo esc_attr( wp_json_encode( $wbwDiag['assets'] ) ); ?>"
			data-wpf-plugins="<?php echo esc_attr( wp_json_encode( $wbwDiag['plugins'] ) ); ?>"
			data-wpf-env="<?php echo esc_attr( wp_json_encode( $wbwDiag['env'] ) ); ?>">
			<div class="wbw-page">
				<?php if ( ! $wbwFullWidth ) { ?>
					<nav class="wbw-rail" aria-label="<?php esc_attr_e( 'Product Filter screens', 'woo-product-filter' ); ?>">
						<?php require __DIR__ . DIRECTORY_SEPARATOR . 'optionsRail.php'; ?>
					</nav>
					<main class="wbw-main" id="wpfMain">
						<?php echo $this->content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- screen templates escape their own output ?>
					</main>
				<?php } else { ?>
					<?php echo $this->content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- screen templates escape their own output ?>
				<?php } ?>
			</div>
		</div>
	</div>
<?php if ( ! $wbwFullWidth ) { ?>
	<script id="wpf-skin-check">
	/* 4.0.0: if the skin did not apply 300 ms after the page loaded, show a diagnostic card that the owner can copy for support. */
	( function () {
		function check() {
			setTimeout( function () {
				var rail = document.querySelector( '.wbw-rail' ), root = document.getElementById( 'wpfAdmin' );
				if ( ! rail || ! root || document.getElementById( 'wpf-skin-diag' ) ) { return; }
				if ( getComputedStyle( rail ).display === 'flex' ) { return; }
				var parse = function ( a ) { try { return JSON.parse( root.getAttribute( a ) || '[]' ); } catch ( e ) { return []; } };
				var assets = parse( 'data-wpf-assets' ), plugins = parse( 'data-wpf-plugins' ), env = parse( 'data-wpf-env' );
				var esc = function ( s ) { return String( s == null ? '' : s ).replace( /[&<>"']/g, function ( c ) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ]; } ); };
				var box = document.createElement( 'div' );
				box.id = 'wpf-skin-diag';
				box.setAttribute( 'style', 'margin:16px 20px;padding:16px 20px;border:2px solid #C42B1A;border-radius:12px;background:#fff;color:#171509;font:14px/1.5 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;max-width:960px' );
				var html = '<p style="margin:0 0 8px;font-weight:700;font-size:16px">The Product Filter design did not load.</p>';
				html += '<p style="margin:0 0 12px">Copy this box and send it to support.</p>';
				html += '<p style="margin:0 0 4px"><strong>Site</strong>: ' + Object.keys( env ).map( function ( k ) { return esc( k ) + ' ' + esc( env[ k ] ); } ).join( ' · ' ) + '</p>';
				html += '<p style="margin:8px 0 4px"><strong>Our stylesheets and scripts</strong> (status of a HEAD request from this browser):</p><ul id="wpf-skin-diag-assets" style="margin:0 0 8px 18px;padding:0;list-style:disc">';
				assets.forEach( function ( a, i ) { html += '<li data-i="' + i + '" style="word-break:break-all"><code>' + esc( a.handle ) + '</code> ' + esc( a.src ) + ' - <span class="wpf-st">checking…</span></li>'; } );
				html += '</ul><p style="margin:8px 0 4px"><strong>Active plugins</strong>: ' + esc( plugins.join( ', ' ) ) + '</p>';
				box.innerHTML = html;
				root.parentNode.insertBefore( box, root );
				assets.forEach( function ( a, i ) {
					var li = box.querySelector( 'li[data-i="' + i + '"] .wpf-st' );
					fetch( a.src, { method: 'HEAD', credentials: 'same-origin', cache: 'no-store' } ).then( function ( r ) {
						li.textContent = r.status + ( r.ok ? '' : ' (' + ( r.statusText || 'error' ) + ')' );
					} ).catch( function () { li.textContent = 'blocked'; } );
				} );
			}, 300 );
		}
		if ( document.readyState === 'loading' ) { document.addEventListener( 'DOMContentLoaded', check ); } else { check(); }
	} )();
	</script>
<?php } ?>
<?php
WooBeWoo_PF_Dispatcher::doAction( 'afterWoobewooWrap' );
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
