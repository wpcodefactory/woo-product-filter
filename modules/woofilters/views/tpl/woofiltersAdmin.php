<?php
/**
 * Product Filter by WBW - Woofilters Admin
 *
 * @version 3.1.7
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

?>
<section>
	<div class="woobewoo-item woobewoo-panel woobewoo-d-flex woobewoo-flex-column">
		<div class="topBtnsArea woobewoo-d-flex woobewoo-items-center woobewoo-justify-end">
			<a href="#wpfadd" class="woobewoo-btn woobewoo-btn-primary woobewoo-d-flex woobewoo-items-center">
				<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path d="M15.8333 10.8333H10.8333V15.8333H9.16663V10.8333H4.16663V9.16666H9.16663V4.16666H10.8333V9.16666H15.8333V10.8333Z" fill="currentColor" />
				</svg>
				<span>Add New Filter</span>
			</a>
		</div>
		<div class="woobewoo-d-flex woobewoo-items-center woobewoo-justify-between woobewoo-py-24">
			<div class="woobewoo-relative searchContainer">
				<div class="searchIcon">
					<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
						<path d="M13.1292 11.8792H12.4708L12.2375 11.6542C13.0542 10.7042 13.5458 9.47083 13.5458 8.12916C13.5458 5.13749 11.1208 2.71249 8.12916 2.71249C5.13749 2.71249 2.71249 5.13749 2.71249 8.12916C2.71249 11.1208 5.13749 13.5458 8.12916 13.5458C9.47083 13.5458 10.7042 13.0542 11.6542 12.2375L11.8792 12.4708V13.1292L16.0458 17.2875L17.2875 16.0458L13.1292 11.8792ZM8.12916 11.8792C6.05416 11.8792 4.37916 10.2042 4.37916 8.12916C4.37916 6.05416 6.05416 4.37916 8.12916 4.37916C10.2042 4.37916 11.8792 6.05416 11.8792 8.12916C11.8792 10.2042 10.2042 11.8792 8.12916 11.8792Z" fill="#636977" />
					</svg>
				</div>
				<input id="wpfTableTblSearchTxt"
					class="woobewoo-wdt-100"
					type="text" name="tbl_search" placeholder="<?php echo esc_attr__('Search', 'woo-product-filter'); ?>">
			</div>
			<ul id="wpfTableTblNavBtnsShell" class="woobewoo-bar-controls woobewoo-m-0">
				<li title="<?php echo esc_attr__('Delete selected', 'woo-product-filter'); ?>">
					<button class="button button-small" id="wpfTableRemoveGroupBtn" disabled data-toolbar-button>
						<i class="fa fa-fw fa-trash-o"></i>
						<?php esc_html_e('Delete selected', 'woo-product-filter'); ?>
					</button>
				</li>
				<?php
				if (FrameWpf::_()->isPro()) {
					DispatcherWpf::doAction('addAdminButtonsPro');
				} else {
				?>
				<li title="<?php echo esc_attr(__('Import tables', 'woo-product-filter')); ?>">
					<a class="woobewoo-relative filterExportImportBtn" href="<?php echo esc_url($this->proLink); ?>" target="_blank">
						<span>
							<?php esc_html_e('Import', 'woo-product-filter'); ?>
						</span>
						<svg class="woobewoo-ms-2" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M15 12.5V15H5.00004V12.5H3.33337V15C3.33337 15.9167 4.08337 16.6667 5.00004 16.6667H15C15.9167 16.6667 16.6667 15.9167 16.6667 15V12.5H15ZM14.1667 9.16666L12.9917 7.99166L10.8334 10.1417V3.33333H9.16671V10.1417L7.00837 7.99166L5.83337 9.16666L10 13.3333L14.1667 9.16666Z" fill="currentColor" />
						</svg>
						<span class="pro_label">
							PRO
						</span>
					</a>
				</li>
				<li title="<?php echo esc_attr(__('Export selected', 'woo-product-filter')); ?>">
					<a class="woobewoo-relative filterExportImportBtn export" href="<?php echo esc_url($this->proLink); ?>" target="_blank">
						<span>
							<?php esc_html_e('Export', 'woo-product-filter'); ?>
						</span>
						<svg class="woobewoo-ms-2" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
							<path d="M4.16663 3.33333H15.8333V4.99999H4.16663V3.33333ZM4.16663 11.6667H7.49996V16.6667H12.5V11.6667H15.8333L9.99996 5.83333L4.16663 11.6667ZM10.8333 9.99999V15H9.16663V9.99999H8.19163L9.99996 8.19166L11.8083 9.99999H10.8333Z" fill="currentColor" />
						</svg>
						<span class="pro_label">
							PRO
						</span>
					</a>
				</li>
				<?php } ?>
			</ul>
		</div>
		<div id="containerWrapper">
			<div class="woobewoo-clear"></div>
			<table id="wpfTableTbl" data-columns="<?php echo esc_attr__('ID', 'woo-product-filter') . ';' . esc_attr__('Title', 'woo-product-filter') . ';' . esc_attr__('Shortcode', 'woo-product-filter') . ';' . esc_attr__('Actions', 'woo-product-filter'); ?>">

			</table>
			<div id="wpfTableTblNav"></div>
			<div id="wpfTableTblEmptyMsg" class="woobewoo-hidden">
				<h3>
					<?php echo esc_html__('You have no Filters for now.', 'woo-product-filter') . ' <a href="' . esc_url($this->addNewLink) . '">' . esc_html__('Create', 'woo-product-filter') . '</a> ' . esc_html__('your Filter', 'woo-product-filter') . '!'; ?>
				</h3>
			</div>
		</div>
		<div class="woobewoo-clear"></div>
	</div>
</section>
