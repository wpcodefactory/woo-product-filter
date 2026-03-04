<style type="text/css">
	.wpfDeactivateDescShell {
		display: none;
		margin-left: 25px;
		margin-top: 5px;
	}
	.wpfDeactivateReasonShell {
		display: block;
		margin-bottom: 10px;
	}
	#wpfDeactivateWnd {display: none;}
	#wpfDeactivateWnd input[type="text"],
	#wpfDeactivateWnd textarea {
		width: 100%;
	}
	#wpfDeactivateWnd h4 {
		line-height: 1.53em;
	}
	#wpfDeactivateWnd + .ui-dialog-buttonpane .ui-dialog-buttonset {
		float: none;
	}
	.wpfDeactivateSkipDataBtn {
		float: right;
		margin-top: 15px;
		text-decoration: none;
		color: #777 !important;
	}
</style>
<div id="wpfDeactivateWnd" title="<?php esc_html_e('Your Feedback', 'woo-product-filter'); ?>">
	<h4>
	<?php 
	/* translators: %s: plugin_name */
	echo esc_html(sprintf(__('If you have a moment, please share why you are deactivating %s', 'woo-product-filter'), WPF_WP_PLUGIN_NAME)); 
	?>
	</h4>
	<form id="wpfDeactivateForm">
		<label class="wpfDeactivateReasonShell">
			<?php 
				HtmlWpf::radiobutton('deactivate_reason', array(
					'value' => 'not_working',
				));
				?>
			<?php esc_html_e('Couldn\'t get the plugin to work', 'woo-product-filter'); ?>
			<div class="wpfDeactivateDescShell">
				<?php
				/* translators: %s: url */
				echo sprintf(esc_html__('If you have a question, %s and will do our best to help you', 'woo-product-filter'), '<a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/contact-us/?utm_source=plugin&utm_medium=deactivated_contact&utm_campaign=popup') . '" class="wupsales-wc-hidden" target="_blank">' . esc_html__('contact us', 'woo-product-filter') . '</a>'); 
				?>
			</div>
		</label>
		<label class="wpfDeactivateReasonShell">
			<?php 
				HtmlWpf::radiobutton('deactivate_reason', array(
					'value' => 'found_better',
				));
				?>
			<?php esc_html_e('I found a better plugin', 'woo-product-filter'); ?>
			<div class="wpfDeactivateDescShell">
				<?php 
					HtmlWpf::text('better_plugin', array(
						'placeholder' => esc_attr__('If it\'s possible, specify plugin name', 'woo-product-filter'),
					));
					?>
			</div>
		</label>
		<label class="wpfDeactivateReasonShell">
			<?php 
				HtmlWpf::radiobutton('deactivate_reason', array(
					'value' => 'not_need',
				));
				?>
			<?php esc_html_e('I no longer need the plugin', 'woo-product-filter'); ?>
		</label>
		<label class="wpfDeactivateReasonShell">
			<?php 
				HtmlWpf::radiobutton('deactivate_reason', array(
					'value' => 'temporary',
				));
				?>
			<?php esc_html_e('It\'s a temporary deactivation', 'woo-product-filter'); ?>
		</label>
		<label class="wpfDeactivateReasonShell">
			<?php 
				HtmlWpf::radiobutton('deactivate_reason', array(
					'value' => 'other',
				));
				?>
			<?php esc_html_e('Other', 'woo-product-filter'); ?>
			<div class="wpfDeactivateDescShell">
				<?php 
					HtmlWpf::text('other', array(
						'placeholder' => esc_attr__('What is the reason?', 'woo-product-filter'),
					));
					?>
			</div>
		</label>
		<?php HtmlWpf::hidden('mod', array('value' => 'promo')); ?>
		<?php HtmlWpf::hidden('action', array('value' => 'saveDeactivateData')); ?>
	</form>
	<a href="" class="wpfDeactivateSkipDataBtn"><?php esc_html_e('Skip & Deactivate', 'woo-product-filter'); ?></a>
</div>
