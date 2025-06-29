<div class="wpfAdminFooterShell wpfHidden">
	<div class="wpfAdminFooterCell">
		<?php echo esc_html(WPF_WP_PLUGIN_NAME); ?>
		<?php esc_html_e('Version', 'woo-product-filter'); ?>:
		<a target="_blank" href="http://wordpress.org/plugins/woo-product-filter/changelog/"><?php echo esc_html(WPF_VERSION); ?></a>
	</div>
	<div class="wpfAdminFooterCell">|</div>
	<?php if (!FrameWpf::_()->getModule(implode('', array('l', 'ic', 'e', 'ns', 'e')))) { ?>
	<div class="wpfAdminFooterCell">
		<?php esc_html_e('Go', 'woo-product-filter'); ?>&nbsp;<a target="_blank" href="<?php echo esc_url($this->getModule()->getMainLink()); ?>"><?php esc_html_e('PRO', 'woo-product-filter'); ?></a>
	</div>
	<div class="wpfAdminFooterCell">|</div>
	<?php } ?>
	<div class="wpfAdminFooterCell">
		<a target="_blank" href="https://wordpress.org/support/plugin/woo-product-filter"><?php esc_html_e('Support', 'woo-product-filter'); ?></a>
	</div>
	<div class="wpfAdminFooterCell">|</div>
	<div class="wpfAdminFooterCell">
		Add your <a target="_blank" href="http://wordpress.org/support/view/plugin-reviews/woo-product-filter?filter=5#postform">&#9733;&#9733;&#9733;&#9733;&#9733;</a> on wordpress.org.
	</div>
</div>
