<?php
/**
 * Product Filter by WBW - Woofilters Edit Tab Filters On Sale
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

WooBeWoo_PF_View::display( 'woofiltersEditTabCommonTitle' );
?>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e( 'Show on frontend as', 'woo-product-filter' ); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				WooBeWoo_PF_Html::selectbox(
					'f_frontend_type',
					array(
						'options' => array(
							'list'   => esc_attr__( 'Checkbox', 'woo-product-filter' ),
							'switch' => esc_attr__( 'Toggle Switch', 'woo-product-filter' ) . $labelPro,
						),
						'attrs'   => 'class="woobewoo-flat-input"',
					)
				);
				?>
		</div>
	</div>
</div>
<?php WooBeWoo_PF_Dispatcher::doAction( 'addEditTabFilters', 'partEditTabFiltersSwitchType' ); ?>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e( 'Checkbox label', 'woo-product-filter' ); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				WooBeWoo_PF_Html::text(
					'f_checkbox_label',
					array(
						'placeholder' => esc_attr(
							$this->getModel( 'woofilters' )->getFilterLabels( 'OnSale' )
						),
						'attrs'       => 'class="woobewoo-flat-input"',
					)
				);
				?>
		</div>
	</div>
</div>
<?php WooBeWoo_PF_Dispatcher::doAction( 'addEditTabFilters', 'partEditTabFiltersOnSale' );
