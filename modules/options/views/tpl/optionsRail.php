<?php
/**
 * Product Filter by WBW - Left rail (4.0)
 *
 * Order: Setup, then tabs with `rail_group => 'setup'` (for example "Switch from another
 * filter"), Filters, Speed and SEO, Help, Upgrade (Free only). Tabs with
 * `rail_group => 'pro'` are listed under a "Pro" heading, in the order given, with their
 * `icon_svg`. Every other tab (Overview, Settings, hidden tabs) stays off the rail.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$wbwRailIcons = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getRailIcons();
$wbwSvgKses   = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getSvgKses();
$wbwGroups    = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getRailGroups( $this->tabs );
$wbwActive    = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getRailActiveKey( $this->activeTab, $this->tabs );

$wbwRailItem = function ( $tabKey, $t ) use ( $wbwRailIcons, $wbwSvgKses, $wbwActive ) {
	$active = ( $wbwActive === $tabKey );
	$icon   = ! empty( $t['icon_svg'] ) ? wp_kses( $t['icon_svg'], $wbwSvgKses ) : ( isset( $wbwRailIcons[ $tabKey ] ) ? $wbwRailIcons[ $tabKey ] : $wbwRailIcons['default'] );
	?>
	<a class="wbw-rail__item" href="<?php echo esc_url( $t['url'] ); ?>"<?php echo $active ? ' aria-current="page"' : ''; ?><?php echo empty( $t['blank'] ) ? '' : ' target="_blank" rel="noopener"'; ?>>
		<?php echo $icon; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline SVG or wp_kses() filtered SVG ?>
		<span><?php echo esc_html( $t['label'] ); ?></span>
	</a>
	<?php
};
?>
<p class="wbw-rail__brand">
	<svg width="22" height="16" viewBox="0 0 48 35" aria-hidden="true"><path fill="currentColor" d="M37.98 1.02c.76-1.36 2.71-1.36 3.48-.01l6.25 10.93h-1.38c-.7 0-1.34.36-1.7.96-3.5 5.87-9.02 15.93-11.76 20.97-.74 1.36-2.67 1.4-3.47.08L22.8 23.09c-2.28 4.09-4.49 8.13-5.93 10.78-.74 1.36-2.67 1.4-3.47.08L6.82 22.94.28 11.9a1.95 1.95 0 0 1-.05-1.96l4.25-7.98c.76-1.36 2.71-1.37 3.48-.01l5.25 8.55c.36.65.32 1.45-.09 2.07l-5.07 8.36c-.22.33-.67 1.28-.67 1.67v.9h.83c.72 0 1.72-.95 2.07-1.59L21.98 1.02c.76-1.36 2.71-1.36 3.48-.01l6.42 10.91-1.55.02c-.7 0-1.34.36-1.7.96-1.64 2.74-3.72 6.4-5.75 10.04h1.65c.72 0 1.39-.39 1.75-1.02L37.98 1.02Z"/></svg>
	<?php esc_html_e( 'Product Filter', 'woo-product-filter' ); ?>
</p>
<?php foreach ( $wbwGroups['main'] as $tabKey => $t ) { $wbwRailItem( $tabKey, $t ); } ?>
<?php if ( ! empty( $wbwGroups['pro'] ) ) { ?>
	<div class="wbw-rail__group">
		<span class="wbw-kicker"><?php esc_html_e( 'Pro', 'woo-product-filter' ); ?></span>
		<?php foreach ( $wbwGroups['pro'] as $tabKey => $t ) { $wbwRailItem( $tabKey, $t ); } ?>
	</div>
<?php } ?>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
