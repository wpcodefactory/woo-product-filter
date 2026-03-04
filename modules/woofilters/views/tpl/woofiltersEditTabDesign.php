<div class="row row-tab" id="row-tab-design">
	<div class="sub-tab woobewoo-input-group col-xs-12">
		<a href="#sub-tab-design-general" class="button"><?php esc_html_e('General', 'woo-product-filter'); ?></a>
		<a href="#sub-tab-design-blocks" class="button disabled"><?php esc_html_e('Blocks', 'woo-product-filter'); ?></a>
		<a href="#sub-tab-design-titles" class="button disabled"><?php esc_html_e('Titles', 'woo-product-filter'); ?></a>
		<a href="#sub-tab-design-buttons" class="button disabled"><?php esc_html_e('Buttons', 'woo-product-filter'); ?></a>
		<a href="#sub-tab-design-floating" class="button disabled"><?php esc_html_e('Floating mode', 'woo-product-filter'); ?></a>
	</div>
	<div class="col-xs-12 sub-tab-content" id="sub-tab-design-general">
		<div class="settings-block-title">
			<?php esc_html_e('General styles', 'woo-product-filter'); ?>
		</div>
		<div class="row row-settings-block">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Filter Width', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Set the filter width in pixels or percent.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/filter-and-block-widthwpf/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
			</div>
			<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
				<div class="settings-value settings-w50">
					<div class="settings-value-label woobewoo-width60">
						<?php esc_html_e('desktop', 'woo-product-filter'); ?>
					</div>
				</div>
				<div class="settings-value settings-w50">
					<?php
						HtmlWpf::text('settings[filter_width]', array(
							'value' => isset($this->settings['settings']['filter_width']) ? $this->settings['settings']['filter_width'] : '100',
							'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"'));
						HtmlWpf::selectbox('settings[filter_width_in]', array(
							'options' => array('%' => '%', 'px' => 'px'),
							'value' => ( isset($this->settings['settings']['filter_width_in']) ? $this->settings['settings']['filter_width_in'] : '%' ),
							'attrs' => 'class="woobewoo-flat-input"'
						));
						?>
				</div>
				<div class="settings-value settings-w50">
					<div class="settings-value-label woobewoo-width60">
						<?php esc_html_e('mobile', 'woo-product-filter'); ?>
					</div>
				</div>
				<div class="settings-value settings-w50">
					<?php
						HtmlWpf::text('settings[filter_width_mobile]', array(
							'value' => isset($this->settings['settings']['filter_width_mobile']) ? $this->settings['settings']['filter_width_mobile'] : '100',
							'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"'));
						HtmlWpf::selectbox('settings[filter_width_in_mobile]', array(
							'options' => array('%' => '%', 'px' => 'px'),
							'value' => ( isset($this->settings['settings']['filter_width_in_mobile']) ? $this->settings['settings']['filter_width_in_mobile'] : '%' ),
							'attrs' => 'class="woobewoo-flat-input"'
						));
						?>
				</div>
			</div>
		</div>
		<div class="row row-settings-block">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Filter Block Width', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Set the filter width in pixels or percent.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/filter-and-block-widthwpf/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
			</div>
			<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
				<div class="settings-value settings-w50">
					<div class="settings-value-label woobewoo-width60">
						<?php esc_html_e('desktop', 'woo-product-filter'); ?>
					</div>
				</div>
				<div class="settings-value settings-w50">
					<?php
						HtmlWpf::text('settings[filter_block_width]', array(
							'value' => isset($this->settings['settings']['filter_block_width']) ? $this->settings['settings']['filter_block_width'] : '100',
							'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"'));
						HtmlWpf::selectbox('settings[filter_block_width_in]', array(
							'options' => array('%' => '%', 'px' => 'px'),
							'value' => ( isset($this->settings['settings']['filter_block_width_in']) ? $this->settings['settings']['filter_block_width_in'] : '%' ),
							'attrs' => 'class="woobewoo-flat-input"'
						));
						?>
				</div>
				<div class="settings-value settings-w50">
					<div class="settings-value-label woobewoo-width60">
						<?php esc_html_e('mobile', 'woo-product-filter'); ?>
					</div>
				</div>
				<div class="settings-value settings-w50">
					<?php
						HtmlWpf::text('settings[filter_block_width_mobile]', array(
							'value' => isset($this->settings['settings']['filter_block_width_mobile']) ? $this->settings['settings']['filter_block_width_mobile'] : '100',
							'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"'));
						HtmlWpf::selectbox('settings[filter_block_width_in_mobile]', array(
							'options' => array('%' => '%', 'px' => 'px'),
							'value' => ( isset($this->settings['settings']['filter_block_width_in_mobile']) ? $this->settings['settings']['filter_block_width_in_mobile'] : '%' ),
							'attrs' => 'class="woobewoo-flat-input"'
						));
						?>
				</div>
			</div>
		</div>
		<div class="row row-settings-block">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Filter Block Height', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Set the filter block height in pixels. If not filled, then the height is calculated automatically based on the content of the filter. Please note that if the value of the Maximum height in frontend of the filter is greater than the specified value, some data may be hidden.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/general-design-tab/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
			</div>
			<div class="settings-block-values col-xs-8 col-sm-9">
				<div class="settings-value">
					<?php
						HtmlWpf::text('settings[filter_block_height]', array(
						'value' => isset($this->settings['settings']['filter_block_height']) ? $this->settings['settings']['filter_block_height'] : '',
						'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"'
						));
						?>
					<div class="settings-value-label">px</div>
				</div>
			</div>
		</div>
		<div class="row row-settings-block">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e( 'Padding child list', 'woo-product-filter' ); ?>
			</div>
			<div class="settings-block-values col-xs-8 col-sm-9">
				<div class="settings-value">
					<?php
					HtmlWpf::text( 'settings[padding_child_list]', array(
						'value' => isset( $this->settings['settings']['padding_child_list'] ) ? $this->settings['settings']['padding_child_list'] : '',
						'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"'
					) );
					?>
					<div class="settings-value-label">px</div>
				</div>
			</div>
		</div>
		<div class="row row-settings-block">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('CSS editor', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Custom CSS', 'woo-product-filter'); ?>"></i>
			</div>
			<div class="settings-block-values settings-w100 col-xs-12 col-sm-9">
				<?php
					HtmlWpf::textarea('settings[css_editor]', array(
						'value' => ( isset($this->settings['settings']['css_editor']) ? stripslashes(base64_decode($this->settings['settings']['css_editor'])) : '' ),
						'auto_width' => true
					)); 
					?>
			</div>
		</div>
		<div class="row row-settings-block" data-no-preview="1">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('JS editor', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Custom JS', 'woo-product-filter'); ?>"></i>
			</div>
			<div class="settings-block-values settings-w100 col-xs-12 col-sm-9">
				<?php 
					HtmlWpf::textarea('settings[js_editor]', array(
						'value' => ( isset($this->settings['settings']['js_editor']) ? stripslashes(base64_decode($this->settings['settings']['js_editor'])) : '' ),
						'auto_width' => true
					));
					?>
			</div>
		</div>
		<div class="row row-settings-block">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Don\'t load Fontawesome', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Attention! Do not enable this option if you are not sure. It is worth using if your theme loads the Fontawesome library on its own, or if you plan to change filter icons to custom ones with CSS.', 'woo-product-filter'); ?>"></i>
			</div>
			<div class="settings-block-values col-xs-8 col-sm-9">
				<div class="settings-value settings-w100" data-no-preview="1">
					<?php 
						HtmlWpf::checkboxToggle('settings[disable_fontawesome_loading]', array(
							'checked' => ( isset($this->settings['settings']['disable_fontawesome_loading']) ? (int) $this->settings['settings']['disable_fontawesome_loading'] : '' )
						));
						?>
				</div>
			</div>
		</div>
		<div class="row row-settings-block">
			<div class="settings-block-label col-xs-4 col-sm-3">
				<?php esc_html_e('Use theme styles', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr__('Disable the use of custom plugin styles for filter elements. This option does not work in admin preview.', 'woo-product-filter'); ?>"></i>
			</div>
			<div class="settings-block-values col-xs-8 col-sm-9">
				<div class="settings-value settings-w100" data-no-preview="1">
					<?php 
						HtmlWpf::checkboxToggle('settings[disable_plugin_styles]', array(
							'checked' => ( isset($this->settings['settings']['disable_plugin_styles']) ? (int) $this->settings['settings']['disable_plugin_styles'] : '' )
						));
						?>
				</div>
			</div>
		</div>
	</div>
	<div class="col-xs-12 sub-tab-content" id="sub-tab-design-blocks">
		<div class="settings-block-title">
			<?php esc_html_e('Blocks Styling', 'woo-product-filter'); ?>
		</div>
		<?php 
		if ($isPro) {
			DispatcherWpf::doAction('addEditTabDesign', 'partEditTabDesignBlocks', $this->settings);
		} else { 
			?>
			<div class="row row-settings-block">
				<div class="settings-block-label col-xs-4 col-sm-3">
					<?php esc_html_e('Use Custom Styles', 'woo-product-filter'); ?>
					<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Choose custom styles for filter blocks. Any settings you leave blank will default.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/filter-block-design/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
				</div>
				<div class="settings-block-values col-xs-8 col-sm-9">
					<span class="settings-value wpfProLabel"><a href="<?php echo esc_url($this->proLink); ?>" target="_blank"><?php esc_html_e('PRO Option', 'woo-product-filter'); ?></a></span>
				</div>
			</div>
		<?php } ?>
	</div>
	<div class="col-xs-12 sub-tab-content" id="sub-tab-design-titles">
		<div class="settings-block-title">
			<?php esc_html_e('Titles Styling', 'woo-product-filter'); ?>
		</div>
		<?php 
		if ($isPro) {
			DispatcherWpf::doAction('addEditTabDesign', 'partEditTabDesignTitles', $this->settings);
		} else { 
			?>
			<div class="row row-settings-block">
				<div class="settings-block-label col-xs-4 col-sm-3">
					<?php esc_html_e('Use Custom Styles', 'woo-product-filter'); ?>
					<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Choose custom styles for filter titles. Any settings you leave blank will default.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/filter-title-design/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
				</div>
				<div class="settings-block-values col-xs-8 col-sm-9">
					<span class="settings-value wpfProLabel"><a href="<?php echo esc_url($this->proLink); ?>" target="_blank"><?php esc_html_e('PRO Option', 'woo-product-filter'); ?></a></span>
				</div>
			</div>
		<?php } ?>
	</div>
	<div class="col-xs-12 sub-tab-content" id="sub-tab-design-buttons">
		<div class="settings-block-title">
			<?php esc_html_e('Buttons Styling', 'woo-product-filter'); ?>
		</div>
		<?php 
		if ($isPro) {
			DispatcherWpf::doAction('addEditTabDesign', 'partEditTabDesignButtons', $this->settings);
		} else { 
			?>
			<div class="row row-settings-block">
				<div class="settings-block-label col-xs-4 col-sm-3">
					<?php esc_html_e('Use Custom Styles', 'woo-product-filter'); ?>
					<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Choose custom styles for filter buttons. Any settings you leave blank will default.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/buttons-design/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
				</div>
				<div class="settings-block-values col-xs-8 col-sm-9">
					<span class="settings-value wpfProLabel"><a href="<?php echo esc_url($this->proLink); ?>" target="_blank"><?php esc_html_e('PRO Option', 'woo-product-filter'); ?></a></span>
				</div>
			</div>
		<?php } ?>
	</div>
	<div class="col-xs-12 sub-tab-content" id="sub-tab-design-floating">
		<div class="settings-block-title">
			<?php esc_html_e('Floating Modе Options', 'woo-product-filter'); ?>
		</div>
		<?php 
		if ($isPro) {
			DispatcherWpf::doAction('addEditTabDesign', 'partEditTabDesignFloating', $this->settings, $this->filter['id']);
		} else { 
			?>
			<div class="row row-settings-block">
				<div class="settings-block-label col-xs-4 col-sm-3">
					<?php esc_html_e('Use Floating Modе', 'woo-product-filter'); ?>
					<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('The filter will be located in a popup.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/buttons-design/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
				</div>
				<div class="settings-block-values col-xs-8 col-sm-9">
					<span class="settings-value wpfProLabel"><a href="<?php echo esc_url($this->proLink); ?>" target="_blank"><?php esc_html_e('PRO Option', 'woo-product-filter'); ?></a></span>
				</div>
			</div>
		<?php } ?>
	</div>
</div>
