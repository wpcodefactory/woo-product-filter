<?php
/**
 * Product Filter by WBW - Options Admin Page
 *
 * @version 3.1.4
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

?>

<style type="text/css">
	.woobewoo-main {
		display: none;
	}

	.woobewoo-plugin-loader {
		width: 100%;
		height: 100px;
		text-align: center;
	}

	.woobewoo-plugin-loader div {
		font-size: 30px;
		position: relation;
		margin-top: 40px;
	}
</style>
<div class="wrap woobewoo-wrap">
	<div class="woobewoo-plugin woobewoo-main">
		<section id="mainContainer" class="woobewoo-content d-flex items-stretch">
			<nav class="woobewoo-navigation woobewoo-sticky <?php DispatcherWpf::doAction('adminMainNavClassAdd'); ?>">
				<a href="javascript:void(0);" class="woobewoo_logo d-inline-block">
					<svg width="48" height="35" viewBox="0 0 48 35" fill="none" xmlns="http://www.w3.org/2000/svg">
						<g clip-path="url(#clip0_1_769)">
							<path d="M37.9799 1.02244C38.7399 -0.334244 40.6897 -0.342452 41.4613 1.00779L47.7074 11.9385H46.3314C45.6334 11.9385 44.9873 12.2973 44.6293 12.8965C41.123 18.7635 35.6081 28.8301 32.8676 33.8721C32.1291 35.2304 30.1966 35.2725 29.3939 33.9512L22.7982 23.0888C20.5178 27.1784 18.3089 31.2203 16.8676 33.8721C16.1291 35.2304 14.1966 35.2725 13.3939 33.9512L6.81872 22.9385L0.279662 11.9043C-0.0759926 11.3041 -0.09367 10.5613 0.233763 9.94529L4.47888 1.95994C5.23893 0.603345 7.18878 0.595078 7.96033 1.94529L13.2074 10.5C13.565 11.153 13.5305 11.9508 13.1176 12.5703L8.04333 20.9336C7.82433 21.2621 7.3799 22.2097 7.37988 22.6045V23.5H8.20703C8.93094 23.5 9.92596 22.5466 10.2797 21.915L21.9799 1.02244C22.7399 -0.334243 24.6897 -0.342451 25.4613 1.00779L31.8781 11.9181L30.3314 11.9385C29.6334 11.9385 28.9873 12.2973 28.6293 12.8965C26.9899 15.6397 24.9129 19.3019 22.8842 22.9385H24.5345C25.2585 22.9385 25.926 22.5466 26.2797 21.915L37.9799 1.02244Z" fill="black" />
						</g>
						<defs>
							<clipPath id="clip0_1_769">
								<rect width="48" height="35" fill="white" />
							</clipPath>
						</defs>
					</svg>
				</a>
				<ul>
					<?php foreach ($this->tabs as $tabKey => $t) { ?>
						<?php
						if (isset($t['hidden']) && $t['hidden']) {
							continue;
						}
						?>
						<li class="woobewoo-tab-<?php echo esc_attr($tabKey); ?> <?php echo (($this->activeTab == $tabKey || in_array($tabKey, $this->activeParentTabs)) ? 'active' : ''); ?>">
							<a class="menuItem d-inline-block w-100" href="<?php echo esc_url($t['url']); ?>" title="<?php echo esc_attr($t['label']); ?>" <?php echo empty($t['blank']) ? '' : ' target="_blank"'; ?>>
								<?php if (isset($t['fa_icon'])) { ?>
									<i class="fa <?php echo esc_attr($t['fa_icon']); ?>"></i>
								<?php } elseif (isset($t['wp_icon'])) { ?>
									<i class="dashicons-before <?php echo esc_attr($t['wp_icon']); ?>"></i>
								<?php } elseif (isset($t['icon'])) { ?>
									<i class="<?php echo esc_attr($t['icon']); ?>"></i>
								<?php } ?>
								<span class="sup-tab-label"><?php echo esc_html($t['label']); ?></span>
							</a>
						</li>
					<?php } ?>
				</ul>
			</nav>
			<div class="contantArea woobewoo-container w-100 relative woobewoo-<?php echo esc_attr($this->activeTab); ?>">
				<div class="breadcrumb_heading">
					<?php HtmlWpf::echoEscapedHtml($this->breadcrumbs); ?>
				</div>
				<?php HtmlWpf::echoEscapedHtml($this->content); ?>
				<div class="clear"></div>
			</div>
		</section>
		<?php
		$filtersList = FrameWpf::_()->getModule('woofilters')->getModel()->getAllFilters();
		?>
		<div id="wpfAddDialog" class="woobewoo-plugin woobewoo-hidden" title="<?php echo esc_attr__('Add new product filter', 'woo-product-filter'); ?>" data-button="<?php echo esc_attr__('Create', 'woo-product-filter'); ?>">
			<div>
				<form id="tableForm">
					<div class="wpfPopupBlock">
						<label class="wpfPopupLabel"><?php esc_html_e('Filter name', 'woo-product-filter'); ?></label>
						<input id="addDialog_title" class="woobewoo-text woobewoo-width-full" type="text" placeholder="e.g. Shop Sidebar Filter" />
					</div>
					<div class="wpfPopupBlock">
						<label class="wpfPopupLabel"><?php esc_html_e('Choose filter types ', 'woo-product-filter'); ?>
							<span><?php esc_html_e('(You can change them later)', 'woo-product-filter'); ?></span>
						</label>
						<ul class="wpfPopupList" id="addDialog_list">
							<?php
							foreach ($filtersList as $filter => $data) {
								if ('wpfPriceRange' != $filter) {
									echo '<li><input type="checkbox" data-unique-id="' . esc_attr(uniqid('wpf_')) .
										'" data-value="' . esc_attr($filter) .
										'"><label>' . esc_html($data['name']) .
										'</label></li>';
								}
							}
							?>
						</ul>
					</div>
					<input type="hidden" id="addDialog_duplicateid" class="woobewoo-text woobewoo-width-full" />
				</form>
				<div id="formError" class="woobewoo-hidden">
					<p></p>
				</div>
			</div>
		</div>
		<div id="wpfDuplicateDialog" class="woobewoo-plugin woobewoo-hidden" title="<?php echo esc_attr__('Duplicate product filter', 'woo-product-filter'); ?>" data-button="<?php echo esc_attr__('Duplicate', 'woo-product-filter'); ?>">
			<div>
				<form id="tableForm">
					<div class="wpfPopupBlock">
						<label class="wpfPopupLabel"><?php esc_html_e('Filter name', 'woo-product-filter'); ?></label>
						<input id="addDialog_titleDuplicate" class="woobewoo-text woobewoo-width-full" type="text" />
					</div>
					<input type="hidden" id="addDialog_duplicateid" class="woobewoo-text woobewoo-width-full" />
				</form>
				<div id="formError" class="woobewoo-hidden">
					<p></p>
				</div>
			</div>
		</div>
	</div>
	<div class="woobewoo-plugin-loader">
		<div>Loading...<i class="fa fa-spinner fa-spin"></i></div>
	</div>
</div>
<?php DispatcherWpf::doAction('afterWoobewooWrap');
