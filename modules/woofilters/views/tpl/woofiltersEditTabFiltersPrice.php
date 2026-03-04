<?php
ViewWpf::display('woofiltersEditTabCommonTitle');

$skins = array(
	'default' => esc_attr__( 'default', 'woo-product-filter' ),
	'flat' => esc_attr__( 'Flat skin', 'woo-product-filter' ) . $labelPro,
	'big' => esc_attr__( 'Big skin', 'woo-product-filter' ) . $labelPro,
	'modern' => esc_attr__( 'Modern skin', 'woo-product-filter' ) . $labelPro,
	'sharp' => esc_attr__( 'Sharp skin', 'woo-product-filter' ) . $labelPro,
	'round' => esc_attr__( 'Round skin', 'woo-product-filter' ) . $labelPro,
	'square' => esc_attr__( 'Square skin', 'woo-product-filter' ) . $labelPro,
	'compact' => esc_attr__( 'Compact skin', 'woo-product-filter' ) . $labelPro,
	'circle' => esc_attr__( 'Circle skin', 'woo-product-filter' ) . $labelPro,
	'rail' => esc_attr__( 'Rail skin', 'woo-product-filter' ) . $labelPro,
	'trolley' => esc_attr__( 'Trolley skin', 'woo-product-filter' ) . $labelPro,
);
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
				'attrs'   => 'class="woobewoo-flat-input' . ( $isPro ? '' : ' wpfWithProAd' ) . '"'
			) );
			?>
		</div>

		<span class="wpfProLabel wpfHidden"><a href="<?php echo esc_url( $this->proLink ); ?>" target="_blank"><?php esc_html_e( 'PRO Option', 'woo-product-filter' ); ?></a></span>

	</div>
</div>
<?php 
if ($isPro) {
	DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersPriceSkin');
} else {
	foreach ($skins as $key => $value) {
		if (strpos($value, $labelPro)) {
			?>
			<div class="row-settings-block col-md-12 wpfPriceSkinPro wpfHidden" data-type="<?php echo esc_attr($key); ?>">
				<?php if (FrameWpf::_()->isWCLicense()) { ?>
				<img class="wpfProAd" src="<?php echo esc_url($adPath . 'price_skin_' . $key . '.png'); ?>">
				<?php } else { ?>
				<a href="<?php echo esc_url('https://' . WPF_WP_PLUGIN_URL . '/plugins/woocommerce-filter/'); ?>" target="_blank">
					<img class="wpfProAd" src="<?php echo esc_url($adPath . 'price_skin_' . $key . '.png'); ?>">
				</a>
				<?php } ?>
			</div>
			<?php 
		} 
		if ('square' == $key) {
			break;
		}
	}
}
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
<?php
if ($isPro) :
	?>
	<div class="row-settings-block js-block-show-currency-slider" id="jsBlockShowCurrencySlider">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e('Show currency in slider', 'woo-product-filter'); ?>
		</div>
		<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
			<div class="settings-value settings-w100">
				<?php HtmlWpf::checkboxToggle('f_price_show_currency_slider', array('checked' => 0)); ?>
			</div>
		</div>
	</div>
<?php
endif;
?>
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
if ($isPro) :
	DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersPriceOptions');
else :
	?>
	<div class="row-settings-block">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Set min/max prices', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'Set the price min/max value. Doesn\'t working with recount prices option!', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values col-xs-8 col-sm-9">
			<span class="settings-value wpfProLabel"><a href="<?php echo esc_url( $this->proLink ); ?>" target="_blank"><?php esc_html_e( 'PRO Option', 'woo-product-filter' ); ?></a></span>
		</div>
	</div>
	<div class="row-settings-block">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Set tax rates', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'The values will be changed by the specified percentage', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values col-xs-8 col-sm-9">
			<span class="settings-value wpfProLabel"><a href="<?php echo esc_url( $this->proLink ); ?>" target="_blank"><?php esc_html_e( 'PRO Option', 'woo-product-filter' ); ?></a></span>
		</div>
	</div>
<?php
	endif;
?>
