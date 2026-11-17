<?php
/**
 * Product Filter by WBW - Help screen
 *
 * @version 4.0.0
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this template is included from a view method, so its variables are local to that method, not globals

$wbwDocs = array(
	array( __( 'Create your first filter', 'woo-product-filter' ), 'https://woobewoo.com/documentation/create-your-first-product-filter/' ),
	array( __( 'Add the filter to your shop page', 'woo-product-filter' ), 'https://woobewoo.com/documentation/how-to-add-woocommerce-product-filter-to-shop/' ),
	array( __( 'Where the filter shows', 'woo-product-filter' ), 'https://woobewoo.com/documentation/display-only-on-page-wpf/' ),
	array( __( 'Update results without reloading', 'woo-product-filter' ), 'https://woobewoo.com/documentation/enable-ajax/' ),
	array( __( 'Attribute blocks', 'woo-product-filter' ), 'https://woobewoo.com/documentation/attribute-filter-settings/' ),
	array( __( 'Elementor and other builders', 'woo-product-filter' ), 'https://woobewoo.com/documentation/compatibility-with-elementor-addons/' ),
);
?>
<div class="wbw-speed wbw-help" id="wpfHelp">
	<div class="wbw-grid2">
		<section class="wbw-card">
			<span class="wbw-kicker"><?php esc_html_e( 'Diagnostics', 'woo-product-filter' ); ?></span>
			<h2><?php esc_html_e( 'Export what support needs to know', 'woo-product-filter' ); ?></h2>
			<p class="wbw-muted"><?php esc_html_e( 'One JSON file: WordPress, WooCommerce and PHP versions, theme, builders, active plugins, your filters, index health, selector guesses, settings and version history counts. Emails and keys are removed.', 'woo-product-filter' ); ?></p>
			<a class="wbw-btn wbw-btn--primary" href="<?php echo esc_url( $this->downloadUrl ); ?>"><?php esc_html_e( 'Download diagnostics', 'woo-product-filter' ); ?></a>
		</section>
		<section class="wbw-card">
			<span class="wbw-kicker"><?php esc_html_e( 'Self test', 'woo-product-filter' ); ?></span>
			<h2><?php esc_html_e( 'Check that everything fits together', 'woo-product-filter' ); ?></h2>
			<p class="wbw-muted"><?php esc_html_e( 'Loads your shop page, finds the product loop, checks the index, cron and other filter plugins.', 'woo-product-filter' ); ?></p>
			<button type="button" class="wbw-btn wbw-btn--primary" id="wpfSelfTestRun"><?php esc_html_e( 'Run the self test', 'woo-product-filter' ); ?></button>
			<ul class="wbw-selftest-rows" id="wpfSelfTestRows" role="list" aria-live="polite"></ul>
		</section>
	</div>
	<div class="wbw-grid2">
		<section class="wbw-card">
			<span class="wbw-kicker"><?php esc_html_e( 'Docs', 'woo-product-filter' ); ?></span>
			<h2><?php esc_html_e( 'Short guides', 'woo-product-filter' ); ?></h2>
			<ul class="wbw-docs" role="list">
				<?php foreach ( $wbwDocs as $doc ) { ?>
					<li><a href="<?php echo esc_url( $doc[1] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $doc[0] ); ?></a></li>
				<?php } ?>
			</ul>
		</section>
		<section class="wbw-card wbw-card--ink">
			<span class="wbw-kicker"><?php esc_html_e( 'Support', 'woo-product-filter' ); ?></span>
			<h2><?php esc_html_e( 'Stuck? Ask us.', 'woo-product-filter' ); ?></h2>
			<p><?php esc_html_e( 'Attach the diagnostics file to your message so the first answer is the fix.', 'woo-product-filter' ); ?></p>
			<p><strong><?php esc_html_e( 'Pro: first reply within one business day.', 'woo-product-filter' ); ?></strong> <?php esc_html_e( 'Free: answered on the WordPress.org forum as time allows.', 'woo-product-filter' ); ?></p>
			<div class="wbw-actions">
				<a class="wbw-btn" href="https://wordpress.org/support/plugin/woo-product-filter/" target="_blank" rel="noopener"><?php esc_html_e( 'Free support forum', 'woo-product-filter' ); ?></a>
				<?php if ( $this->isPro ) { ?>
					<a class="wbw-btn wbw-btn--primary" href="https://woobewoo.com/support/" target="_blank" rel="noopener"><?php esc_html_e( 'Open a Pro ticket', 'woo-product-filter' ); ?></a>
				<?php } else { ?>
					<a class="wbw-btn wbw-btn--primary" href="<?php echo esc_url( $this->proLink ); ?>"><?php esc_html_e( 'See what Pro adds', 'woo-product-filter' ); ?></a>
				<?php } ?>
			</div>
		</section>
	</div>
</div>
