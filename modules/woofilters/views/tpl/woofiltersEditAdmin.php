<?php
$isPro = $this->is_pro;
$labelPro = '';
if (!$isPro) {
	$adPath = $this->getModule()->getModPath() . 'img/ad/';
	$labelPro = ' Pro';
}
$isWCLicense = FrameWpf::_()->isWCLicense();

list($categoryDisplay, $parentCategories) = $this->getModule()->getCategoriesDisplay();

list($tagsDisplay) = $this->getModule()->getTagsDisplay();

$settings = $this->getFilterSetting($this->settings, 'settings', array());

list($attrDisplay, $attrTypes, $attrNames) = $this->getModule()->getAttributesDisplay();

list($roles) = $this->getModule()->getRolesDisplay();

$wpfBrand = array(
	'exist' => taxonomy_exists('product_brand')
);

$catArgs = array(
	'orderby' => 'name',
	'order' => 'asc',
	'hide_empty' => false,
);
$brandDisplay = array();
$parentBrands = array();
if (taxonomy_exists('pwb-brand')) {
	list($brandDisplay, $parentBrands) = $this->getModule()->getCategoriesDisplay('pwb-brand');
}

?>

<div id="wpfFiltersEditTabs">
	<section>
		<div class="woobewoo-item woobewoo-panel">
			<div id="containerWrapper">
				<form id="wpfFiltersEditForm" data-table-id="<?php echo esc_attr($this->filter['id']); ?>" data-href="<?php echo esc_attr($this->link); ?>">

					<div class="row">
						<div class="wpfCopyTextCodeSelectionShell col-lg-8 col-md-8 col-sm-8 col-xs-12">
							<div class="row">
								<div class="col-lg-3 col-md-4 col-sm-5 col-xs-6 wpfNamePadding">
									<span id="wpfFilterTitleWrapLabel"><?php echo esc_html__('Filter name:', 'woo-product-filter'); ?></span>
									<span id="wpfFilterTitleShell" title="<?php echo esc_attr__('Click to edit', 'woo-product-filter'); ?>">
										<?php $filterTitle = isset($this->filter['title']) ? $this->filter['title'] : 'empty'; ?>
										<span id="wpfFilterTitleLabel"><?php echo esc_html($filterTitle); ?></span>
										<?php
											HtmlWpf::text('title', array(
												'value' => $filterTitle,
												'attrs' => 'class="wpfHidden" id="wpfFilterTitleTxt"',
												'required' => true,
											));
											?>
										<i class="fa fa-fw fa-pencil"></i>
									</span>
								</div>
								<div class="col-lg-3 col-md-3 col-sm-6 col-xs-6 wpfShortcodeAdm">
									<select name="shortcode_example" id="wpfCopyTextCodeExamples" class="woobewoo-flat-input">
										<option value="shortcode"><?php echo esc_html__('Filter Shortcode', 'woo-product-filter'); ?></option>
										<option value="phpcode"><?php echo esc_html__('Filter PHP code', 'woo-product-filter'); ?></option>
										<option value="shortcode_product"><?php echo esc_html__('Product Shortcode', 'woo-product-filter'); ?></option>
										<option value="phpcode_product"><?php echo esc_html__('Product PHP code', 'woo-product-filter'); ?></option>
									</select>
									<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Using short code display the filter and products in the desired place of the template.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/how-to-add-woocommerce-product-filter-to-shop/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
								</div>
								<?php $fid = isset($this->filter['id']) ? $this->filter['id'] : ''; ?>
								<?php if ($fid) { ?>
								<div class="col-lg-3 col-md-5 col-sm-6 col-xs-6 wpfCopyTextCodeShowBlock wpfShortcode shortcode">
									<?php
										HtmlWpf::text('', array(
											'value' => '[' . WPF_SHORTCODE . " id=$fid]",
											'attrs' => 'readonly onclick="this.setSelectionRange(0, this.value.length);" class="woobewoo-flat-input woobewoo-width-full"',
											'required' => true,
										));
									?>
								</div>
								<div class="col-lg-4 col-md-5 col-sm-6 col-xs-6 wpfCopyTextCodeShowBlock wpfShortcode phpcode wpfHidden">
									<?php
										HtmlWpf::text('', array(
											'value' => "<?php echo do_shortcode('[" . WPF_SHORTCODE . " id=$fid]') ?>",
											'attrs' => 'readonly onclick="this.setSelectionRange(0, this.value.length);" class="woobewoo-flat-input woobewoo-width-full"',
											'required' => true,
										));
									?>
								</div>
								<div class="col-lg-3 col-md-5 col-sm-6 col-xs-6 wpfCopyTextCodeShowBlock wpfShortcode shortcode_product wpfHidden">
									<?php
										HtmlWpf::text('', array(
											'value' => '[' . WPF_SHORTCODE_PRODUCTS . ']',
											'attrs' => 'readonly onclick="this.setSelectionRange(0, this.value.length);" class="woobewoo-flat-input woobewoo-width-full"',
											'required' => true,
										));
									?>
								</div>
								<div class="col-lg-4 col-md-5 col-sm-6 col-xs-6 wpfCopyTextCodeShowBlock wpfShortcode phpcode_product wpfHidden">
									<?php
										HtmlWpf::text('', array(
											'value' => "<?php echo do_shortcode('[" . WPF_SHORTCODE_PRODUCTS . "]') ?>",
											'attrs' => 'readonly onclick="this.setSelectionRange(0, this.value.length);" class="woobewoo-flat-input woobewoo-width-full"',
											'required' => true,
										));
									?>
								</div>
								<?php } ?>
								<div class="clear"></div>
							</div>
						</div>
						<div class="wpfMainBtnsShell col-lg-4 col-md-4 col-sm-4 col-xs-12">
							<ul class="wpfSub control-buttons">
								<li>
									<button id="buttonSave" class="button<?php echo $isWCLicense ? ' button-primary' : ''; ?>">
										<i class="fa fa-floppy-o" aria-hidden="true"></i><span><?php echo esc_html__('Save', 'woo-product-filter'); ?></span>
									</button>
								</li>
								<li>
									<button id="buttonDelete" class="button" >
										<i class="fa fa-trash-o" aria-hidden="true"></i><span><?php echo esc_html__('Delete', 'woo-product-filter'); ?></span>
									</button>
								</li>
							</ul>
						</div>
					</div>

					<div class="row">
						<div class="col-md-9 no-md-r-padding">
							<ul class="wpfSub tabs-wrapper wpfMainTabs">
								<li>
									<a href="#row-tab-filters" class="current button wpfFilters"><i class="fa fa-fw fa-eye"></i><?php echo esc_html__('Filters', 'woo-product-filter'); ?></a>
								</li>
								<li>
									<a href="#row-tab-options" class="button"><i class="fa fa-fw fa-wrench"></i><?php echo esc_html__('Options', 'woo-product-filter'); ?></a>
								</li>
								<li>
									<a href="#row-tab-design" class="button"><i class="fa fa-fw fa-picture-o"></i><?php echo esc_html__('Design', 'woo-product-filter'); ?></a>
								</li>
							</ul>
							<span id="wpfFilterTitleEditMsg"></span>
						</div>
						<div class="col-md-3 no-l-padding hidden-sm hidden-xs">
							<div class="wpfPreviewTitle"><?php echo esc_html__('Preview', 'woo-product-filter'); ?></div>
						</div>
					</div>
					<div class="col-lg-12 col-md-12 wpfMainTabsContainer">
						<div class="row">
							<div class="col-md-9 wpfFiltersTabContents">
								<?php //All templates in the same folder now. This is simplest way to include all. ?>
								<?php include_once 'woofiltersEditTabFilters.php'; ?>
								<?php include_once 'woofiltersEditTabOptions.php'; ?>
								<?php include_once 'woofiltersEditTabDesign.php'; ?>
							</div>
							<div class="col-md-3">
								<div class="hidden-lg hidden-md">
									<div class="wpfPreviewTitle"><?php echo esc_html__('Preview', 'woo-product-filter'); ?></div>
								</div>
								<div class="wpfFiltersBlockPreview"></div>
							</div>
						</div>
					</div>

					<?php
					if (isset($this->settings['settings']['filters']['order'])) {
						$orderTab = $this->resolveDepricatedOptionality($this->settings['settings']['filters']['order']);
					} else {
						$orderTab = '';
					}

					HtmlWpf::hidden('settings[filters][order]', array(
						'value' => $orderTab,
					));
					HtmlWpf::hidden('settings[filters][preselect]', array(
						'value' => ( isset($this->settings['settings']['filters']['preselect']) ? htmlentities($this->settings['settings']['filters']['preselect'], ENT_COMPAT) : '' ),
					));
					?>

					<?php HtmlWpf::hidden( 'mod', array( 'value' => 'woofilters' ) ); ?>
					<?php HtmlWpf::hidden( 'action', array( 'value' => 'save' ) ); ?>
					<?php HtmlWpf::hidden( 'id', array( 'value' => $this->filter['id'] ) ); ?>
				</form>
				<div class="woobewoo-clear"></div>
			</div>
		</div>
	</section>
</div>
