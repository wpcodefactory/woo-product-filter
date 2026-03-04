<?php
	ViewWpf::display('woofiltersEditTabCommonTitle');
?>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Show on frontend as', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('You can choose to display the sorting by dropdown or radio buttons', 'woo-product-filter')); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php
				HtmlWpf::selectbox('f_frontend_type', array(
					'options' => array(
						'dropdown' => esc_attr__( 'Dropdown (single select)', 'woo-product-filter' ),
						'mul_dropdown' => esc_attr__( 'Stylized Dropdown', 'woo-product-filter' ) . $labelPro,
						'radio' => esc_attr__( 'Radiobuttons list (single select)', 'woo-product-filter' ),
					),
					'attrs' => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Sort options', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip"  title="<?php echo esc_attr(__('Select the sorting options available for site users (min two options).', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/sort-by/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="sub-block-values settings-w100 col-xs-8 col-sm-9" id="wpfContainerSortBy">
		<?php
			$classMobile = ( UtilsWpf::isMobile() ) ? ' wpfMoveWrapMobile' : '';
		?>
		<div class="settings-value settings-value-elementor-row-revert js-wpf-row-move">
			<?php
			$options = array();
			$strMove = '';

			if (isset($this->settings['settings']['filters']['order'])) {
				$labels = $this->getModel('woofilters')->getSortByFilterLabels(json_decode($this->settings['settings']['filters']['order']));
			} else {
				$labels = array();
			}

			foreach ($labels as $key => $value) {
				$strMove = '<div class="wpfMoveWrap' . $classMobile . '"><a href="#" class="wpfMove wpfMoveUp js-wpfMove"><i class="fa fa-chevron-up"></i></a><a href="#" class="wpfMove wpfMoveDown js-wpfMove"><i class="fa fa-chevron-down"></i></a></div>';
				$options[] = array(
					'id' => 'f_sortby_' . $key,
					'value' => $key, 
					'checked' => 1, 
					'text' => '<div><input type="text" class="woobewoo-flat-input js-sortby-item" name="f_option_labels[' . $key . ']" data-name="' . $key . '" placeholder="' . esc_attr($value) . '"/></div>' . $strMove
					);
			}
			HtmlWpf::checkboxlist('f_options', array('options' => $options), '</div><div class="settings-value settings-value-elementor-row-revert js-wpf-row-move">');
			?>
		</div>
	</div>
</div>

<?php
if ( $isPro ) {
	DispatcherWpf::doAction( 'addEditTabFilters', 'partEditTabFiltersSortBy' );
}
?>
