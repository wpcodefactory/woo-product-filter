<?php
/**
 * Product Filter by WBW - Woofilters Edit Tab Filters Brand
 *
 * WooCommerce Brands (product_brand) block settings. Free since 4.0.0: show as, dropdown label,
 * count, hide empty, pick brands, logos (list or swatch tiles), search, max height.
 * When Pro is active its own template answers through the addEditTabFilters action.
 *
 * @version 4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- this template is included from a view method, so its variables are local to that method, not globals

if ( ! apply_filters( 'woobewoo_pf_is_pro', false ) ) {
	WooBeWoo_PF_View::display( 'woofiltersEditTabCommonTitle' );
	$wpfBrandTerms = taxonomy_exists( 'product_brand' ) ? get_terms( // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		array(
			'taxonomy'   => 'product_brand',
			'hide_empty' => false,
			'orderby'    => 'name',
			'order'      => 'asc',
		)
	) : array();
	$wpfBrandOptions = array(); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	if ( ! is_wp_error( $wpfBrandTerms ) ) {
		foreach ( $wpfBrandTerms as $wpfBrandTerm ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			$wpfBrandOptions[ $wpfBrandTerm->term_id ] = $wpfBrandTerm->name;
		}
	}
	?>
	<div class="row-settings-block">
		<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
			<?php esc_html_e( 'Show on frontend as', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'Shoppers pick one brand with a radio list or a dropdown, or more than one with a checkbox list or a multiple dropdown.', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
			<div class="settings-value settings-w100">
				<?php
				WooBeWoo_PF_Html::selectbox(
					'f_frontend_type',
					array(
						'options' => array(
							'list'         => esc_attr__( 'Radiobuttons list (single select)', 'woo-product-filter' ),
							'dropdown'     => esc_attr__( 'Dropdown (single select)', 'woo-product-filter' ),
							'mul_dropdown' => esc_attr__( 'Multiple Dropdown', 'woo-product-filter' ),
							'multi'        => esc_attr__( 'Checkbox list (multiple select)', 'woo-product-filter' ),
						),
						'attrs'   => 'class="woobewoo-flat-input"',
					)
				);
				?>
			</div>
		</div>
	</div>
	<div class="row-settings-block wpfTypeSwitchable" data-type="dropdown mul_dropdown">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Dropdown label', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'Dropdown first option text.', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
			<div class="settings-value settings-w100">
				<?php
				WooBeWoo_PF_Html::text(
					'f_dropdown_first_option_text',
					array(
						'placeholder' => esc_attr__( 'Select all', 'woo-product-filter' ),
						'attrs'       => 'class="woobewoo-flat-input"',
					)
				);
				?>
			</div>
		</div>
	</div>
	<div class="row-settings-block wpfTypeSwitchable" data-type="list multi">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Show brand logos', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'Uses the brand image set under Products > Brands (or the image stored by the filter index). Brands without an image show a plain tile.', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
			<div class="settings-value settings-w100">
				<?php WooBeWoo_PF_Html::checkboxToggle( 'f_show_logos', array() ); ?>
			</div>
			<div class="settings-value settings-w100" data-parent="f_show_logos">
				<div class="settings-value-label"><?php esc_html_e( 'Logo layout', 'woo-product-filter' ); ?></div>
				<?php
				WooBeWoo_PF_Html::selectbox(
					'f_logo_layout',
					array(
						'options' => array(
							'list'   => esc_attr__( 'Logo next to the name', 'woo-product-filter' ),
							'swatch' => esc_attr__( 'Logo tiles (swatches)', 'woo-product-filter' ),
						),
						'attrs'   => 'class="woobewoo-flat-input"',
					)
				);
				?>
			</div>
		</div>
	</div>
	<div class="row-settings-block">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Sort by', 'woo-product-filter' ); ?>
		</div>
		<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
			<div class="settings-value settings-w100">
				<?php
				WooBeWoo_PF_Html::selectbox(
					'f_sort_by',
					array(
						'options' => array(
							'default' => esc_attr__( 'Default', 'woo-product-filter' ),
							'asc'     => esc_attr__( 'Name A to Z', 'woo-product-filter' ),
							'desc'    => esc_attr__( 'Name Z to A', 'woo-product-filter' ),
						),
						'attrs'   => 'class="woobewoo-flat-input"',
					)
				);
				?>
			</div>
		</div>
	</div>
	<div class="row-settings-block">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Show count', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'Show how many products each brand has.', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
			<div class="settings-value settings-w100">
				<?php WooBeWoo_PF_Html::checkboxToggle( 'f_show_count', array() ); ?>
			</div>
		</div>
	</div>
	<div class="row-settings-block">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Hide brands without products', 'woo-product-filter' ); ?>
		</div>
		<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
			<div class="settings-value settings-w100">
				<?php WooBeWoo_PF_Html::checkboxToggle( 'f_hide_empty', array() ); ?>
			</div>
			<div class="settings-value settings-w100" data-parent="f_hide_empty">
				<div class="settings-value-label"><?php esc_html_e( 'Same behavior for active filter', 'woo-product-filter' ); ?></div>
				<?php WooBeWoo_PF_Html::checkboxToggle( 'f_hide_empty_active', array() ); ?>
			</div>
		</div>
	</div>
	<div class="row-settings-block">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Product brands', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'Pick the brands to show. Leave empty to show all of them.', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
			<div class="settings-value settings-w100">
				<?php
				WooBeWoo_PF_Html::selectlist(
					'f_mlist[]',
					array(
						'options' => $wpfBrandOptions,
						'attrs'   => 'class="woobewoo-flat-input wpfMultiSelect" multiple',
					)
				);
				?>
			</div>
			<div class="settings-value settings-w100">
				<div class="settings-value-label"><?php esc_html_e( 'Order by custom', 'woo-product-filter' ); ?></div>
				<?php WooBeWoo_PF_Html::checkboxToggle( 'f_order_custom', array() ); ?>
			</div>
			<div class="settings-value settings-w100">
				<div class="settings-value-label"><?php esc_html_e( 'Make selected brands as default', 'woo-product-filter' ); ?></div>
				<?php WooBeWoo_PF_Html::checkboxToggle( 'f_hidden_brands', array() ); ?>
			</div>
		</div>
	</div>
	<div class="row-settings-block">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Exclude terms ids', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'Brand ids to leave out, like 1,2,3.', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
			<div class="settings-value settings-w100">
				<?php WooBeWoo_PF_Html::text( 'f_exclude_terms', array( 'attrs' => 'class="woobewoo-flat-input"' ) ); ?>
			</div>
		</div>
	</div>
	<div class="row-settings-block wpfTypeSwitchable" data-type="list">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Show search', 'woo-product-filter' ); ?>
		</div>
		<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
			<div class="settings-value settings-w100">
				<?php WooBeWoo_PF_Html::checkboxToggle( 'f_show_search_input', array() ); ?>
			</div>
		</div>
	</div>
	<div class="row-settings-block wpfTypeSwitchable" data-type="list multi">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Maximum height in frontend', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'In pixels. The list scrolls when it is taller. 0 for no limit.', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
			<div class="settings-value settings-w100">
				<?php WooBeWoo_PF_Html::text( 'f_max_height', array( 'attrs' => 'class="woobewoo-flat-input" type="number" min="0"', 'value' => '0' ) ); ?>
			</div>
		</div>
	</div>
	<?php
}
WooBeWoo_PF_Dispatcher::doAction( 'addEditTabFilters', 'partEditTabFiltersBrand' );
