<?php
	$modPath = $this->getModule()->getModPath();
?>
<section class="woobewoo-bar">
	<div class="wpf-overview-title">
		<?php esc_html_e('Welcome to WBW Product Filter', 'woo-product-filter'); ?>
	</div>
	<div class="woobewoo-clear"></div>
</section>
<section>
	<div class="woobewoo-item woobewoo-panel wpf-overview-panel">
		<div class="wpf-overview-block">
			<div class="wpf-overview-block-header">
				<div class="wpf-overview-header-title">
					<?php esc_html_e('Need help?', 'woo-product-filter'); ?>
				</div>
				<div class="wpf-overview-header-desc">
					<?php esc_html_e('You\'ll find instant answers to almost any question by searching our documentation. And if you still need help, please contact us directly.', 'woo-product-filter'); ?>
				</div>
			</div>
			<div class="wpf-overview-block-body">
				<div class="row">
					<div class="col-sm-3">
						<div class="wpf-overview-img wpf-overview-img-dc">
							<div class="wpf-overview-body-title">
								<a href="https://woobewoo.com/docs/woocommerce-filter-documentation/" target="_blank">
									<?php esc_html_e('Documentation', 'woo-product-filter'); ?>
								</a>
							</div>
							<ul>
								<li>
									<a href="https://woobewoo.com/docs/general/" target="_blank">
										<?php esc_html_e('General', 'woo-product-filter'); ?>
									</a>
								</li>
								<li>
									<a href="https://woobewoo.com/docs/filters/" target="_blank">
										<?php esc_html_e('Filters', 'woo-product-filter'); ?>
									</a>
								</li>
								<li>
									<a href="https://woobewoo.com/docs/options/" target="_blank">
										<?php esc_html_e('Options', 'woo-product-filter'); ?>
									</a>
								</li>
								<li>
									<a href="https://woobewoo.com/docs/design/" target="_blank">
										<?php esc_html_e('Design', 'woo-product-filter'); ?>
									</a>
								</li>
								<li>
									<a href="https://woobewoo.com/docs/new-features/" target="_blank">
										<?php esc_html_e('New Features', 'woo-product-filter'); ?>
									</a>
								</li>
								<li>
									<a href="https://woobewoo.com/docs/solved-issues/" target="_blank">
										<?php esc_html_e('Solved Issues', 'woo-product-filter'); ?>
									</a>
								</li>
								<li>
									<a href="https://woobewoo.com/docs/faq/" target="_blank">
										<?php esc_html_e('FAQ', 'woo-product-filter'); ?>
									</a>
								</li>
							</ul>
						</div>
					</div>
					<div class="col-sm-4">
						<div class="wpf-overview-img wpf-overview-img-wp">
							<div class="wpf-overview-body-title">
								<a href="https://wordpress.org/plugins/woo-product-filter/" target="_blank">
									<?php esc_html_e('WordPress Forum', 'woo-product-filter'); ?>
								</a>
							</div>
							<div class="wpf-overview-body-text">
								<a href="https://wordpress.org/plugins/woo-product-filter/" target="_blank">
									<?php esc_html_e('Join our community on the wordpress forum and post your question. In addition to our expert constantly on duty at the forum, you can share your experience or learn new experience for yourself from our regular users.', 'woo-product-filter'); ?>
								</a>
							</div>
						</div>
						<div class="wpf-overview-img wpf-overview-img-ct">
							<div class="wpf-overview-body-title">
								<a href="https://woobewoo.com/custom-web-development-services/" target="_blank">
									<?php esc_html_e('Custom Development', 'woo-product-filter'); ?>
								</a>
							</div>
							<div class="wpf-overview-body-text">
								<a href="https://woobewoo.com/custom-web-development-services/" target="_blank">
									<?php esc_html_e('We have expanded our development team, and now we are glad to offer you custom web and app development solutions. We offer a full range of web app development services to suit your needs. Be assured, we will cope with any task.', 'woo-product-filter'); ?>
								</a>
							</div>
						</div>
					</div>
					<div class="col-sm-5">
						<div class="wpf-overview-block">
							<div class="wpf-overview-body-title">
								<?php esc_html_e('Contact Us', 'woo-product-filter'); ?>
							</div>
							<div class="wpf-overview-body-text">
								<input type="text" class="wpf-overview-input wpf-field-fullwidth" name="wpf-name" value="" placeholder="<?php esc_html_e('Name', 'woo-product-filter'); ?>">
								<input type="text" class="wpf-overview-input wpf-field-fullwidth" name="wpf-email" data-error="<?php esc_html_e('Enter your Email', 'woo-product-filter'); ?>" value="" placeholder="<?php esc_html_e('Email', 'woo-product-filter'); ?>">
								<input type="text" class="wpf-overview-input wpf-field-fullwidth" name="wpf-subject" value="" placeholder="<?php esc_html_e('Subject', 'woo-product-filter'); ?>">
								<textarea class="wpf-overview-textarea wpf-field-fullwidth" name="wpf-desc" value="" data-error="<?php esc_html_e('Enter description', 'woo-product-filter'); ?>" placeholder="<?php esc_html_e('Description', 'woo-product-filter'); ?>"></textarea>
								<button id="wpfContactSubmit" class="wpf-overview-button wpf-overview-submit button wpf-overview-center">
									<?php esc_html_e('SEND', 'woo-product-filter'); ?>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php include_once 'overviewFeedback.php'; ?>
		
		<div class="wpf-overview-block">
			<div class="wpf-overview-block-header">
				<div class="wpf-overview-header-title">
					<?php esc_html_e('More plugins by WBW', 'woo-product-filter'); ?>
				</div>
				<div class="wpf-overview-header-desc">
					<?php esc_html_e('WBW it’s the Ready-made NoCode WooCommerce store solutions from top WordPress developers', 'woo-product-filter'); ?>
				</div>
			</div>
			<div class="wpf-overview-block-body">
				<div class="row">
					<div class="col-sm-6">
						<div class="wpf-overview-img wpf-overview-img-pt">
							<div class="wpf-overview-body-title">
								<a href="https://woobewoo.com/plugins/table-woocommerce-plugin/" target="_blank">
									<?php esc_html_e('Product Table', 'woo-product-filter'); ?>
								</a>
							</div>
							<div class="wpf-overview-body-text">
								<a href="https://woobewoo.com/plugins/table-woocommerce-plugin/" target="_blank">
									<?php esc_html_e('Get WooCommerce Product Table and start listing your products the right way. A responsive effective table of the selected products will be created automatically due to your requirements. Add a caption, sorting, searching, pagination, and other features to your product table in one click.', 'woo-product-filter'); ?>
								</a>
							</div>
						</div>
					</div>
					<div class="col-sm-6">
						<div class="wpf-overview-img wpf-overview-img-cr">
							<div class="wpf-overview-body-title">
								<a href="https://woobewoo.com/plugins/woo-currency/" target="_blank">
									<?php esc_html_e('Currency Switcher', 'woo-product-filter'); ?>
								</a>
							</div>
							<div class="wpf-overview-body-text">
								<a href="https://woobewoo.com/plugins/woo-currency/" target="_blank">
									<?php esc_html_e('Free Currency Switcher WordPress plugin by WBW allows the customers of your WooCommerce store to switch products prices easily to any currencies and get their rates converted in real-time! You can convert any currency you require and add as many currencies as you need.', 'woo-product-filter'); ?>
								</a>
							</div>
						</div>
					</div>
				</div>
				<div class="wpf-overview-center">
					<a class="wpf-overview-button button" href="https://woobewoo.com/" target="_blank">
						<?php esc_html_e('MORE', 'woo-product-filter'); ?>
					</a>
				</div>
			</div>
		</div>
	</div>
</section>
