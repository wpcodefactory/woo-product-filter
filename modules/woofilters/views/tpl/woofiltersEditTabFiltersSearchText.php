<?php
/**
 * Product Filter by WBW - Woofilters Edit Tab Filters Search Text
 *
 * @version 3.1.8
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

ob_start();
	?>
	<div class="row-settings-block col-md-12">
		<?php if ( FrameWpf::_()->isWCLicense() ) { ?>
		<img class="wpfProAd" src="<?php echo esc_url( $adPath . 'search_text.png' ); ?>">
		<?php } else { ?>
		<a href="<?php echo esc_url( 'https://' . WPF_WP_PLUGIN_URL . '/plugins/woocommerce-filter/' ); ?>" target="_blank">
			<img class="wpfProAd" src="<?php echo esc_url( $adPath . 'search_text.png' ); ?>">
		</a>
		<?php } ?>
	</div>
	<?php echo DispatcherWpf::applyFilters( 'woobewoo_pf_search_text_option', ob_get_clean() );
