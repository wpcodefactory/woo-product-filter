<?php
/**
 * Product Filter by WBW - 4.0 settings: Phone (mobile drawer), Look preset, automatic placement
 *
 * Included from woofiltersEditTabOptions.php. In the 4.0 builder these rows are moved into
 * the "Phone", "Look" and "Where it shows" groups by admin.builder.js (data-wbw-group).
 *
 * @version 4.0.0
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$wbwS = isset( $this->settings['settings'] ) && is_array( $this->settings['settings'] ) ? $this->settings['settings'] : array();
$wbwV = function ( $key, $default = '' ) use ( $wbwS ) {
	return isset( $wbwS[ $key ] ) ? $wbwS[ $key ] : $default;
};
$wbwStyles = array(
	''         => esc_html__( 'Theme look (no preset)', 'woo-product-filter' ),
	'clean'    => esc_html__( 'Clean', 'woo-product-filter' ),
	'chips'    => esc_html__( 'Chips', 'woo-product-filter' ),
	'swatches' => esc_html__( 'Swatches', 'woo-product-filter' ),
	'compact'  => esc_html__( 'Compact', 'woo-product-filter' ),
);
?>
<div class="col-xs-12 sub-tab-content" id="sub-tab-options-phone" data-wbw-group="phone">
	<div class="settings-block-title"><?php esc_html_e( 'Phone', 'woo-product-filter' ); ?></div>
	<div class="woobewoo_row row-settings-block">
		<div class="settings-block-label col-xs-4 col-lg-3">
			<?php esc_html_e( 'Open as a drawer on phones', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__( 'Under 768px the filter moves into a bottom sheet. A sticky "Filters" button opens it; shoppers see their picks as chips and a button with the number of matching products.', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values col-xs-6 col-sm-8 col-lg-9">
			<div class="settings-value settings-w100">
				<?php WooBeWoo_PF_Html::checkboxToggle( 'settings[mobile_drawer]', array( 'checked' => (int) $wbwV( 'mobile_drawer', 0 ) ) ); ?>
			</div>
			<div class="settings-value settings-w100 <?php echo (int) $wbwV( 'mobile_drawer', 0 ) ? '' : 'wpfHidden'; ?>" data-parent="settings[mobile_drawer]">
				<div class="settings-value-label"><?php esc_html_e( 'Button text', 'woo-product-filter' ); ?></div>
				<?php WooBeWoo_PF_Html::text( 'settings[mobile_drawer_text]', array( 'value' => $wbwV( 'mobile_drawer_text', '' ), 'placeholder' => esc_attr__( 'Filters', 'woo-product-filter' ), 'attrs' => 'class="woobewoo-flat-input"' ) ); ?>
			</div>
			<div class="settings-value settings-w100 <?php echo (int) $wbwV( 'mobile_drawer', 0 ) ? '' : 'wpfHidden'; ?>" data-parent="settings[mobile_drawer]">
				<div class="settings-value-label"><?php esc_html_e( 'Show the picked choices as chips', 'woo-product-filter' ); ?></div>
				<?php WooBeWoo_PF_Html::checkboxToggle( 'settings[mobile_drawer_chips]', array( 'checked' => (int) $wbwV( 'mobile_drawer_chips', 1 ) ) ); ?>
			</div>
			<div class="settings-value settings-w100 <?php echo (int) $wbwV( 'mobile_drawer', 0 ) ? '' : 'wpfHidden'; ?>" data-parent="settings[mobile_drawer]">
				<div class="settings-value-label"><?php esc_html_e( 'Show a "Show N results" button', 'woo-product-filter' ); ?></div>
				<?php WooBeWoo_PF_Html::checkboxToggle( 'settings[mobile_drawer_results]', array( 'checked' => (int) $wbwV( 'mobile_drawer_results', 1 ) ) ); ?>
			</div>
			<div class="settings-value settings-w100 <?php echo (int) $wbwV( 'mobile_drawer', 0 ) ? '' : 'wpfHidden'; ?>" data-parent="settings[mobile_drawer]">
				<div class="settings-value-label"><?php esc_html_e( 'Use the drawer on every screen size', 'woo-product-filter' ); ?></div>
				<?php WooBeWoo_PF_Html::checkboxToggle( 'settings[mobile_drawer_always]', array( 'checked' => (int) $wbwV( 'mobile_drawer_always', 0 ) ) ); ?>
			</div>
		</div>
	</div>
</div>
<div class="col-xs-12 sub-tab-content" id="sub-tab-options-look" data-wbw-group="look">
	<div class="settings-block-title"><?php esc_html_e( 'Look preset', 'woo-product-filter' ); ?></div>
	<div class="woobewoo_row row-settings-block">
		<div class="settings-block-label col-xs-4 col-lg-3">
			<?php esc_html_e( 'Preset', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__( 'Clean: checkbox lists. Chips: round pills. Swatches: colour dots for colour blocks, chips for the rest. Compact: dropdowns in a tight row.', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values col-xs-6 col-sm-8 col-lg-9">
			<div class="settings-value settings-w100">
				<?php WooBeWoo_PF_Html::selectbox( 'settings[wbw_style]', array( 'options' => $wbwStyles, 'value' => $wbwV( 'wbw_style', '' ), 'attrs' => 'class="woobewoo-flat-input"' ) ); ?>
			</div>
			<div class="settings-value settings-w100">
				<div class="settings-value-label"><?php esc_html_e( 'Accent colour', 'woo-product-filter' ); ?></div>
				<?php WooBeWoo_PF_Html::text( 'settings[wbw_accent]', array( 'value' => $wbwV( 'wbw_accent', '' ), 'placeholder' => '#171509', 'attrs' => 'class="woobewoo-flat-input" pattern="^#[0-9a-fA-F]{6}$"' ) ); ?>
			</div>
		</div>
	</div>
</div>
<div class="col-xs-12 sub-tab-content" id="sub-tab-options-place" data-wbw-group="where">
	<div class="settings-block-title"><?php esc_html_e( 'Put it on the shop automatically', 'woo-product-filter' ); ?></div>
	<div class="woobewoo_row row-settings-block">
		<div class="settings-block-label col-xs-4 col-lg-3">
			<?php esc_html_e( 'Automatic placement', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__( 'The plugin prints the filter for you: above the products, in the shop sidebar, or as a drawer button only. Pick "I place it myself" to use the shortcode, widget or block.', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values col-xs-6 col-sm-8 col-lg-9">
			<div class="settings-value settings-w100">
				<?php
				WooBeWoo_PF_Html::selectbox(
					'settings[wbw_placement]',
					array(
						'options' => array(
							''          => esc_html__( 'I place it myself (shortcode, widget or block)', 'woo-product-filter' ),
							'above'     => esc_html__( 'Above the products', 'woo-product-filter' ),
							'sidebar'   => esc_html__( 'In the shop sidebar', 'woo-product-filter' ),
							'drawer'    => esc_html__( 'Drawer only (a button on every screen)', 'woo-product-filter' ),
						),
						'value'   => in_array( $wbwV( 'wbw_placement', '' ), array( 'above', 'sidebar', 'drawer' ), true ) ? $wbwV( 'wbw_placement', '' ) : '',
						'attrs'   => 'class="woobewoo-flat-input"',
					)
				);
				?>
			</div>
		</div>
	</div>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
