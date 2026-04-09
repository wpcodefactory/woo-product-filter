<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Show title label', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Show title label with open/close filter functionality. Be carefull when show it as close with "Hide filter by title click" filter oprionality. In such case users do not see filter content.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/how-to-create-product-filter-accordion/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width60">
				<?php esc_html_e('desktop', 'woo-product-filter'); ?>
			</div>
			<?php 
				HtmlWpf::selectbox('f_enable_title', array(
					'options' => array( 'no' => esc_attr__( 'No', 'woo-product-filter'), 'yes_close' => esc_attr__( 'Yes, show as close', 'woo-product-filter'), 'yes_open' => esc_attr__( 'Yes, show as opened', 'woo-product-filter') ),
					'attrs' => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label woobewoo-width60">
				<?php esc_html_e('mobile', 'woo-product-filter'); ?>
			</div>
			<?php 
				HtmlWpf::selectbox('f_enable_title_mobile', array(
					'options' => array( 'no' => esc_attr__( 'No', 'woo-product-filter'), 'yes_close' => esc_attr__( 'Yes, show as close', 'woo-product-filter'), 'yes_open' => esc_attr__( 'Yes, show as opened', 'woo-product-filter') ),
					'attrs' => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
	</div>
</div>
