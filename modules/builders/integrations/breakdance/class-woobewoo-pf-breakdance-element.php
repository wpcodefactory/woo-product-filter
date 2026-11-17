<?php
/**
 * Product Filter by WBW - Breakdance element class.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\Breakdance\Elements\Element' ) ) {
	return;
}

class WooBeWoo_PF_Breakdance_Element extends \Breakdance\Elements\Element {

	public static function uiIcon() {
		return 'FilterIcon';
	}

	public static function tag() {
		return 'div';
	}

	public static function tagOptions() {
		return array();
	}

	public static function tagControl() {
		return false;
	}

	public static function name() {
		return esc_html__( 'Product Filter (WBW)', 'woo-product-filter' );
	}

	public static function className() {
		return 'wpf-breakdance-element';
	}

	public static function category() {
		return 'woocommerce';
	}

	public static function badge() {
		return false;
	}

	public static function slug() {
		return __CLASS__;
	}

	public static function template() {
		return '<?php echo WooBeWoo_PF_Builders::renderFilter( isset( $propertiesData[\'content\'][\'filter\'][\'filter_id\'] ) ? (int) $propertiesData[\'content\'][\'filter\'][\'filter_id\'] : 0, array( \'blockTheme\' => ! empty( $propertiesData[\'content\'][\'filter\'][\'block_theme\'] ) ) ); ?>';
	}

	public static function defaultCss() {
		return '';
	}

	public static function defaultProperties() {
		return false;
	}

	public static function defaultChildren() {
		return false;
	}

	public static function contentControls() {
		$items = array();
		foreach ( WooBeWoo_PF_Builders::filterChoices() as $id => $title ) {
			$items[] = array( 'text' => $title, 'value' => (string) $id );
		}
		return array(
			array(
				'slug'     => 'filter',
				'label'    => esc_html__( 'Filter', 'woo-product-filter' ),
				'type'     => 'section',
				'children' => array(
					array(
						'slug'    => 'filter_id',
						'label'   => esc_html__( 'Which filter', 'woo-product-filter' ),
						'type'    => 'dropdown',
						'options' => array( 'items' => $items ),
					),
					array(
						'slug'  => 'block_theme',
						'label' => esc_html__( 'Block theme mode', 'woo-product-filter' ),
						'type'  => 'toggle',
					),
				),
			),
		);
	}

	public static function designControls() {
		return array();
	}

	public static function settingsControls() {
		return array();
	}

	public static function dependencies() {
		return false;
	}

	public static function settings() {
		return false;
	}

	public static function addPanelRules() {
		return false;
	}

	public static function actions() {
		return false;
	}

	public static function nestingRule() {
		return array( 'type' => 'final' );
	}

	public static function spacingBars() {
		return false;
	}

	public static function attributes() {
		return false;
	}

	public static function experimental() {
		return false;
	}

	public static function order() {
		return 950;
	}

	public static function dynamicPropertyPaths() {
		return array();
	}

	public static function additionalClasses() {
		return false;
	}

	public static function projectManagement() {
		return false;
	}

	public static function propertyPathsToWhitelistInFlatProps() {
		return array();
	}

	public static function propertyPathsToSsrElementWhenValueChanges() {
		return array( 'content.filter.filter_id', 'content.filter.block_theme' );
	}
}
