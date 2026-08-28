<?php
/**
 * Product Filter by WBW - Show Admin Notice
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="error notice is-dismissible">
	<p><?php WooBeWoo_PF_Html::echoEscapedHtml( $this->errorMsg ); ?></p>
</div>
