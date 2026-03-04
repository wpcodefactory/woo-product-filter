<?php
if ($isPro) {
	DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersVendors');
} else {
	?>
	<div class="row-settings-block col-md-12">
		<?php if (FrameWpf::_()->isWCLicense()) { ?>
		<img class="wpfProAd" src="<?php echo esc_url($adPath . 'vendors.png'); ?>">
		<?php } else { ?>
		<a href="<?php echo esc_url('https://' . WPF_WP_PLUGIN_URL . '/plugins/woocommerce-filter/'); ?>" target="_blank">
			<img class="wpfProAd" src="<?php echo esc_url($adPath . 'vendors.png'); ?>">
		</a>
		<?php } ?>
	</div>
<?php } ?>
