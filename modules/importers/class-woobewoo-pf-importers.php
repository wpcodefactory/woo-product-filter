<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Importers Class
 *
 * "Switch from another filter": detects YITH WooCommerce Ajax Product Filter, HUSKY (WOOF), annasta, WooCommerce widgets/blocks,
 * BeRocket AJAX Product Filters and Filter Everything (active or inactive, by their options, posts
 * and tables), reads their filter sets, maps them to our blocks and settings, previews ours next
 * to theirs, and imports them as drafts (display_on_page none) with a mapped / unmapped report.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/models/class-woobewoo-pf-importer-base-model.php';

class WooBeWoo_PF_Importers extends WooBeWoo_PF_Module {

	/** Mapper model names, in the order shown. */
	public static $rivals = array( 'importer_yith', 'importer_husky', 'importer_berocket', 'importer_filtereverything', 'importer_annasta', 'importer_wcnative' );

	public function init() {
		parent::init();
		WooBeWoo_PF_Dispatcher::addFilter( 'mainAdminTabs', array( $this, 'addAdminTab' ) );
		WooBeWoo_PF_Dispatcher::addFilter( 'mainBreadcrumbs', array( $this, 'addBreadcrumb' ) );
		add_filter( 'woobewoo_pf_selftest_rows', array( $this, 'selfTestRows' ) );
		if ( ! is_admin() && isset( $_GET['wpf_rival_preview'], $_GET['wpfNonce'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified in the controller
			add_action( 'template_redirect', array( $this->getController(), 'woobewoo_pf_importers_rival_preview' ), 0 );
		}
		if ( ! is_admin() && get_option( 'wpf_switch_live' ) ) {
			add_action( 'template_redirect', array( $this, 'redirectLegacy' ), 1 );
		}
	}

	/**
	 * 301 their old filter links to ours, for the plugins switched from (option wpf_switch_live, kept by the switch model).
	 */
	public function redirectLegacy() {
		$live = (array) get_option( 'wpf_switch_live', array() );
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed below, never printed
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$q    = array();
		wp_parse_str( (string) wp_parse_url( $uri, PHP_URL_QUERY ), $q );
		foreach ( $live as $slug ) {
			$m = $this->mapper( $slug );
			if ( ! $m || $m->active() ) {
				continue; // their plugin still answers its own links
			}
			$to = $m->legacyUrl( $path, $q );
			if ( is_array( $to ) && ! empty( $to['args'] ) ) {
				$p    = isset( $to['path'] ) && '' !== $to['path'] ? $to['path'] : $path;
				$base = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
				if ( '' !== $base && ( $p === $base || 0 === strpos( $p, $base . '/' ) ) ) {
					$p = substr( $p, strlen( $base ) ); // WordPress in a sub folder: the request path already carries it
				}
				$dest = add_query_arg( array_map( 'rawurlencode', $to['args'] ), home_url( $p ) );
				wp_safe_redirect( $dest, 301, 'WBW Product Filter' );
				exit;
			}
		}
	}

	public function addAdminTab( $tabs ) {
		$tabs['importers'] = array(
			'label'      => esc_html__( 'Switch from another filter', 'woo-product-filter' ),
			'callback'   => array( $this, 'getTabContent' ),
			'fa_icon'    => 'fa-exchange',
			'sort_order' => 2,
			'rail_group' => 'setup',
			'icon_svg'   => '<svg class="wbw-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h13"/><path d="M14 4l3 3-3 3"/><path d="M20 17H7"/><path d="M10 14l-3 3 3 3"/></svg>',
		);
		return $tabs;
	}

	public function addBreadcrumb( $list ) {
		if ( 'importers' === WooBeWoo_PF_Frame::_()->getModule( 'options' )->getActiveTab() ) {
			$list[] = array( 'label' => esc_html__( 'Switch from another filter', 'woo-product-filter' ), 'url' => '' );
		}
		return $list;
	}

	public function getTabContent() {
		return $this->getView()->getTabContent();
	}

	/**
	 * Mapper instances.
	 */
	public function mappers() {
		$out = array();
		foreach ( self::$rivals as $name ) {
			$m = $this->getModel( $name );
			if ( $m ) {
				$out[ $m->slug() ] = $m;
			}
		}
		return $out;
	}

	public function mapper( $slug ) {
		$mappers = $this->mappers();
		return isset( $mappers[ sanitize_key( $slug ) ] ) ? $mappers[ sanitize_key( $slug ) ] : null;
	}

	/**
	 * Detection rows for every rival.
	 */
	public function detect() {
		$rows = array();
		foreach ( $this->mappers() as $m ) {
			$rows[] = $m->detect();
		}
		return $rows;
	}

	public function selfTestRows( $rows ) {
		$found = array();
		foreach ( $this->detect() as $r ) {
			if ( $r['installed'] && $r['count'] ) {
				$found[] = $r['name'] . ' (' . $r['count'] . ')';
			}
		}
		if ( $found ) {
			$rows[] = array(
				'state'  => 'partial',
				'label'  => __( 'Another filter plugin left filters to import', 'woo-product-filter' ),
				'detail' => implode( ', ', $found ) . '. ' . __( 'Open "Switch from another filter" to bring them over.', 'woo-product-filter' ),
			);
		}
		return $rows;
	}
}
