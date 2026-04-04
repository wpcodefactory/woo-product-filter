<?php
/**
 * Product Filter by WBW - Edit Tab Filters Custom Field
 *
 * @version 3.1.4
 * @since   3.1.4
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

ViewWpf::display('woofiltersEditTabCommonTitle');
?>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Custom Field options', 'woo-product-filter'); ?>
		<span>(ACF plugin radio and checkbox fields)</span>
	</div>
	<div class="sub-block-values settings-w100 col-xs-8 col-sm-9" id="wpfContainerCustomField">
		<?php
		$classMobile = (UtilsWpf::isMobile()) ? ' wpfMoveWrapMobile' : '';
		?>
		<div class="settings-value settings-value-elementor-row-revert js-wpf-row-move">
			<?php
			$options = array();
			$strMove = '';

			// Retrieve custom fields (ACF or other custom fields)
			if (class_exists('ACF')) {
				$custom_fields = $this->getModel('woofilters')->getCustomFieldFilterOptions('product'); // Function to get available custom fields
			} else {
				$custom_fields = array();
			}
			// Loop through all custom fields and create the checkbox options
			foreach ($custom_fields as $key => $label) {
				$strMove = '<div class="wpfMoveWrap' . $classMobile . '"><a href="#" class="wpfMove wpfMoveUp js-wpfMove"><i class="fa fa-chevron-up"></i></a><a href="#" class="wpfMove wpfMoveDown js-wpfMove"><i class="fa fa-chevron-down"></i></a></div>';

				$options[] = array(
					'id' => 'f_customfield_' . $key,
					'value' => $key,
					'checked' => 1,
					'text' => '<div><input type="text" readonly class="woobewoo-flat-input js-sortby-item" name="f_option_labels[' . $key . ']" data-name="' . $key . '" placeholder="' . esc_attr($label['label']) . '"/></div>' . $strMove
				);
			}
			HtmlWpf::checkboxlist('f_options', array('options' => $options), '</div><div class="settings-value settings-value-elementor-row-revert js-wpf-row-move">');
			?>
		</div>
	</div>
</div>
