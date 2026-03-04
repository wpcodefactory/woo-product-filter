<?php
if ($isPro) {
	DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersSearchNumber', array('attrDisplay' => $attrDisplay));
} else {
	?>
	<div class="row-settings-block col-md-12">
		<?php if (FrameWpf::_()->isWCLicense()) { ?>
		<img class="wpfProAd" src="<?php echo esc_url($adPath . 'search_number.png'); ?>">
		<?php } else { ?>
		<a href="<?php echo esc_url('https://' . WPF_WP_PLUGIN_URL . '/plugins/woocommerce-filter/'); ?>" target="_blank">
			<img class="wpfProAd" src="<?php echo esc_url($adPath . 'search_number.png'); ?>">
		</a>
		<?php } ?>
	</div>
<?php } ?>
