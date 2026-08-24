<?php
/**
 * Product Filter by WBW - Woofilters Edit Tab Elementor Filters
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

$labelPro = apply_filters( 'woobewoo_pf_pro_label', ' - Pro feature' );

list($categoryDisplay, $parentCategories) = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getCategoriesDisplay();

list($tagsDisplay) = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getTagsDisplay();

list($attrDisplay, $attrTypes, $attrNames) = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getAttributesDisplay();

list($roles) = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getRolesDisplay();

$wpfBrand = array(
	'exist' => taxonomy_exists( 'product_brand' ),
);

$catArgs      = array(
	'taxonomy'   => 'pwb-brand',
	'orderby'    => 'name',
	'order'      => 'asc',
	'hide_empty' => false,
);
$brandDisplay = array();
$parentBrands = array();
if ( taxonomy_exists( 'pwb-brand' ) ) {
	$productBrands = get_terms( $catArgs );
	foreach ( $productBrands as $c ) {
		if ( 0 == $c->parent ) {
			array_push( $parentBrands, $c->term_id );
		}
		$brandDisplay[ $c->term_id ] = $c->name;
	}
}

$formLink = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getTabUrl( WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getView()->getCode() );
?>

<div class="woobewoo-plugin" id="containerWrapperElementor">
	<form id="wpfFiltersEditForm" data-href="<?php echo esc_attr( $formLink ); ?>">
		<div class="woobewoo_row">
			<div class="col-md-12">
				<div class="woobewoo-input-group" id="wpfChooseFiltersBlock" data-no-preview="1">
					<div class="woobewoo-group-label">
						<?php echo esc_html__( 'Filter name:', 'woo-product-filter' ); ?>
					</div>
					<?php
					HtmlWpf::text(
						'title',
						array(
							'value' => '',
						)
					);
					?>
				</div>
			</div>
		</div>
		<div class="wpfMainTabsContainer">
			<div class="woobewoo_row">
				<div class="col-md-12 wpfFiltersTabContents">
					<?php require_once 'woofiltersEditTabFilters.php'; ?>
				</div>
			</div>
		</div>
		<?php
		HtmlWpf::hidden(
			'settings',
			array(
				'value' => '',
			)
		);
		HtmlWpf::hidden(
			'settings[filters][order]',
			array(
				'value' => '',
			)
		);
		HtmlWpf::hidden(
			'settings[filters][preselect]',
			array(
				'value' => '',
			)
		);
		HtmlWpf::hidden(
			'esettings',
			array(
				'value' => '',
			)
		);
		?>


		<?php HtmlWpf::hidden( 'mod', array( 'value' => 'woofilters' ) ); ?>
		<?php HtmlWpf::hidden( 'action', array( 'value' => 'woobewoo_pf_save' ) ); ?>
		<?php HtmlWpf::hidden( 'id', array( 'value' => '' ) ); ?>
	</form>
	<div class="woobewoo-clear"></div>
</div>
<?php
