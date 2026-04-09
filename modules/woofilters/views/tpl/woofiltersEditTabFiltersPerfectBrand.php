<?php
	ViewWpf::display('woofiltersEditTabCommonTitle');
?>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Show on frontend as', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Depending on whether you need one or several brands to be available at the same time, show your brands list as checkbox or dropdown.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('f_frontend_type', array(
					'options' => array(
						'list' => esc_attr__( 'Radiobuttons list (single select)', 'woo-product-filter' ),
						'dropdown' => esc_attr__( 'Dropdown (single select)', 'woo-product-filter' ),
						'mul_dropdown' => esc_attr__( 'Multiple Dropdown', 'woo-product-filter' ),
						'multi' => esc_attr__( 'Checkbox list (multiple select)', 'woo-product-filter' ) . $labelPro,
						'buttons' => esc_attr__( 'Buttons', 'woo-product-filter' ) . $labelPro,
						'text' => esc_attr__( 'Text', 'woo-product-filter' ) . $labelPro
					),
					'attrs' => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
	</div>
</div>
<?php
if ($isPro) {
	DispatcherWpf::doAction( 'addEditTabFilters', 'partEditTabFiltersMultiSelect');
}
?>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3 wpfTypeSwitchable" data-not-type="buttons">
		<?php esc_html_e('Show hierarchical', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Show paternal and subsidiary categories (for checkbox list). If you display only some categories, make sure that the parent categories are selected.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100 wpfTypeSwitchable" data-not-type="buttons">
			<?php HtmlWpf::checkboxToggle('f_show_hierarchical', array()); ?>
		</div>
		<div class="settings-value settings-w100 wpfTypeSwitchable" data-not-type="buttons" data-parent-switch="f_show_hierarchical">
			<div class="settings-value-label">
				<?php esc_html_e('Hide categories parent', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Show only categories children.', 'woo-product-filter'); ?>"></i>
			</div>
			<?php HtmlWpf::checkboxToggle('f_hide_parent', array()); ?>
		</div>
		<?php
		if ($isPro) {
			DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersHierarchicalOption');
		}
		?>
	</div>
</div>
<?php
if ($isPro) {
	DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersButtonsType');
	DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersCategoryMulti');
}
?>
<div class="row-settings-block wpfTypeSwitchable" data-type="dropdown">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Dropdown label', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Dropdown first option text.', 'woo-product-filter'); ?>"></i>
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
		<?php esc_html_e('Sort by', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Set brands sorting by ascendance or descendance.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('f_sort_by', array(
					'options' => array(
						'asc' => esc_attr__( 'ASC', 'woo-product-filter' ),
						'desc' => esc_attr__( 'DESC', 'woo-product-filter' ),
						'default' => esc_attr__( 'Default', 'woo-product-filter' ) . $labelPro
					),
					'attrs' => 'class="woobewoo-flat-input' . ( $isPro ? '' : ' wpfWithProAd' ) . '"'
				));
				?>
		</div>
		<?php
		if ($isPro) {
			DispatcherWpf::doAction('addEditTabFilters', 'partEditTabFiltersSortAsNumbers');
		} else {
			?>
		<div class="row-settings-block" data-parent="f_sort_by" data-no-values="default">
			<div class="settings-block-label col-xs-8 col-sm-6" >
				<?php esc_html_e('Sort as numbers', 'woo-product-filter'); ?>
				<span class="wpfProLabel"><a href="<?php echo esc_url($this->proLink); ?>" target="_blank"><?php esc_html_e('PRO Option', 'woo-product-filter'); ?></a></span>
			</div>
		</div>
		<?php } ?>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Order by custom', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Categories are displayed according to the order of their selection in the input fields.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_order_custom', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfHidden wpfTypeSwitchable" data-not-type="buttons" data-parent="f_show_hierarchical">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Hide brands parent', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Show only brands children.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_hide_parent', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Show count', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Show count display the number of products that have the appropriate parameter.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_show_count', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Hide brands without products', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Hide brands without products', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_hide_empty', array()); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_hide_empty">
			<div class="settings-value-label">
				<?php esc_html_e('Same behavior for active filter', 'woo-product-filter'); ?>
				<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('By default if a value is selected in the filter, items without products will still be displayed (this was done on purpose so that you can quickly switch between items).If the option is enabled, empty elements will be hidden regardless of the filter activity (this also applies to loading via ajax and when reloading the page with the parameters specified in the url).', 'woo-product-filter')); ?>"></i>
			</div>
			<?php HtmlWpf::checkboxToggle('f_hide_empty_active', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Product brands', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Select product brands to be displayed on your site from the list. If you want to select several brands, hold the "Shift" button and click on brand names. Or you can hold "Ctrl" and click on brand names. Press "Ctrl" + "a" for checking all brands.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100 woobewoo-width-full">
			<?php 
				HtmlWpf::selectlist('f_mlist', array(
					'options' => $brandDisplay,
					'data-parents' => json_encode($parentBrands, JSON_HEX_QUOT | JSON_HEX_TAG)
				));
				?>
		</div>
		<div class="settings-value settings-w100">
			<div class="settings-value-label">
				<?php esc_html_e( 'Include children', 'woo-product-filter' ); ?>
			</div>
			<?php HtmlWpf::checkboxToggle( 'f_mlist_with_children', array() ); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Make selected brands as default', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Selected brands will be marked as default and hidden on frontend.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_hidden_brands', array('attrs' => 'data-preselect-flag="1"')); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Clear filter only to selected brands', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('When the filter is clear, he will be filtered only by selected items. Be careful when using two or more brand filters!', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_filtered_by_selected', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Exclude terms ids', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Exclude brand terms from filter by ids. Example input: 1,2,3.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::text('f_exclude_terms', array('attrs' => 'class="woobewoo-flat-input"')); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="list">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Show search', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Show search display the bar for searching by brand name in the filter.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-values-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_show_search_input', array()); ?>
		</div>
		<div class="settings-value settings-w100" data-parent="f_show_search_input">
			<?php
			$labels = $this->getModel('woofilters')->getFilterLabels('Category');
			HtmlWpf::text('f_search_label', array('placeholder' => esc_html($labels['search']), 'attrs' => 'class="woobewoo-flat-input"'));
			?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Hide child', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Hide child taxonomy', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_hide_taxonomy', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label col-xs-4 col-sm-3">
		<?php esc_html_e('Always display all brands', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('If checked, the entire list of brands will always be visible, otherwise only available for filtered items.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::checkboxToggle('f_show_all_brands', array()); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-not-type="dropdown">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Layout', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Select a vertical or horizontal layout and set the count of columns.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w50">
			<?php 
				HtmlWpf::selectbox('f_layout', array(
					'options' => array('ver' => esc_attr__('Vertical', 'woo-product-filter'), 'hor' => esc_attr__('Horizontal', 'woo-product-filter')),
					'attrs' => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
		<div class="settings-value settings-w50" data-select="f_layout" data-select-value="ver">
			<div class="settings-value-label">
				<?php esc_html_e('Columns', 'woo-product-filter'); ?>
			</div>
			<?php HtmlWpf::text('f_ver_columns', array('value' => 1, 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width40"')); ?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-not-type="dropdown">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Maximum height in frontend', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__('Set maximum displayed height in frontend.', 'woo-product-filter'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php HtmlWpf::text('f_max_height', array('value'=>'200', 'attrs' => 'class="woobewoo-flat-input woobewoo-number woobewoo-width60"')); ?> px
		</div>
	</div>
</div>
<?php
if ( $isPro ) {
	DispatcherWpf::doAction( 'addEditTabFilters', 'partEditTabFiltersPerfectBrandDescription' );
} else {
	?>
	<div class="row-settings-block">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Display brand description', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip no-tooltip"
			   title="<?php echo esc_attr__( 'Display brand description before product list', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values col-xs-8 col-sm-9">
			<div class="settings-value">
				<span class="wpfProLabel"><a href="<?php echo esc_url($this->proLink); ?>" target="_blank"><?php esc_html_e('PRO Option', 'woo-product-filter'); ?></a></span>
			</div>
		</div>
	</div>
	<?php
}
