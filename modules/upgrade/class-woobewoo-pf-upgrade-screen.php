<?php
/**
 * Product Filter by WBW - Upgrade screen (Free only, 4.0)
 *
 * Plain cards of what Pro adds (design spec section 5), prices from the theme facts when
 * the WooBeWoo theme runs on the same site, else the fixed current prices. One primary
 * button, no dark patterns. Loaded by the options module for the `gopro` tab.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Upgrade_Screen {

	/**
	 * Prices: theme facts (wbw_data('facts')) when present, else the fixed current prices.
	 */
	public static function prices() {
		$prices = array( 'year' => '$59.99', 'lifetime' => '$99.99', 'url' => 'https://woobewoo.com/product/woocommerce-filter/' );
		if ( function_exists( 'wbw_data' ) && function_exists( 'wbw_money' ) ) {
			$facts = wbw_data( 'facts' );
			if ( is_array( $facts ) && ! empty( $facts['current']['plans'] ) && is_array( $facts['current']['plans'] ) ) {
				foreach ( $facts['current']['plans'] as $plan ) {
					if ( ! empty( $plan['annual']['price'] ) && '$59.99' === $prices['year'] ) {
						$prices['year'] = wbw_money( $plan['annual']['price'] );
					}
					if ( ! empty( $plan['lifetime']['price'] ) && '$99.99' === $prices['lifetime'] ) {
						$prices['lifetime'] = wbw_money( $plan['lifetime']['price'] );
					}
				}
			}
		}
		return apply_filters( 'woobewoo_pf_upgrade_prices', $prices );
	}

	/**
	 * What Pro adds, in plain words (design spec section 5).
	 *
	 * 4.0.0: every line is checked against what Free already ships. Basic swatches (one colour or one
	 * picture per value, modules/swatches) and the AI shopping readiness score (modules/abilities,
	 * shown on Speed and SEO) are Free, so the cards no longer sell them; Pro adds up to four colours
	 * per value and the agent screen (per ability switches, MCP endpoint, plain words bar, clean up).
	 */
	public static function cards() {
		return array(
			array(
				'title' => __( 'Insights', 'woo-product-filter' ),
				'text'  => __( 'Demand you could not serve (zero result combinations), filters nobody uses, sold out sizes shoppers wanted, revenue per filter, a weekly email and a CSV export.', 'woo-product-filter' ),
			),
			array(
				'title' => __( 'Answer pages', 'woo-product-filter' ),
				'text'  => __( 'An approval queue fed by real demand. Each row: the combination, how many shoppers asked, a suggested title, H1, meta and intro. Approve, edit or skip. Sitemap and FAQ schema on.', 'woo-product-filter' ),
			),
			array(
				'title' => __( 'AI and agents', 'woo-product-filter' ),
				'text'  => __( 'An AI and agents screen: an on/off switch per agent ability and the MCP endpoint shown. A plain words bar above the filter. An attribute clean up assistant that finds "XL" and "X-Large". The readiness score you already have in Free gets its fixes next to it, and an optional AI model refines the smart setup suggestions.', 'woo-product-filter' ),
			),
			array(
				'title' => __( 'Merchandising', 'woo-product-filter' ),
				'text'  => __( 'Pin, boost and bury rules inside filtered results with presets like "boost in stock" and "bury low margin", on a schedule.', 'woo-product-filter' ),
			),
			array(
				'title' => __( 'More blocks and looks', 'woo-product-filter' ),
				'text'  => __( 'Search by text with suggestions, search by number, vendors, multi-colour swatches (up to four colours per value; Free has one colour or one picture), sliders for any number, every slider skin, and filters on any page.', 'woo-product-filter' ),
			),
			array(
				'title' => __( 'Support within one business day', 'woo-product-filter' ),
				'text'  => __( 'First reply within one business day. That promise is public. Free stays on the wordpress.org forum.', 'woo-product-filter' ),
			),
		);
	}

	/**
	 * Render the screen.
	 */
	public static function render() {
		$p = self::prices();
		ob_start();
		?>
		<div>
			<h1 class="wbw-h1 wbw-h1--page"><?php esc_html_e( 'Upgrade', 'woo-product-filter' ); ?></h1>
			<p class="wbw-small" style="margin-top:6px"><?php esc_html_e( 'The free core block is the floor. Pro is the ceiling. Here is exactly what it adds; nothing in Free stops working.', 'woo-product-filter' ); ?></p>
		</div>
		<div class="wbw-grid2">
			<?php foreach ( self::cards() as $card ) { ?>
				<section class="wbw-card" aria-label="<?php echo esc_attr( $card['title'] ); ?>">
					<h2 class="wbw-h2"><?php echo esc_html( $card['title'] ); ?></h2>
					<p class="wbw-small"><?php echo esc_html( $card['text'] ); ?></p>
				</section>
			<?php } ?>
		</div>
		<section class="wbw-card wbw-card--ink" aria-label="<?php esc_attr_e( 'Prices', 'woo-product-filter' ); ?>" style="flex-direction:row;align-items:center;flex-wrap:wrap;gap:24px;padding:24px 28px">
			<div style="flex:1 1 320px;min-width:0">
				<span class="wbw-kicker" style="margin-bottom:6px"><?php esc_html_e( 'Pro', 'woo-product-filter' ); ?></span>
				<h2 style="font-size:22px;line-height:1.15;margin-bottom:8px"><?php echo esc_html( sprintf( /* translators: 1: yearly price, 2: lifetime price */ __( '%1$s a year, or %2$s once for life.', 'woo-product-filter' ), $p['year'], $p['lifetime'] ) ); ?></h2>
				<p class="wbw-small"><?php esc_html_e( 'One site. Updates and support included. 30 day money back, no questions.', 'woo-product-filter' ); ?></p>
			</div>
			<div class="wbw-actions">
				<a class="wbw-btn wbw-btn--primary" href="<?php echo esc_url( $p['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See Pro on woobewoo.com', 'woo-product-filter' ); ?></a>
			</div>
		</section>
		<?php
		return ob_get_clean();
	}
}
