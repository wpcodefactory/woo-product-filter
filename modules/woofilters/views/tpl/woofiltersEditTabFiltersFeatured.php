<?php
	ViewWpf::display('woofiltersEditTabCommonTitle');
?>

<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Show on frontend as', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('f_frontend_type', array(
					'options' => array('list' => esc_attr__( 'Checkbox', 'woo-product-filter' ), 'switch' => esc_attr__( 'Toggle Switch', 'woo-product-filter' ) . $labelPro),
					'attrs' => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
	</div>
</div>

<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Custom title', 'woo-product-filter'); ?>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
			HtmlWpf::text('f_custom_title', array('placeholder' => esc_attr__('Featured', 'woo-product-filter'), 'attrs' => 'class="woobewoo-flat-input"'));
			?>
		</div>
	</div>
</div>

<?php
if ($isPro) {
	DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersSwitchType');
}
?>
