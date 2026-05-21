<?php
/**
 * Product Filter by WBW - Show Admin Notice
 *
 * @version 3.1.8
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="error notice is-dismissible">
	<p><?php HtmlWpf::echoEscapedHtml($this->errorMsg); ?></p>
</div>
