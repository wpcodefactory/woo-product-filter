<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Woofilters_Default_Widget Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Woofilters_Default_Widget extends WP_Widget {
	public function __construct() {
		$widgetOps = array(
			'classname'   => 'WpfWoofiltersWidget',
			'description' => 'Displays Filters',
		);
		parent::__construct( 'WpfWoofiltersWidget', WPF_WP_PLUGIN_NAME, $widgetOps );
	}

	/**
	 * widget.
	 *
	 * @version 3.3.2
	 */
	public function widget( $args, $instance ) {
		if ( is_array( $args ) ) {
			extract( $args );
		}
		extract( $instance );
		WooBeWoo_PF_Frame::_()->getModule( 'woofilters_widget' )->getView()->displayWidget( $instance, $args );
	}

	/**
	 * form.
	 *
	 * @version 3.3.2
	 */
	public function form( $instance ) {
		extract( $instance );
		WooBeWoo_PF_Frame::_()->getModule( 'woofilters_widget' )->getView()->displayForm( $instance, $this );
	}
	public function update( $new_instance, $old_instance ) {
		return $new_instance;
	}
}
