<?php
/**
 * Product Filter by WBW - Show Admin Notice
 *
 * @version 3.2.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="error notice is-dismissible">
	<p><?php echo esc_html( $this->errorMsg ); ?></p>
</div>
