<?php
/**
 * Product Filter by WBW - Options Admin Page
 *
 * @version 2.9.7
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

?>

<style type="text/css">
.woobewoo-main {
	display:none;
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
		<section class="woobewoo-content">
			<nav class="woobewoo-navigation woobewoo-sticky <?php DispatcherWpf::doAction('adminMainNavClassAdd'); ?>">
				<ul>
					<?php foreach ($this->tabs as $tabKey => $t) { ?>
						<?php
						if (isset($t['hidden']) && $t['hidden']) {
							continue;
						}
						?>
						<li class="woobewoo-tab-<?php echo esc_attr($tabKey); ?> <?php echo ( ( $this->activeTab == $tabKey || in_array($tabKey, $this->activeParentTabs) ) ? 'active' : '' ); ?>">
							<a href="<?php echo esc_url($t['url']); ?>" title="<?php echo esc_attr($t['label']); ?>"<?php echo empty($t['blank']) ? '' : ' target="_blank"'; ?>>
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
			<div class="woobewoo-container woobewoo-<?php echo esc_attr($this->activeTab); ?>">
				<?php HtmlWpf::echoEscapedHtml($this->breadcrumbs); ?>
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
						<input id="addDialog_title" class="woobewoo-text woobewoo-width-full" type="text"/>
					</div>
					<div class="wpfPopupBlock">
						<label class="wpfPopupLabel"><?php esc_html_e('Select Filters (You can change them later)', 'woo-product-filter'); ?></label>
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
					<input type="hidden" id="addDialog_duplicateid" class="woobewoo-text woobewoo-width-full"/>
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
						<input id="addDialog_titleDuplicate" class="woobewoo-text woobewoo-width-full" type="text"/>
					</div>
					<input type="hidden" id="addDialog_duplicateid" class="woobewoo-text woobewoo-width-full"/>
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
