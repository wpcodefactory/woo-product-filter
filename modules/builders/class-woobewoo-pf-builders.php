<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Builders Class
 *
 * Page builder and block integrations, one file per builder, each guarded by class or function
 * existence: Gutenberg block woobewoo/product-filter (block.json, server render, plain JS editor
 * script, block theme mode), Bricks, Breakdance, Oxygen, Divi (4 and 5 compatibility layer),
 * WPBakery, Avada (Fusion Builder) and Beaver Builder. Elementor stays in modules/woofilters_widget.
 * Every integration exposes a filter id control and renders the [wpf-filters] shortcode.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Builders extends WooBeWoo_PF_Module {

	/** Loaded integrations: slug => label. */
	public $registered = array();

	public function init() {
		parent::init();
		foreach ( $this->integrations() as $slug => $file ) {
			$path = __DIR__ . '/integrations/' . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
		add_action( 'init', array( $this, 'boot' ), 5 );
		add_filter( 'woobewoo_pf_selftest_rows', array( $this, 'selfTestRows' ) );
	}

	/**
	 * slug => file. Each file defines a class WooBeWoo_PF_Builder_<Slug> with static detect() and register().
	 */
	public function integrations() {
		return array(
			'gutenberg'  => 'class-woobewoo-pf-builder-gutenberg.php',
			'bricks'     => 'class-woobewoo-pf-builder-bricks.php',
			'breakdance' => 'class-woobewoo-pf-builder-breakdance.php',
			'oxygen'     => 'class-woobewoo-pf-builder-oxygen.php',
			'divi'       => 'class-woobewoo-pf-builder-divi.php',
			'wpbakery'   => 'class-woobewoo-pf-builder-wpbakery.php',
			'avada'      => 'class-woobewoo-pf-builder-avada.php',
			'beaver'     => 'class-woobewoo-pf-builder-beaver.php',
		);
	}

	/**
	 * Register every integration whose builder is present. Runs on init (5) so builder hooks that
	 * fire on init 10 and later are still in time.
	 */
	public function boot() {
		foreach ( array_keys( $this->integrations() ) as $slug ) {
			$class = 'WooBeWoo_PF_Builder_' . ucfirst( $slug );
			if ( ! class_exists( $class ) || ! is_callable( array( $class, 'detect' ) ) ) {
				continue;
			}
			if ( call_user_func( array( $class, 'detect' ) ) ) {
				$label = is_callable( array( $class, 'label' ) ) ? call_user_func( array( $class, 'label' ) ) : ucfirst( $slug );
				if ( call_user_func( array( $class, 'register' ) ) ) {
					$this->registered[ $slug ] = $label;
				}
			}
		}
	}

	/**
	 * Filters for pickers: id => title.
	 */
	public static function filterChoices() {
		$out   = array();
		$wf    = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
		$model = $wf ? $wf->getModel( 'woofilters' ) : null;
		if ( ! $model ) {
			return $out;
		}
		foreach ( (array) $model->setSelectFields( 'id, title' )->getFromTbl() as $row ) {
			$out[ (int) $row['id'] ] = $row['title'] ? $row['title'] : ( '#' . $row['id'] );
		}
		return $out;
	}

	/**
	 * Shared renderer: the shortcode, optionally in the block theme wrapper.
	 */
	public static function renderFilter( $filterId, $atts = array() ) {
		$filterId = (int) $filterId;
		if ( ! $filterId ) {
			return '<div class="wpf-builder-empty">' . esc_html__( 'Pick a filter in the settings of this element.', 'woo-product-filter' ) . '</div>';
		}
		$html = do_shortcode( '[wpf-filters id="' . $filterId . '"]' );
		if ( ! empty( $atts['blockTheme'] ) || ! empty( $atts['block_theme'] ) ) {
			$html = self::blockThemeWrap( $html, $filterId, $atts );
		}
		return $html;
	}

	/**
	 * Block theme mode: a wrapper compatible with woocommerce/product-collection (same layout
	 * classes as the core Product Filters block, so it sits beside it without fighting).
	 */
	public static function blockThemeWrap( $html, $filterId, $atts = array() ) {
		$classes = array( 'wpf-block-theme', 'wc-block-product-filters', 'wp-block-woocommerce-product-filters' );
		if ( ! empty( $atts['className'] ) ) {
			$classes[] = sanitize_html_class( $atts['className'] );
		}
		$align = ! empty( $atts['align'] ) ? ' align' . sanitize_html_class( $atts['align'] ) : '';
		return '<div class="' . esc_attr( implode( ' ', $classes ) ) . $align . '" data-wpf-filter="' . (int) $filterId . '" data-wc-interactive="{&quot;namespace&quot;:&quot;woobewoo/product-filter&quot;}">' . $html . '</div>';
	}

	/**
	 * Self test rows: one per builder present (registered or not).
	 */
	public function selfTestRows( $rows ) {
		foreach ( $this->integrations() as $slug => $file ) {
			$class = 'WooBeWoo_PF_Builder_' . ucfirst( $slug );
			if ( ! class_exists( $class ) ) {
				continue;
			}
			$present = (bool) call_user_func( array( $class, 'detect' ) );
			$label   = is_callable( array( $class, 'label' ) ) ? call_user_func( array( $class, 'label' ) ) : ucfirst( $slug );
			if ( ! $present && 'gutenberg' !== $slug ) {
				continue; // Nothing to report for builders that are not installed.
			}
			$ok     = isset( $this->registered[ $slug ] );
			$rows[] = array(
				'state'  => $ok ? 'pass' : 'partial',
				'label'  => sprintf( /* translators: %s: builder name */ __( '%s filter element registered', 'woo-product-filter' ), $label ),
				'detail' => $ok ? __( 'Builder modules registered: the filter element is in the builder with a filter picker.', 'woo-product-filter' ) : __( 'The builder is present but its element API was not found; use the shortcode block instead.', 'woo-product-filter' ),
			);
		}
		return $rows;
	}
}
