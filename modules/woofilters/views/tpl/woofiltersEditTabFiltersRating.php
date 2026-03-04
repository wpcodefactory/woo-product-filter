<?php
ViewWpf::display('woofiltersEditTabCommonTitle');

$ratingTypes = array(
	'list' => esc_attr__( 'Radiobuttons list', 'woo-product-filter' ),
	'dropdown' => esc_attr__( 'Dropdown', 'woo-product-filter' ),
	'linestars' => esc_attr__( 'Single line star rating', 'woo-product-filter' ) . $labelPro,
	'liststars' => esc_attr__( 'Multiline star rating', 'woo-product-filter' ) . $labelPro,
);
?>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Show on frontend as', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="
		<?php 
		echo esc_attr(__('Depending on whether you need one or several attributes to be available at the same time, show your attributes list as checkbox or dropdown.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/product-rating-settings-and-filtering/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>')
		; 
		?>
		"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::selectbox('f_frontend_type', array(
					'options' => $ratingTypes,
					'attrs' => 'class="woobewoo-flat-input' . ( $isPro ? '' : ' wpfWithProAd' ) . '"'
				));
				?>
		</div>
	</div>
</div>
<div class="row-settings-block wpfTypeSwitchable" data-type="dropdown">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Dropdown label', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Dropdown first option text.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/product-rating-settings-and-filtering/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('f_dropdown_first_option_text', array(
					'placeholder' => esc_attr__('Select all', 'woo-product-filter'),
					'attrs' => 'class="woobewoo-flat-input"'
				));
				?>
		</div>
	</div>
</div>
<?php
if ( $isPro ) :
	DispatcherWpf::doAction( 'addEditTabFilters', 'partEditTabFiltersRatingStars' );
else :
	foreach ( $ratingTypes as $key => $value ) {
		if ( strpos( $value, $labelPro ) ) {
			?>
			<div class="row-settings-block col-md-12 wpfFilterTypePro wpfHidden" data-type="<?php echo esc_attr( $key ); ?>">
				<?php if (FrameWpf::_()->isWCLicense()) { ?>
				<img class="wpfProAd" src="<?php echo esc_url( $adPath . 'rating_' . $key . '.png' ); ?>">
				<?php } else { ?>
				<a href="<?php echo esc_url('https://' . WPF_WP_PLUGIN_URL . '/plugins/woocommerce-filter/' ); ?>" target="_blank">
					<img class="wpfProAd" src="<?php echo esc_url( $adPath . 'rating_' . $key . '.png' ); ?>">
				</a>
				<?php } ?>
			</div>
			<?php
		}
	}
	?>
	<div class="row-settings-block">
		<div class="settings-block-label col-xs-4 col-sm-3">
			<?php esc_html_e( 'Use exact values', 'woo-product-filter' ); ?>
			<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr__( 'Use exact values instead of range', 'woo-product-filter' ); ?>"></i>
		</div>
		<div class="settings-block-values col-xs-8 col-sm-9">
			<div class="settings-value">
				<span class="wpfProLabel"><a href="<?php echo esc_url( $this->proLink ); ?>" target="_blank"><?php esc_html_e( 'PRO Option', 'woo-product-filter' ); ?></a></span>
			</div>
		</div>
	</div>
<?php
endif;
?>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Additional text for 1-4', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Additional text for 1-4 rating filter.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/product-rating-settings-and-filtering/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('f_add_text', array(
					'placeholder' => esc_attr__('and up', 'woo-product-filter'),
					'attrs' => 'class="woobewoo-flat-input woobewoo-width60"'
				));
				?>
		</div>
	</div>
</div>
<div class="row-settings-block">
	<div class="settings-block-label settings-w100 col-xs-4 col-sm-3">
		<?php esc_html_e('Additional text for 5', 'woo-product-filter'); ?>
		<i class="fa fa-question woobewoo-tooltip no-tooltip" title="<?php echo esc_attr(__('Additional text for 5-star rating filter.', 'woo-product-filter') . ' <a href="' . esc_url('https://' . WPF_WP_PLUGIN_URL . '/documentation/product-rating-settings-and-filtering/') . '" class="wupsales-wc-hidden" target="_blank">' . __('Learn More', 'woo-product-filter') . '</a>'); ?>"></i>
	</div>
	<div class="settings-block-values settings-w100 col-xs-8 col-sm-9">
		<div class="settings-value settings-w100">
			<?php 
				HtmlWpf::text('f_add_text5', array(
					'placeholder' => esc_attr__('5 only', 'woo-product-filter'),
					'attrs' => 'class="woobewoo-flat-input woobewoo-width60"'
				));
				?>
		</div>
	</div>
</div>
