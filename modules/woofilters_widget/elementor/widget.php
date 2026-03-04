<?php
/**
 * Maps widget class
 */
class WpfWoofiltersWidget extends WP_Widget {
	public function __construct() {
		$widgetOps = array(
			'classname' => 'WpfWoofiltersWidget',
			'description' => 'Displays Filters'
		);
		parent::__construct( 'WpfWoofiltersWidget', WPF_WP_PLUGIN_NAME, $widgetOps );
	}
	public function widget( $args, $instance ) {
		if ( is_array( $args ) ) {
			extract( $args );
		}
		extract($instance);
		FrameWpf::_()->getModule('woofilters_widget')->getView()->displayWidget($instance, $args);
	}
	public function form( $instance ) {
		extract($instance);
		FrameWpf::_()->getModule('woofilters_widget')->getView()->displayForm($instance, $this);
	}
	public function update( $new_instance, $old_instance ) {
		return $new_instance;
	}
}
