<?php
/**
 * Product Filter by WBW - "Add a block" picture cards (4.0 builder)
 *
 * Free cards: Checkboxes, Chips, Swatches, Dropdown, Price slider. Pro adds cards through
 * `woobewoo_pf_builder_block_cards` (array of cards) and prints extra markup through
 * `woobewoo_pf_builder_block_cards_html` right after the cards.
 *
 * Card keys: id, label, desc, svg (inline SVG, kses filtered here), pro (bool), href (link
 * instead of an action) or data (array of data attributes: block, type, style...).
 * admin.builder.js adds a block when a card with data-block is clicked.
 *
 * @version 4.0.0
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$wbwPic = function ( $paths ) {
	return '<svg class="wbw-pic" viewBox="0 0 24 24" aria-hidden="true">' . $paths . '</svg>';
};
$wbwCards = array(
	array( 'id' => 'checkboxes', 'label' => __( 'Checkboxes', 'woo-product-filter' ), 'desc' => __( 'Pick more than one', 'woo-product-filter' ), 'pro' => false, 'svg' => $wbwPic( '<rect x="4" y="5" width="5" height="5" rx="1"/><path d="M12 7.5h8"/><rect x="4" y="14" width="5" height="5" rx="1"/><path d="M12 16.5h6"/><path d="M5.5 7.5l1 1 2-2"/>' ), 'data' => array( 'block' => 'wpfAttribute', 'type' => 'list', 'style' => '' ) ),
	array( 'id' => 'chips', 'label' => __( 'Chips', 'woo-product-filter' ), 'desc' => __( 'Pills shoppers tap', 'woo-product-filter' ), 'pro' => false, 'svg' => $wbwPic( '<rect x="3" y="6" width="8" height="5" rx="2.5"/><rect x="13" y="6" width="8" height="5" rx="2.5"/><rect x="3" y="13" width="10" height="5" rx="2.5"/>' ), 'data' => array( 'block' => 'wpfAttribute', 'type' => 'list', 'style' => 'chips' ) ),
	array( 'id' => 'swatches', 'label' => __( 'Swatches', 'woo-product-filter' ), 'desc' => __( 'Colour dots', 'woo-product-filter' ), 'pro' => false, 'svg' => $wbwPic( '<circle cx="7" cy="9" r="3"/><circle cx="17" cy="9" r="3"/><circle cx="7" cy="17" r="3"/><circle cx="17" cy="17" r="3"/>' ), 'data' => array( 'block' => 'wpfAttribute', 'type' => 'list', 'style' => 'swatches' ) ),
	array( 'id' => 'dropdown', 'label' => __( 'Dropdown', 'woo-product-filter' ), 'desc' => __( 'Saves space', 'woo-product-filter' ), 'pro' => false, 'svg' => $wbwPic( '<rect x="3" y="8" width="18" height="8" rx="4"/><path d="M15 11l2 2 2-2"/>' ), 'data' => array( 'block' => 'wpfAttribute', 'type' => 'dropdown', 'style' => '' ) ),
	array( 'id' => 'slider', 'label' => __( 'Price slider', 'woo-product-filter' ), 'desc' => __( 'From / to', 'woo-product-filter' ), 'pro' => false, 'svg' => $wbwPic( '<path d="M4 12h16"/><circle cx="9" cy="12" r="2.5" fill="currentColor"/><circle cx="16" cy="12" r="2.5" fill="currentColor"/>' ), 'data' => array( 'block' => 'wpfPrice', 'type' => '', 'style' => '' ) ),
);
$wbwCards = apply_filters( 'woobewoo_pf_builder_block_cards', $wbwCards, $this->settings );
$wbwKses  = array(
	'svg'      => array( 'class' => true, 'viewbox' => true, 'width' => true, 'height' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'aria-hidden' => true, 'xmlns' => true ),
	'path'     => array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'fill-rule' => true ),
	'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true ),
	'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true ),
	'line'     => array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true ),
	'polyline' => array( 'points' => true, 'fill' => true, 'stroke' => true ),
	'polygon'  => array( 'points' => true, 'fill' => true, 'stroke' => true ),
	'g'        => array( 'fill' => true, 'stroke' => true, 'transform' => true ),
);
?>
<div class="wbw-addblock" id="wpfAddBlockCards">
	<span class="wbw-kicker"><?php esc_html_e( 'Add a block', 'woo-product-filter' ); ?></span>
	<div class="wbw-addblock__cards">
		<?php foreach ( (array) $wbwCards as $card ) { ?>
			<?php
			if ( empty( $card['id'] ) || empty( $card['label'] ) ) {
				continue;
			}
			$isLink = ! empty( $card['href'] );
			$attrs  = ' data-card="' . esc_attr( $card['id'] ) . '"';
			if ( ! empty( $card['data'] ) && is_array( $card['data'] ) ) {
				foreach ( $card['data'] as $dk => $dv ) {
					$attrs .= ' data-' . esc_attr( sanitize_key( $dk ) ) . '="' . esc_attr( (string) $dv ) . '"';
				}
			}
			$tag = $isLink ? 'a' : 'button';
			?>
			<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> class="wbw-pick__card wbw-pick__card--sm<?php echo ! empty( $card['pro'] ) ? ' is-pro' : ''; ?>"<?php echo $isLink ? ' href="' . esc_url( $card['href'] ) . '" target="_blank" rel="noopener"' : ' type="button"'; ?><?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- attributes escaped above ?>>
				<?php echo ! empty( $card['svg'] ) ? wp_kses( $card['svg'], $wbwKses ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses() filtered SVG ?>
				<strong><?php echo esc_html( $card['label'] ); ?></strong>
				<?php if ( ! empty( $card['desc'] ) ) { ?><span><?php echo esc_html( $card['desc'] ); ?></span><?php } ?>
				<?php if ( ! empty( $card['pro'] ) ) { ?><em class="pro_label">PRO</em><?php } ?>
			</<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
		<?php } ?>
	</div>
	<?php do_action( 'woobewoo_pf_builder_block_cards_html', $this->settings ); ?>
	<button type="button" class="wbw-link wbw-addblock__more" id="wpfAddBlockMore"><?php esc_html_e( 'More block types', 'woo-product-filter' ); ?></button>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
