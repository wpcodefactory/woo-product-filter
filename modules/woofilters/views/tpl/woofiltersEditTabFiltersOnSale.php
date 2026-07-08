<?php
/**
 * Product Filter by WBW - Woofilters Edit Tab Filters On Sale
 *
 * @version 3.1.9
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

ViewWpf::display('woofiltersEditTabCommonTitle');
?>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Show on frontend as', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				$onsaleFrontendTypes = array(
					'list' => esc_attr__( 'Checkbox', 'woo-product-filter' ),
				);
				$onsaleFrontendTypes = DispatcherWpf::applyFilters( 'getAdminFilterTypes', $onsaleFrontendTypes, 'wpfOnSale' );
				HtmlWpf::selectbox('f_frontend_type', array(
					'options' => $onsaleFrontendTypes,
					'attrs'   => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
	</div>
</div>
<?php
DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersSwitchType');
?>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Checkbox label', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				$labels = $this->getModel('woofilters')->getFilterLabels('OnSale');
				HtmlWpf::text('f_checkbox_label', array(
					'placeholder' => esc_attr($labels['onsale']),
					'attrs'       => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
	</div>
</div>
<?php
DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersOnSale');
