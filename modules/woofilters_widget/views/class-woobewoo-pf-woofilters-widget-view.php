<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Woofilters_Widget_View Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Woofilters_Widget_View extends WooBeWoo_PF_View {

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
				WooBeWoo_PF_Html::echoEscapedHtml( $widget );
			}
		}
	}

	/**
	 * displayForm.
	 *
	 * @version 3.3.2
	 */
	public function displayForm( $data, $widget ) {
		WooBeWoo_PF_Frame::_()->addStyle( 'woofilters_widget', $this->getModule()->getModPath() . 'css/gmap_widget.css' );
		$filters     = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel()->getFromTbl();
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
