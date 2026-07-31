<?php
/**
 * Product Filter by WBW - Woofilters_WidgetViewWpf Class
 *
 * @version 3.3.0
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

class Woofilters_WidgetViewWpf extends ViewWpf { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound

	/**
	 * displayWidget.
	 *
	 * @version 3.3.0
	 */
	public function displayWidget( $instance, $args ) {
		if ( isset( $instance['id'] ) && $instance['id'] ) {
			// now disabled rule: if is_shop() or is_product_category() or is_product_tag() or is_customize_preview()
			$widget = do_shortcode( '[' . WPF_SHORTCODE . ' id=' . absint( $instance['id'] ) . ' mode="widget"]' );
			if ( '' !== $widget ) {
				if ( isset( $args['before_widget'] ) && isset( $args['after_widget'] ) ) {
					$widget = $args['before_widget'] . $widget . $args['after_widget'];
				}
				HtmlWpf::echoEscapedHtml( $widget );
			}
		}
	}

	/**
	 * displayForm.
	 *
	 * @version 3.3.0
	 */
	public function displayForm( $data, $widget ) {
		FrameWpf::_()->addStyle( 'woofilters_widget', $this->getModule()->getModPath() . 'css/gmap_widget.css' );
		$filters     = FrameWpf::_()->getModule( 'woofilters' )->getModel()->getFromTbl();
		$filtersOpts = array();
		if ( empty( $filters ) ) {
			$filtersOpts[0] = esc_html__( 'You have no filters', 'woo-product-filter' );
		} else {
			$filtersOpts[0] = esc_html__( 'Select filter', 'woo-product-filter' );
			foreach ( $filters as $filter ) {
				$filtersOpts[ $filter['id'] ] = $filter['title'];
			}
		}
		$this->assign( 'filtersOpts', $filtersOpts );
		$this->displayWidgetForm( $data, $widget );
	}
}
