<?php
	$filtersList = $this->getModel()->getAllFilters();
?>
<div class="row row-tab active" id="row-tab-filters">
	<div class="col-xs-12 row-settings-block">
		<div class="woobewoo-input-group" id="wpfChooseFiltersBlock" data-no-preview="1">
			<div class="woobewoo-group-label">
				<?php esc_html_e('Select filters to add', 'woo-product-filter'); ?>
			</div>
			<select id="wpfChooseFilters">
				<?php
				foreach ($filtersList as $filter => $data) {
					echo '<option value="' . esc_attr($filter) .
							'" data-enabled="' . esc_attr((int) $data['enabled']) .
							'" data-unique-id="' . esc_attr(uniqid('wpf_')) .
							'" data-unique="' . esc_attr((int) $data['unique']) .
							'" data-filtername="' . esc_attr($this->getFilterSetting($data, 'filtername', '')) .
							'" data-group="' . esc_attr($this->getFilterSetting($data, 'group', '')) .
							'">' .
								esc_html($data['name']) .
						'</option>';
				}
				?>
			</select>
			<button id="wpfAddFilterButton" data-option='add' class="button button-small">
				<span><?php esc_html_e('Add', 'woo-product-filter'); ?></span>
			</button>
			<span data-option='pro' class="wpfProLabel wpfHidden"><a href="<?php echo esc_url($this->proLink); ?>" target="_blank"><?php esc_html_e('PRO Option', 'woo-product-filter'); ?></a></span>
			<span data-option='uniq' class="wpfProLabel wpfHidden"><?php esc_html_e('Already in the list', 'woo-product-filter'); ?></span>
			<span data-option='group' class="wpfProLabel wpfHidden">
			<?php 
			echo esc_html__('Оnly one of', 'woo-product-filter') . ' <span class="light">' . esc_html($filtersList['wpfPrice']['name']) .
				'</span>/<span class="light">' . esc_html($filtersList['wpfPriceRange']['name']) . '</span> ' .	esc_html__('is available', 'woo-product-filter');
			?>
			</span>
		</div>
	</div>

	<div class="col-xs-12 row-settings-block">
		<div class="wpfFiltersBlock">

		</div>
	</div>

	<div class="wpfTemplates wpfHidden">
		<div class="wpfAttributesTerms">
			<?php
			echo '<input type="hidden" name="attr_types" value="' . esc_attr(UtilsWpf::jsonEncode($attrTypes)) . '">';
			echo '<input type="hidden" name="attr_filternames" value="' . esc_attr(UtilsWpf::jsonEncode($attrNames)) . '">';

			if (isset($this->settings['settings']['filters']['order'])) {
				$filtersOrder = UtilsWpf::jsonDecode($this->settings['settings']['filters']['order']);
				$module = $this->getModule();
				$slugs = array();
				foreach ($filtersOrder as $filter) {
					if ('wpfAttribute' == $filter['id'] && !empty($filter['settings']['f_list'])) {
						$slug = $filter['settings']['f_list'];
						if (!in_array($slug, $slugs)) {
							$slugs[] = $slug;
							$terms = $module->getAttributeTerms($slug);
							$keys = array_keys($terms);

							echo '<input type="hidden" name="attr-' . esc_attr($slug) . '" data-order="' . esc_attr(UtilsWpf::jsonEncode($keys)) . '" value="' .
								esc_attr(UtilsWpf::jsonEncode($terms)) . '">';
						}
					}
				}
			}
			?>
		</div>
		<div class="wpfRangeByHandTemplate">
			<div class="wpfRangeByHand">
				<div class="wpfRangeByHandHandlerFrom">
					<?php esc_html_e('From', 'woo-product-filter'); ?>
					<input type="text" name="from" value="">
				</div>
				<div class="wpfRangeByHandHandlerTo">
					<?php esc_html_e('To', 'woo-product-filter'); ?>
					<input type="text" name="to" value="">
				</div>
				<div class="wpfRangeByHandHandler">
					<i class="fa fa-arrows-v"></i>
				</div>
				<div class="wpfRangeByHandRemove">
					<i class="fa fa-trash-o"></i>
				</div>
			</div>
		</div>

		<div class="wpfRangeByHandTemplateAddButton">
			<div class="wpfRangeByHandAddButton">
				<button class="button wpfAddPriceRange"><?php esc_html_e('Add', 'woo-product-filter'); ?></button>
			</div>
		</br>
			<div>
				<?php 
					esc_html_e('Do not leave empty fields. Enter `i` if you want the value to be calculated automatically (for From field this will be the minimum price, for field To - the maximum price', 'woo-product-filter'); 
				?>
			</div>
		</div>

		<div class="wpfFilter wpfFiltersBlockTemplate">
			<div class="wpfHeaders">
				<?php HtmlWpf::checkbox('f_enable', array('checked' => 1, 'attrs' => 'class="wpfHidden"')); ?>
				<div class="wpfFilterTitle"></div>
				<a href="#" class="wpfDelete"><i class="fa fa-fw fa-times"></i></a>
				<a href="#" class="wpfToggle"><i class="fa fa-chevron-down"></i></a>
				<div class="wpfFilterFrontDescOpt">
					<?php 
						HtmlWpf::text('f_description', array(
							'placeholder' => esc_attr__('Description', 'woo-product-filter'),
						));
						?>
				</div>
				<div class="wpfFilterFrontTitleOpt">
					<?php
						HtmlWpf::text('f_title', array(
							'placeholder' => esc_attr__('Title', 'woo-product-filter'),
						));
						?>
				</div>
			</div>
			<div class="wpfOptions wpfHidden"></div>
		</div>
		<div class="wpfOptionsTemplate wpfHidden">
			<?php 
			foreach ($filtersList as $filter => $data) { 
				if (!$data['enabled']) {
					continue;
				}
				?>
			<div class="wpfFilterOptions" data-filter="<?php echo esc_attr($filter); ?>">
				<?php 
				HtmlWpf::hidden('f_name', array('value' => $data['name']));
				include_once 'woofiltersEditTabFilters' . substr($filter, 3) . '.php';
				?>
			</div>
			<?php } ?>
		</div>
	</div>
</div>
