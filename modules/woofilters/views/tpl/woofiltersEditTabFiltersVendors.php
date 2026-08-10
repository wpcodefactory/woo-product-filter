<?php
/**
 * Product Filter by WBW - Woofilters Edit Tab Filters Vendors
 *
 * @version 3.3.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

ob_start();
?>
<div class="row-settings-block col-md-12">
	<?php if ( FrameWpf::_()->isWCLicense() ) { ?>
		<img class="wpfProAd" src="<?php echo esc_url( $adPath . 'vendors.png' ); ?>">
	<?php } else { ?>
		<a
			href="<?php echo esc_url( 'https://' . WPF_WP_PLUGIN_URL . '/plugins/woocommerce-filter/' ); ?>"
			target="_blank"
		>
			<img class="wpfProAd" src="<?php echo esc_url( $adPath . 'vendors.png' ); ?>">
		</a>
	<?php } ?>
</div>
<?php echo DispatcherWpf::applyFilters( 'woobewoo_pf_vendors_option', ob_get_clean() );
