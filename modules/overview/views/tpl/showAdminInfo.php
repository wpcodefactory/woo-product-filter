<?php
/**
 * Product Filter by WBW - Overview - Show Admin Info
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

?>
<div
	class="wpf-notice-dismis notice notice-info is-dismissible"
	<?php echo empty( $this->msgSlug ) ? '' : ' data-disslug="' . esc_attr( $this->msgSlug ) . '"'; ?>
>
	<p><?php WooBeWoo_PF_Html::echoEscapedHtml( $this->message ); ?></p>
</div>
