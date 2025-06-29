<div class="wpfWidgetRow wpfMapRow">
	<div class="wpfWidgetRowCell wpfFirstCell">
		<label for="<?php echo esc_attr($this->widget->get_field_id('id')); ?>"><?php esc_html_e('Select filter', 'woo-product-filter'); ?>:</label>
	</div>
	<div class="wpfWidgetRowCell wpfLastCell">
		<?php 
			HtmlWpf::selectbox($this->widget->get_field_name('id'), array(
				'attrs' => 'id="' . $this->widget->get_field_id('id') . '"',
				'value' => isset($this->data['id']) ? $this->data['id'] : 0,
				'options' => $this->filtersOpts,
			));
			?>
	</div>
</div>
<script type="text/javascript">
	jQuery(document).ready(function(){
		jQuery('.wpfWidgetRowCell select option[value="0"]').prop('disabled',true);
	});
</script>
