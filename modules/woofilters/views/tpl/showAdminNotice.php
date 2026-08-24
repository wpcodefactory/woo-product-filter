<?php
/**
 * Product Filter by WBW - Show Admin Notice
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="error notice is-dismissible">
	<p><?php WooBeWoo_PF_Html::echoEscapedHtml( $this->errorMsg ); ?></p>
</div>
