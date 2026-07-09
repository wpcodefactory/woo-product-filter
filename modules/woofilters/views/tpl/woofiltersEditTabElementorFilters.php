<?php
/**
 * Product Filter by WBW - Woofilters Edit Tab Elementor Filters
 *
 * @version 3.2.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

list($categoryDisplay, $parentCategories) = FrameWpf::_()->getModule('woofilters')->getCategoriesDisplay();

list($tagsDisplay) = FrameWpf::_()->getModule('woofilters')->getTagsDisplay();

list($attrDisplay, $attrTypes, $attrNames) = FrameWpf::_()->getModule('woofilters')->getAttributesDisplay();

list($roles) = FrameWpf::_()->getModule('woofilters')->getRolesDisplay();

$formLink = FrameWpf::_()->getModule('options')->getTabUrl( FrameWpf::_()->getModule('woofilters')->getView()->getCode() );
?>

<div class="woobewoo-plugin" id="containerWrapperElementor">
	<form id="wpfFiltersEditForm" data-href="<?php echo esc_attr($formLink); ?>">
		<div class="woobewoo_row">
			<div class="col-md-12">
				<div class="woobewoo-input-group" id="wpfChooseFiltersBlock" data-no-preview="1">
					<div class="woobewoo-group-label">
						<?php echo esc_html__('Filter name:', 'woo-product-filter'); ?>
					</div>
					<?php
					HtmlWpf::text('title', array(
						'value' => '',
					));
					?>
				</div>
			</div>
		</div>
		<div class="wpfMainTabsContainer">
			<div class="woobewoo_row">
				<div class="col-md-12 wpfFiltersTabContents">
					<?php include_once 'woofiltersEditTabFilters.php'; ?>
				</div>
			</div>
		</div>
		<?php
		HtmlWpf::hidden('settings', array(
			'value' => '',
		));
		HtmlWpf::hidden('settings[filters][order]', array(
			'value' => '',
		));
		HtmlWpf::hidden('settings[filters][preselect]', array(
			'value' => ''
		));
		HtmlWpf::hidden('esettings', array(
			'value' => ''
		));
		?>


		<?php HtmlWpf::hidden( 'mod', array( 'value' => 'woofilters' ) ); ?>
		<?php HtmlWpf::hidden( 'action', array( 'value' => 'save' ) ); ?>
		<?php HtmlWpf::hidden( 'id', array( 'value' => '' ) ); ?>
	</form>
	<div class="woobewoo-clear"></div>
</div>
