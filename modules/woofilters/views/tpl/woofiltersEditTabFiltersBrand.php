<?php
/**
 * Product Filter by WBW - Woofilters Edit Tab Filters Brand
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

if ( ! apply_filters( 'woobewoo_pf_is_pro', false ) ) {
?>
<div class="row-settings-block col-md-12">
	<?php if ( WooBeWoo_PF_Frame::_()->isWCLicense() ) { ?>
	<img class="wpfProAd" src="<?php echo esc_url( $adPath . 'product_brands.png' ); ?>">
	<?php } else { ?>
	<a href="<?php echo esc_url( 'https://' . WPF_WP_PLUGIN_URL . '/plugins/woocommerce-filter/' ); ?>" target="_blank">
		<img class="wpfProAd" src="<?php echo esc_url( $adPath . 'product_brands.png' ); ?>">
	</a>
	<?php } ?>
</div>
<?php }
WooBeWoo_PF_Dispatcher::doAction( 'addEditTabFilters', 'partEditTabFiltersBrand' );
