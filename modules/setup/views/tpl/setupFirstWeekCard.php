<?php
/**
 * Product Filter by WBW - "Your first week" card (Filters home, ink card)
 *
 * Shown 7 days after the smart setup published a filter, until dismissed.
 *
 * @version 4.0.0
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this template is included from a view method, so its variables are local to that method, not globals

$wbwFrom = date_i18n( 'j', (int) $this->card['published'] );
$wbwTo   = date_i18n( 'j M', (int) $this->card['published'] + 7 * DAY_IN_SECONDS );
?>
<section class="wbw-card wbw-card--ink wbw-first-week-card" id="wpfFirstWeekCard" data-nonce="<?php echo esc_attr( $this->nonce ); ?>" aria-label="<?php esc_attr_e( 'Your first week', 'woo-product-filter' ); ?>">
	<div class="wbw-first-week-card__text">
		<span class="wbw-kicker"><?php echo esc_html( sprintf( /* translators: 1: day, 2: day and month */ __( 'Your first week · %1$s to %2$s', 'woo-product-filter' ), $wbwFrom, $wbwTo ) ); ?></span>
		<h2><?php echo esc_html( $this->card['headline'] ); ?></h2>
		<p><?php echo esc_html( $this->card['suggestion'] ); ?></p>
	</div>
	<div class="wbw-actions">
		<a class="wbw-btn wbw-btn--primary" href="<?php echo esc_url( $this->editLink ); ?>"><?php echo esc_html( $this->card['button'] ); ?></a>
		<button type="button" class="wbw-btn wbw-btn--graphite" id="wpfFirstWeekDismiss"><?php esc_html_e( 'Keep it', 'woo-product-filter' ); ?></button>
	</div>
</section>
