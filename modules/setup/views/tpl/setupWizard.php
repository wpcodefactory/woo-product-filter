<?php
/**
 * Product Filter by WBW - Smart setup frame
 *
 * Step pills joined by connectors across the top (.wbw-stepper), "Skip, I know what I am
 * doing", then the two column frame: left the question card (rendered by admin.setup.js into
 * #wpfSetupStep), right the preview card with the real shop page in an iframe and a desktop /
 * phone toggle; on the Publish step the cards "Put it somewhere else too" and "Speed and SEO"
 * are added under the preview card (.wbw-setup__side).
 *
 * @version 4.0.0
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this template is included from a view method, so its variables are local to that method, not globals

$wbwSteps = array(
	'welcome' => esc_html__( 'Welcome', 'woo-product-filter' ),
	'filters' => esc_html__( 'Filters', 'woo-product-filter' ),
	'look'    => esc_html__( 'Look', 'woo-product-filter' ),
	'phone'   => esc_html__( 'Phone', 'woo-product-filter' ),
	'place'   => esc_html__( 'Place', 'woo-product-filter' ),
	'speed'   => esc_html__( 'Speed and SEO', 'woo-product-filter' ),
	'publish' => esc_html__( 'Publish', 'woo-product-filter' ),
);
?>
<nav class="wbw-stepper" id="wpfSetupSteps" aria-label="<?php esc_attr_e( 'Setup steps', 'woo-product-filter' ); ?>">
	<div class="wbw-steps">
		<?php $n = 0; foreach ( $wbwSteps as $key => $label ) { ++$n; ?>
			<?php if ( $n > 1 ) { ?><span class="wbw-join" aria-hidden="true"></span><?php } ?>
			<button type="button" class="wbw-step" data-step="<?php echo esc_attr( $key ); ?>" data-n="<?php echo esc_attr( $n ); ?>" data-label="<?php echo esc_attr( $label ); ?>" <?php echo 'welcome' === $key ? 'aria-current="step"' : ''; ?>><?php echo esc_html( $n . ' ' . $label ); ?></button>
		<?php } ?>
	</div>
	<a class="wbw-steps__skip" href="<?php echo esc_url( $this->data['filtersUrl'] . '#new' ); ?>"><?php esc_html_e( 'Skip, I know what I am doing', 'woo-product-filter' ); ?></a>
</nav>

<div class="wbw-two wbw-setup" id="wpfSetup" data-step="welcome">
	<div class="wbw-card wbw-card--q wbw-setup__pane" id="wpfSetupStep" aria-live="polite">
		<span class="wbw-kicker"><?php esc_html_e( 'Step 1 of 7 · We did the homework', 'woo-product-filter' ); ?></span>
		<h1 class="wbw-h1 wbw-h1--xl" tabindex="-1"><?php esc_html_e( 'Looking at your products…', 'woo-product-filter' ); ?></h1>
		<p class="wbw-lede"><?php esc_html_e( 'Counting products, categories, sizes, colours, brands, prices and stock. Big stores run this in the background.', 'woo-product-filter' ); ?></p>
		<div class="wbw-seg" id="wpfSetupMeter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="<?php esc_attr_e( 'Scan progress', 'woo-product-filter' ); ?>"><?php echo str_repeat( '<span></span>', 13 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
	</div>

	<div class="wbw-setup__side">
	<div class="wbw-preview wbw-preview--sticky" id="wpfSetupPreview" aria-label="<?php esc_attr_e( 'Live preview of your shop', 'woo-product-filter' ); ?>">
		<div class="wbw-preview__bar">
			<span class="wbw-kicker" id="wpfSetupPreviewKicker"><?php esc_html_e( 'Your real shop page · live', 'woo-product-filter' ); ?></span>
			<div class="wbw-preview__tools">
				<div class="wbw-seg-ctl" role="group" aria-label="<?php esc_attr_e( 'Preview size', 'woo-product-filter' ); ?>">
					<button type="button" data-device="desktop" aria-pressed="true"><?php esc_html_e( 'Desktop', 'woo-product-filter' ); ?></button>
					<button type="button" data-device="phone" aria-pressed="false"><?php esc_html_e( 'Phone', 'woo-product-filter' ); ?></button>
				</div>
				<a class="wbw-btn wbw-btn--small wbw-preview__open" id="wpfSetupOpenShop" data-wbw-preview-open href="<?php echo esc_url( $this->data['shopUrl'] ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Opens your shop in a new tab. It updates by itself as you change the steps.', 'woo-product-filter' ); ?>"><?php esc_html_e( 'View on my shop', 'woo-product-filter' ); ?><svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 4h6v6"/><path d="M20 4l-9 9"/><path d="M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></svg><span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'woo-product-filter' ); ?></span></a>
			</div>
		</div>
		<div class="wbw-preview__frame" id="wpfSetupFrameWrap">
			<iframe id="wpfSetupFrame" title="<?php esc_attr_e( 'Your shop page', 'woo-product-filter' ); ?>" src="<?php echo esc_url( $this->data['shopUrl'] ); ?>"></iframe>
			<div class="wbw-preview__loading" id="wpfSetupFrameLoading"><?php esc_html_e( 'Updating the preview…', 'woo-product-filter' ); ?></div>
		</div>
		<p class="wbw-preview__note" id="wpfSetupPreviewNote"><?php esc_html_e( 'This is your shop with real products. Nothing is published yet.', 'woo-product-filter' ); ?></p>
	</div>
	</div>
</div>
