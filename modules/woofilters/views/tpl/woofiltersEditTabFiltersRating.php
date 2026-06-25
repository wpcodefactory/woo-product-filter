<?php
/**
 * Product Filter by WBW - Woofilters Edit Tab Filters Rating
 *
 * @version 3.1.9
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

ViewWpf::display('woofiltersEditTabCommonTitle');

$ratingTypes = array(
	'list'     => esc_attr__( 'Radiobuttons list', 'woo-product-filter' ),
	'dropdown' => esc_attr__( 'Dropdown', 'woo-product-filter' ),
);
$ratingTypes = DispatcherWpf::applyFilters( 'getAdminFilterTypes', $ratingTypes, 'wpfRating' );
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
					'attrs' => 'class="woobewoo-flat-input"'
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
DispatcherWpf::doAction( 'addEditTabFilters', 'partEditTabFiltersRatingStars' );
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
