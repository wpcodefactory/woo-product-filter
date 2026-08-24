<?php
/**
 * Product Filter by WBW - Woofilters Edit Tab Filters Search Number
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

ob_start();
?>
<div class="row-settings-block col-md-12">
	<?php if ( WooBeWoo_PF_Frame::_()->isWCLicense() ) { ?>
	<img class="wpfProAd" src="<?php echo esc_url( $adPath . 'search_number.png' ); ?>">
	<?php } else { ?>
	<a href="<?php echo esc_url( 'https://' . WPF_WP_PLUGIN_URL . '/plugins/woocommerce-filter/' ); ?>" target="_blank">
		<img class="wpfProAd" src="<?php echo esc_url( $adPath . 'search_number.png' ); ?>">
	</a>
	<?php } ?>
</div>
<?php echo WooBeWoo_PF_Dispatcher::applyFilters( 'woobewoo_pf_search_number_option', ob_get_clean(), $attrDisplay ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
