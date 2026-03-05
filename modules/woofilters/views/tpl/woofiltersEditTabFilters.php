<?php
$filtersList = $this->getModel()->getAllFilters();
?>
<div class="row row-tab active" id="row-tab-filters">
	<div class="col-xs-12 row-settings-block">
		<div class="woobewoo-input-group" id="wpfChooseFiltersBlock" data-no-preview="1">
			<div class="woobewoo-group-label">
				<?php esc_html_e('Select filters to add', 'woo-product-filter'); ?>
			</div>
			<div class="d-flex w-100">
				<select id="wpfChooseFilters" data-added-text="<?php esc_html_e('Added to filter', 'woo-product-filter'); ?>">
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

					<svg class="ms-2" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M7.33341 13.25L3.83341 9.74999L2.66675 10.9167L7.33341 15.5833L17.3334 5.58332L16.1667 4.41666L7.33341 13.25Z" fill="white" />
					</svg>

				</button>
			</div>
			<? //php require WPF_COMMON . 'pro-label.php'; 
			?>
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
			<div>
				<?php
				esc_html_e('Do not leave empty fields. Enter `i` if you want the value to be calculated automatically (for From field this will be the minimum price, for field To - the maximum price', 'woo-product-filter');
				?>
			</div>
		</div>

		<div class="wpfFilter wpfFiltersBlockTemplate">
			<div class="wpfHeaders customHsdjjfs">
				<div class="me-3">
					<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M11 18C11 19.1 10.1 20 9 20C7.9 20 7 19.1 7 18C7 16.9 7.9 16 9 16C10.1 16 11 16.9 11 18ZM9 10C7.9 10 7 10.9 7 12C7 13.1 7.9 14 9 14C10.1 14 11 13.1 11 12C11 10.9 10.1 10 9 10ZM9 4C7.9 4 7 4.9 7 6C7 7.1 7.9 8 9 8C10.1 8 11 7.1 11 6C11 4.9 10.1 4 9 4ZM15 8C16.1 8 17 7.1 17 6C17 4.9 16.1 4 15 4C13.9 4 13 4.9 13 6C13 7.1 13.9 8 15 8ZM15 10C13.9 10 13 10.9 13 12C13 13.1 13.9 14 15 14C16.1 14 17 13.1 17 12C17 10.9 16.1 10 15 10ZM15 16C13.9 16 13 16.9 13 18C13 19.1 13.9 20 15 20C16.1 20 17 19.1 17 18C17 16.9 16.1 16 15 16Z" fill="#525763" />
					</svg>
				</div>

				<?php HtmlWpf::checkbox('f_enable', array('checked' => 1, 'attrs' => 'class="wpfHidden"')); ?>
				<div class="wpfFilterTitle"></div>
				<div class="ms-auto d-flex items-center">
					<div class="wpfFilterFrontTitleOpt">
						<?php
						HtmlWpf::text('f_title', array(
							'placeholder' => esc_attr__('Title', 'woo-product-filter'),
						));
						?>
					</div>
					<div class="wpfFilterFrontDescOpt">
						<?php
						HtmlWpf::text('f_description', array(
							'placeholder' => esc_attr__('Description', 'woo-product-filter'),
						));
						?>
					</div>
					<div class="d-flex items-center">
						<a href="#" class="wpfToggle">
							<i class="fa fa-chevron-down"></i>
						</a>
						<a href="#" class="wpfDelete">
							<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M16 9V19H8V9H16ZM14.5 3H9.5L8.5 4H5V6H19V4H15.5L14.5 3ZM18 7H6V19C6 20.1 6.9 21 8 21H16C17.1 21 18 20.1 18 19V7Z" fill="#B12224" />
							</svg>
						</a>
					</div>
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