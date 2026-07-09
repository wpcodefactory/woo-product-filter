<?php
/**
 * Product Filter by WBW - Woofilters Edit Tab Filters Price
 *
 * @version 3.2.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

ViewWpf::display('woofiltersEditTabCommonTitle');

$skins = array(
	'default' => esc_attr__( 'default', 'woo-product-filter' ),
);
$skins = DispatcherWpf::applyFilters( 'addPriceSkins', $skins );
?>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Filter skin', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="
		<?php
		echo esc_attr(__('Select the price filter skin.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/price-product-filter/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>')
		;
		?>
		"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
			HtmlWpf::selectbox( 'f_skin_type', array(
				'options' => $skins,
				'attrs'   => 'class="woobewoo-flat-input"'
			) );
			?>
		</div>
	</div>
</div>
<?php
DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersPriceSkin');
?>

<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Show price input fields', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_show_inputs', array('checked' => 1)); ?>
		</div>
	</div>
</div>
<div class="row-settings-block f_show_inputs_enabled_currency">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Symbol position', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::selectbox('f_currency_position', array(
					'options' => array('before' => esc_attr__( 'Before', 'woo-product-filter' ), 'after' => esc_attr__( 'After', 'woo-product-filter' )),
					'attrs' => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
	</div>
</div>
<div class="row-settings-block f_show_inputs_enabled_currency">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Show currency as', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::selectbox('f_currency_show_as', array(
					'options' => array('symbol' => esc_attr__( 'Symbol', 'woo-product-filter' ), 'code' => esc_attr__( 'Code', 'woo-product-filter' )),
					'attrs' => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
	</div>
</div>
<div class="row-settings-block f_show_inputs_enabled_tooltip">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Use text tooltip instead of input fields', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_price_tooltip_show_as', array('checked' => 1)); ?>
		</div>
	</div>
</div>
<?php
DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersPriceOptions');
