<?php
/**
 * Product Filter by WBW - Woofilters Edit Admin
 *
 * @version 3.1.7
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

$isPro = $this->is_pro;
$labelPro = '';
if (!$isPro) {
	$adPath = $this->getModule()->getModPath() . 'img/ad/';
	$labelPro = ' - Pro feature';
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
					<div class="topBtnsArea wpfMainBtnsShell">
						<ul class="wpfSub control-buttons">
							<li>
								<button id="buttonSave" class="button<?php echo $isWCLicense ? ' button-primary' : ''; ?>">
									<i class="fa fa-floppy-o" aria-hidden="true"></i><span><?php echo esc_html__('Save', 'woo-product-filter'); ?></span>
								</button>
							</li>
							<li>
								<button id="buttonDelete" class="button">
									<i class="fa fa-trash-o" aria-hidden="true"></i><span><?php echo esc_html__('Delete', 'woo-product-filter'); ?></span>
								</button>
							</li>
						</ul>
					</div>
					<div class="wpfCopyTextCodeSelectionShell">
						<div class="woobewoo_row">
							<div class="col-md-4 wpfNamePadding woobewoo-d-flex woobewoo-flex-column">
								<label class="woobewoo-inline-block" for="">
									<?php echo esc_html__('Filter name', 'woo-product-filter'); ?>
								</label>
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
							<div class="col-md-4 wpfShortcodeAdm woobewoo-flex-column">
								<label for="" class="woobewoo-d-flex woobewoo-items-center">
									<span>
										<?php echo esc_html__('Filter', 'woo-product-filter'); ?>
									</span>
									<i class="fa fa-question woobewoo-tooltip" title="<?php echo esc_attr(__('Using short code display the filter and products in the desired place of the template.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/how-to-add-woocommerce-product-filter-to-shop/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
								</label>
								<select name="shortcode_example" id="wpfCopyTextCodeExamples" class="woobewoo-flat-input woobewoo-wdt-100 woobewoo-max-width-100-i">
									<option value="shortcode"><?php echo esc_html__('Filter Shortcode', 'woo-product-filter'); ?></option>
									<option value="phpcode"><?php echo esc_html__('Filter PHP code', 'woo-product-filter'); ?></option>
									<option value="shortcode_product"><?php echo esc_html__('Product Shortcode', 'woo-product-filter'); ?></option>
									<option value="phpcode_product"><?php echo esc_html__('Product PHP code', 'woo-product-filter'); ?></option>
								</select>
							</div>
							<?php $fid = isset($this->filter['id']) ? $this->filter['id'] : ''; ?>
							<?php if ($fid) { ?>
								<div class="col-md-4 wpfCopyTextCodeShowBlock wpfShortcode shortcode woobewoo-flex-column">
									<label class="woobewoo-inline-block" for="">
										<?php echo esc_html__('Shortcode', 'woo-product-filter'); ?>
									</label>
									<?php
									HtmlWpf::text('', array(
										'value' => '[' . WPF_SHORTCODE . " id=$fid]",
										'attrs' => 'readonly onclick="this.setSelectionRange(0, this.value.length);" class="woobewoo-flat-input woobewoo-width-full"',
										'required' => true,
									));
									?>
								</div>
								<div class="col-md-4 wpfCopyTextCodeShowBlock wpfShortcode phpcode wpfHidden woobewoo-flex-column">
									<?php
									HtmlWpf::text('', array(
										'value' => "<?php echo do_shortcode('[" . WPF_SHORTCODE . " id=$fid]') ?>",
										'attrs' => 'readonly onclick="this.setSelectionRange(0, this.value.length);" class="woobewoo-flat-input woobewoo-width-full"',
										'required' => true,
									));
									?>
								</div>
								<div class="col-md-4 wpfCopyTextCodeShowBlock wpfShortcode shortcode_product wpfHidden woobewoo-flex-column">
									<?php
									HtmlWpf::text('', array(
										'value' => '[' . WPF_SHORTCODE_PRODUCTS . ']',
										'attrs' => 'readonly onclick="this.setSelectionRange(0, this.value.length);" class="woobewoo-flat-input woobewoo-width-full"',
										'required' => true,
									));
									?>
								</div>
								<div class="col-md-4 wpfCopyTextCodeShowBlock wpfShortcode phpcode_product wpfHidden woobewoo-flex-column">
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
					<div class="">
						<div class="no-md-r-padding woobewoo-wdt-100">
							<div id="tabsContainer" class="woobewoo-d-flex wpfSub tabs-wrapper wpfMainTabs">
								<a href="#row-tab-filters"
									class="current button wpfFilters">
									<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M2.5 14.1667V15.8333H7.5V14.1667H2.5ZM2.5 4.16667V5.83333H10.8333V4.16667H2.5ZM10.8333 17.5V15.8333H17.5V14.1667H10.8333V12.5H9.16667V17.5H10.8333ZM5.83333 7.5V9.16667H2.5V10.8333H5.83333V12.5H7.5V7.5H5.83333ZM17.5 10.8333V9.16667H9.16667V10.8333H17.5ZM12.5 7.5H14.1667V5.83333H17.5V4.16667H14.1667V2.5H12.5V7.5Z" fill="currentColor" />
									</svg>

									<span>
										<?php echo esc_html__('Filters', 'woo-product-filter'); ?>
									</span>
								</a>
								<a href="#row-tab-options" class="button">
									<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
										<g clip-path="url(#clip0_1_1491)">
											<path d="M18.8798 15.8247L11.3132 8.25799C12.0882 6.30799 11.6882 4.00799 10.1132 2.42466C8.19651 0.507994 5.21318 0.332994 3.08818 1.88299L6.28818 5.09133L5.10484 6.26633L1.91318 3.07466C0.363176 5.19133 0.538176 8.18299 2.45484 10.0913C4.00484 11.6413 6.26318 12.0497 8.19651 11.3247L15.7882 18.9163C16.1132 19.2413 16.6382 19.2413 16.9632 18.9163L18.8798 16.9997C19.2132 16.683 19.2132 16.158 18.8798 15.8247ZM16.3798 17.158L8.49651 9.27466C7.98818 9.64966 7.42151 9.87466 6.82984 9.95799C5.69651 10.1247 4.50484 9.78299 3.63818 8.91633C2.84651 8.13299 2.47984 7.08299 2.53818 6.04966L5.11318 8.62466L8.64651 5.09133L6.07151 2.51633C7.10484 2.45799 8.14651 2.82466 8.93818 3.60799C9.83818 4.50799 10.1798 5.74966 9.97151 6.90799C9.87151 7.49966 9.62151 8.04966 9.23818 8.54133L17.1132 16.4163L16.3798 17.158Z" fill="#121315" />
										</g>
										<defs>
											<clipPath id="clip0_1_1491">
												<rect width="20" height="20" fill="white" />
											</clipPath>
										</defs>
									</svg>

									<span>
										<?php echo esc_html__('Behavior', 'woo-product-filter'); ?>
									</span>
								</a>
								<a href="#row-tab-design" class="button">
									<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
										<path d="M9.99984 18.3333C5.40817 18.3333 1.6665 14.5917 1.6665 9.99999C1.6665 5.40832 5.40817 1.66666 9.99984 1.66666C14.5915 1.66666 18.3332 5.03332 18.3332 9.16666C18.3332 11.925 16.0915 14.1667 13.3332 14.1667H11.8582C11.6248 14.1667 11.4415 14.35 11.4415 14.5833C11.4415 14.6833 11.4832 14.775 11.5498 14.8583C11.8915 15.25 12.0832 15.7417 12.0832 16.25C12.0832 17.4 11.1498 18.3333 9.99984 18.3333ZM9.99984 3.33332C6.32484 3.33332 3.33317 6.32499 3.33317 9.99999C3.33317 13.675 6.32484 16.6667 9.99984 16.6667C10.2332 16.6667 10.4165 16.4833 10.4165 16.25C10.4165 16.1167 10.3498 16.0167 10.2998 15.9583C9.95817 15.575 9.77484 15.0833 9.77484 14.5833C9.77484 13.4333 10.7082 12.5 11.8582 12.5H13.3332C15.1748 12.5 16.6665 11.0083 16.6665 9.16666C16.6665 5.94999 13.6748 3.33332 9.99984 3.33332Z" fill="#636977" />
										<path d="M5.4165 10.8333C6.10686 10.8333 6.6665 10.2737 6.6665 9.58332C6.6665 8.89297 6.10686 8.33332 5.4165 8.33332C4.72615 8.33332 4.1665 8.89297 4.1665 9.58332C4.1665 10.2737 4.72615 10.8333 5.4165 10.8333Z" fill="#636977" />
										<path d="M7.9165 7.49999C8.60686 7.49999 9.1665 6.94035 9.1665 6.24999C9.1665 5.55963 8.60686 4.99999 7.9165 4.99999C7.22615 4.99999 6.6665 5.55963 6.6665 6.24999C6.6665 6.94035 7.22615 7.49999 7.9165 7.49999Z" fill="#636977" />
										<path d="M12.0832 7.49999C12.7735 7.49999 13.3332 6.94035 13.3332 6.24999C13.3332 5.55963 12.7735 4.99999 12.0832 4.99999C11.3928 4.99999 10.8332 5.55963 10.8332 6.24999C10.8332 6.94035 11.3928 7.49999 12.0832 7.49999Z" fill="#636977" />
										<path d="M14.5832 10.8333C15.2735 10.8333 15.8332 10.2737 15.8332 9.58332C15.8332 8.89297 15.2735 8.33332 14.5832 8.33332C13.8928 8.33332 13.3332 8.89297 13.3332 9.58332C13.3332 10.2737 13.8928 10.8333 14.5832 10.8333Z" fill="#636977" />
									</svg>

									<span>
										<?php echo esc_html__('Appearance', 'woo-product-filter'); ?>
									</span>
								</a>
							</div>
							<span id="wpfFilterTitleEditMsg"></span>
						</div>
						<!-- <div class="col-md-3 no-l-padding hidden-sm hidden-xs">
							<div class="wpfPreviewTitle"><?php echo esc_html__('Preview', 'woo-product-filter'); ?></div>
						</div> -->
					</div>
					<div class="wpfMainTabsContainer woobewooFiltersParentContainer woobewoo-d-flex selectFiltersMain woobewoo-p-24">
						<div class="woobewooFiltersMainLeftCol wpfFiltersTabContents woobewoo-p-0-i">
							<?php //All templates in the same folder now. This is simplest way to include all. ?>
							<?php include_once 'woofiltersEditTabFilters.php'; ?>
							<?php include_once 'woofiltersEditTabOptions.php'; ?>
							<?php include_once 'woofiltersEditTabDesign.php'; ?>
						</div>
						<div class="col-md-3 woobewooFiltersMainRightCol">
							<div class="hidden-lg hidden-md">
								<div class="wpfPreviewTitle"><?php echo esc_html__('Preview', 'woo-product-filter'); ?></div>
							</div>
							<div class="wpfFiltersBlockPreview"></div>
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
