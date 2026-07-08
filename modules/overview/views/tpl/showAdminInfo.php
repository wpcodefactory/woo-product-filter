<?php
/**
 * Product Filter by WBW - Overview - Show Admin Info
 *
 * @version 3.1.9
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wpf-notice-dismis notice notice-info is-dismissible"<?php echo empty($this->msgSlug) ? '' : ' data-disslug="' . esc_attr($this->msgSlug) . '"'; ?>>
	<p><?php echo wp_kses_post( $this->message ); ?></p>
</div>
