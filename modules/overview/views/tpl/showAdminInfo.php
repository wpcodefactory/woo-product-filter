<div class="wpf-notice-dismis notice notice-info is-dismissible"<?php echo empty($this->msgSlug) ? '' : ' data-disslug="' . esc_attr($this->msgSlug) . '"'; ?>>
	<p><?php HtmlWpf::echoEscapedHtml($this->message); ?></p>
</div>
