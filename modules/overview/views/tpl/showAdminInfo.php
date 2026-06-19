<?php
/**
 * Product Filter by WBW - Overview - Show Admin Info
 *
 * @version 3.1.8
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="wpf-notice-dismis notice notice-info is-dismissible"<?php echo empty($this->msgSlug) ? '' : ' data-disslug="' . esc_attr($this->msgSlug) . '"'; ?>>
	<p><?php HtmlWpf::echoEscapedHtml($this->message); ?></p>
</div>
