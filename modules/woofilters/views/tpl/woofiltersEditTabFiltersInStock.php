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
					'options' => array('dropdown' => esc_attr__( 'Dropdown', 'woo-product-filter' ), 'list' => esc_attr__( 'Checkboxes', 'woo-product-filter' ), 'switch' => esc_attr__( 'Toggle Switch', 'woo-product-filter' ) . $labelPro),
					'attrs' => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
	</div>
</div>
<?php
if ($isPro) {
	DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersSwitchType');
}
?>
<div class="row-settings-block wpfTypeSwitchable" data-type="dropdown">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Dropdown label', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Dropdown first option text.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/stock-status-filter-optionswpf/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('f_dropdown_first_option_text', array(
					'placeholder' => esc_attr__('Select all', 'woo-product-filter'),
					'attrs' => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Stock status', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Select the sorting options available for site users (min two options).', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/stock-status-filter-optionswpf/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="sub-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-value-elementor-row-revert">
			<?php 
			$options = array();
			$labels  = $this->getModel('woofilters')->getFilterLabels('InStock');
			foreach ($labels as $key => $value) {
				$options[] = array(
					'id' => 'f_stock_' . $key,
					'value' => $key, 
					'checked' => 1,
					'text' => $value,
				);
			}
			HtmlWpf::checkboxlist('f_options', array('options' => $options), '</div><div class="settings-value settings-value-elementor-row-revert">');
			?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Change status names', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Change stock status names.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/stock-status-filter-optionswpf/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="sub-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_status_names', array()); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_status_names">
			<?php HtmlWpf::text('f_stock_statuses[in]', array('placeholder' => esc_attr($labels['instock']), 'attrs' => 'class="woobewoo-flat-input"')); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_status_names">
			<?php HtmlWpf::text('f_stock_statuses[out]', array('placeholder' => esc_attr($labels['outofstock']), 'attrs' => 'class="woobewoo-flat-input"')); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_status_names">
			<?php HtmlWpf::text('f_stock_statuses[on]', array('placeholder' => esc_attr($labels['onbackorder']), 'attrs' => 'class="woobewoo-flat-input"')); ?>
		</div>
	</div>
</div>
<?php
if ($isPro) :
	DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersStock');
else :
	?>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e( 'Use as default', 'woo-product-filter' ); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'Select some stock status as default.', 'woo-product-filter' ); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value">
			<span class="wpfProLabel"><a href="<?php echo esc_url( $this->proLink ); ?>" target="_blank"><?php esc_html_e( 'PRO Option', 'woo-product-filter' ); ?></a></span>
		</div>
	</div>
</div>
<?php
endif;
