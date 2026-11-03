<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Help Class
 *
 * Help screen: diagnostics export (JSON), compatibility self test, docs links, support box.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Help extends WooBeWoo_PF_Module {

	public function init() {
		parent::init();
		WooBeWoo_PF_Dispatcher::addFilter( 'mainAdminTabs', array( $this, 'addAdminTab' ) );
		WooBeWoo_PF_Dispatcher::addFilter( 'mainBreadcrumbs', array( $this, 'addBreadcrumb' ) );
	}

	public function addAdminTab( $tabs ) {
		$tabs['help'] = array(
			'label'      => esc_html__( 'Help', 'woo-product-filter' ),
			'callback'   => array( $this, 'getTabContent' ),
			'fa_icon'    => 'fa-life-ring',
			'sort_order' => 27,
		);
		return $tabs;
	}

	public function addBreadcrumb( $list ) {
		if ( 'help' === WooBeWoo_PF_Frame::_()->getModule( 'options' )->getActiveTab() ) {
			$list[] = array( 'label' => esc_html__( 'Help', 'woo-product-filter' ), 'url' => '' );
		}
		return $list;
	}

	/**
	 * Help shares the Speed and SEO screen (canvas Help): same cards, Help card first in view.
	 */
	public function getTabContent() {
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		if ( $speed ) {
			return $speed->getView()->getTabContent( 'help' );
		}
		return $this->getView()->getTabContent();
	}

	/**
	 * Diagnostics as an array. Emails and anything that looks like a key are stripped.
	 */
	public function diagnostics() {
		global $wpdb;
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$setup = WooBeWoo_PF_Frame::_()->getModule( 'setup' );
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		$env   = $setup ? $setup->getModel()->detectEnvironment() : array();

		$plugins = array();
		foreach ( get_plugins() as $file => $data ) {
			if ( is_plugin_active( $file ) ) {
				$plugins[] = $data['Name'] . ' ' . $data['Version'];
			}
		}

		$filters  = array();
		$versions = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'versions' );
		$stats    = $versions->statsForAll();
		foreach ( (array) WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getFromTbl() as $row ) {
			$settings  = woobewoo_pf_decode_settings( $row['setting_data'] );
			$s         = isset( $settings['settings'] ) ? $settings['settings'] : array();
			$filters[] = array(
				'id'              => (int) $row['id'],
				'title'           => (string) $row['title'],
				'blocks'          => $versions->blocksSummary( $row['setting_data'] ),
				'display_on_page' => isset( $s['display_on_page'] ) ? $s['display_on_page'] : '',
				'enable_ajax'     => isset( $s['enable_ajax'] ) ? $s['enable_ajax'] : '',
				'selector'        => isset( $s['product_container_selector'] ) ? $s['product_container_selector'] : '',
				'mobile_drawer'   => isset( $s['mobile_drawer'] ) ? $s['mobile_drawer'] : '',
				'wbw_style'       => isset( $s['wbw_style'] ) ? $s['wbw_style'] : '',
				'wbw_placement'   => isset( $s['wbw_placement'] ) ? $s['wbw_placement'] : '',
				'versions'        => isset( $stats[ (int) $row['id'] ] ) ? $stats[ (int) $row['id'] ]['count'] : 0,
			);
		}

		$options = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->getAll();
		$opts    = array();
		foreach ( (array) $options as $key => $val ) {
			$opts[ $key ] = isset( $val['value'] ) ? $val['value'] : $val;
		}

		$data = array(
			'generated'    => gmdate( 'c' ),
			'plugin'       => array( 'version' => WPF_VERSION, 'pro' => (bool) WooBeWoo_PF_Frame::_()->isPro() ),
			'wordpress'    => array( 'version' => get_bloginfo( 'version' ), 'multisite' => is_multisite(), 'locale' => get_locale(), 'cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ),
			'woocommerce'  => array( 'version' => defined( 'WC_VERSION' ) ? WC_VERSION : '', 'shop_url' => $setup ? $setup->getModel()->getShopUrl() : '' ),
			'php'          => array( 'version' => PHP_VERSION, 'memory_limit' => ini_get( 'memory_limit' ), 'max_execution_time' => ini_get( 'max_execution_time' ) ),
			'mysql'        => $wpdb->db_version(),
			'theme'        => isset( $env['theme'] ) ? $env['theme'] : array(),
			'block_theme'  => isset( $env['is_block_theme'] ) ? $env['is_block_theme'] : null,
			'builders'     => isset( $env['builders'] ) ? array_keys( array_filter( $env['builders'] ) ) : array(),
			'selectors'    => array( 'guess' => isset( $env['loop_selector'] ) ? $env['loop_selector'] : '', 'core_filters_block' => isset( $env['core_filters_block'] ) ? $env['core_filters_block'] : null ),
			'plugins'      => $plugins,
			'filters'      => $filters,
			'index_health' => $speed ? $speed->indexHealth() : array(),
			'settings'     => $opts,
			'setup_state'  => get_option( 'wpf_setup_state', array() ),
			'versions'     => array( 'total' => array_sum( wp_list_pluck( $stats, 'count' ) ), 'per_filter' => $stats ),
		);
		return $this->scrub( $data );
	}

	/**
	 * Remove emails and key-like values from the export.
	 */
	public function scrub( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				if ( is_string( $k ) && preg_match( '/(key|secret|token|password|license|licence|email|mail)/i', $k ) ) {
					$value[ $k ] = '[removed]';
				} else {
					$value[ $k ] = $this->scrub( $v );
				}
			}
			return $value;
		}
		if ( is_string( $value ) ) {
			$value = preg_replace( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[email removed]', $value );
			if ( preg_match( '/^[A-Za-z0-9_\-]{32,}$/', $value ) ) {
				return '[removed]';
			}
		}
		return $value;
	}

	/**
	 * Compatibility self test: pass / partial / fail rows in plain words.
	 */
	public function selfTest() {
		$rows  = array();
		$setup = WooBeWoo_PF_Frame::_()->getModule( 'setup' );
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		$add   = function ( $state, $label, $detail = '' ) use ( &$rows ) {
			$rows[] = array( 'state' => $state, 'label' => $label, 'detail' => $detail );
		};

		$add( class_exists( 'WooCommerce' ) ? 'pass' : 'fail', __( 'WooCommerce is active', 'woo-product-filter' ), defined( 'WC_VERSION' ) ? WC_VERSION : '' );
		$add( version_compare( PHP_VERSION, '7.4', '>=' ) ? 'pass' : 'fail', __( 'PHP 7.4 or newer', 'woo-product-filter' ), PHP_VERSION );
		$shopId = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		$add( $shopId > 0 ? 'pass' : 'fail', __( 'A shop page exists', 'woo-product-filter' ), $shopId > 0 ? get_permalink( $shopId ) : __( 'Set one under WooCommerce > Settings > Products.', 'woo-product-filter' ) );

		$tables = array( '@__filters', '@__meta_keys', '@__meta_data', '@__meta_values', '@__filter_versions' );
		$missing = array();
		foreach ( $tables as $t ) {
			if ( ! WooBeWoo_PF_Db::exist( $t ) ) {
				$missing[] = WooBeWoo_PF_Db::prepareQuery( $t );
			}
		}
		$add( empty( $missing ) ? 'pass' : 'fail', __( 'Plugin tables exist', 'woo-product-filter' ), empty( $missing ) ? '' : implode( ', ', $missing ) );

		if ( $speed ) {
			$h = $speed->indexHealth();
			if ( ! empty( $h['running'] ) ) {
				/* translators: %d: rebuild progress in percent */
				$add( 'partial', __( 'Product index covers your catalogue', 'woo-product-filter' ), sprintf( __( 'A rebuild is running (%d%% done). The shop keeps using the current index meanwhile.', 'woo-product-filter' ), $h['percent'] ) );
			} else {
				$add( $h['percent'] >= 95 ? 'pass' : 'partial', __( 'Product index covers your catalogue', 'woo-product-filter' ), sprintf( '%d%% (%d of %d products)', $h['percent'], $h['indexed'], $h['total'] ) );
			}
		}

		$cronOff = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$add( $cronOff ? 'partial' : 'pass', __( 'WP-Cron can run background jobs', 'woo-product-filter' ), $cronOff ? __( 'DISABLE_WP_CRON is set. Make sure a real cron job calls wp-cron.php, or big rebuilds will not move.', 'woo-product-filter' ) : '' );

		$count = (int) WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getCount();
		$add( $count > 0 ? 'pass' : 'partial', __( 'At least one filter exists', 'woo-product-filter' ), $count > 0 ? (string) $count : __( 'Run the one click setup.', 'woo-product-filter' ) );

		if ( class_exists( 'WooBeWoo_PF_Frontend4' ) ) {
			$legacy = WooBeWoo_PF_Frontend4::legacy_report();
			if ( ! $legacy['total'] ) {
				$detail = __( 'None. This store started on 4.0.', 'woo-product-filter' );
			} elseif ( ! $legacy['switched'] ) {
				/* translators: 1: number of filters, 2: number of errors */
				$detail = sprintf( __( '%1$d, on the classic engine, %2$d errors', 'woo-product-filter' ), $legacy['total'], count( $legacy['errors'] ) );
			} else {
				/* translators: 1: number of filters, 2: on the classic engine, 3: switched to 4.0, 4: number of errors */
				$detail = sprintf( __( '%1$d: %2$d on the classic engine, %3$d switched to the 4.0 engine, %4$d errors', 'woo-product-filter' ), $legacy['total'], $legacy['classic'], $legacy['switched'], count( $legacy['errors'] ) );
			}
			if ( $legacy['errors'] ) {
				$detail .= ' (' . implode( '; ', $legacy['errors'] ) . ')';
			}
			$add( $legacy['errors'] ? 'fail' : 'pass', __( 'Filters from before 4.0', 'woo-product-filter' ), $detail );
		}

		if ( $setup ) {
			$env  = $setup->getModel()->detectEnvironment();
			$test = $setup->getModel()->testLoopSelector( $env['loop_selector'] );
			if ( $test['error'] ) {
				$add( 'partial', __( 'Shop page can be loaded by the server', 'woo-product-filter' ), $test['error'] );
			} else {
				$add( $test['found'] ? 'pass' : ( $test['suggest'] ? 'partial' : 'fail' ), __( 'Product loop found on the shop page', 'woo-product-filter' ), $test['found'] ? $env['loop_selector'] : ( $test['suggest'] ? sprintf( /* translators: %s: CSS selector */ __( 'Use %s as the product container selector.', 'woo-product-filter' ), $test['suggest'] ) : __( 'Paste the selector of your product list in the builder.', 'woo-product-filter' ) ) );
			}
			if ( $env['core_filters_block'] ) {
				$add( 'partial', __( 'Core Product Filters block is also placed', 'woo-product-filter' ), __( 'Two filters on one page confuse shoppers. Keep one.', 'woo-product-filter' ) );
			}
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$others = array(
			'woocommerce-ajax-filters/woocommerce-filters.php'           => 'BeRocket AJAX Product Filters',
			'woocommerce-products-filter/index.php'                      => 'HUSKY / WOOF',
			'yith-woocommerce-ajax-navigation/init.php'                  => 'YITH WooCommerce Ajax Product Filter',
			'filter-everything/filter-everything.php'                    => 'Filter Everything',
			'annasta-woocommerce-product-filters/annasta-filters.php'    => 'annasta Filters',
			'themify-wc-product-filter/themify-wc-product-filter.php'    => 'Themify Product Filter',
		);
		$found = array();
		foreach ( $others as $file => $name ) {
			if ( is_plugin_active( $file ) ) {
				$found[] = $name;
			}
		}
		$add( empty( $found ) ? 'pass' : 'partial', __( 'No other filter plugin is active', 'woo-product-filter' ), implode( ', ', $found ) );

		// Remember a one line banner for the Help card.
		$fails = array();
		foreach ( $rows as $r ) {
			if ( 'fail' === $r['state'] ) {
				$fails[] = $r['label'] . ( $r['detail'] ? ': ' . $r['detail'] : '' );
			}
		}
		$loopRow = null;
		foreach ( $rows as $r ) {
			if ( false !== stripos( $r['label'], 'loop' ) ) {
				$loopRow = $r;
			}
		}
		$themeName = wp_get_theme()->get( 'Name' );
		$text      = $fails
			? implode( ' · ', $fails )
			/* translators: 1: CSS selector, 2: theme name */
			: ( $loopRow && 'pass' === $loopRow['state'] ? sprintf( __( 'Your product grid was found: %1$s on %2$s. No conflicts.', 'woo-product-filter' ), $loopRow['detail'], $themeName ) : __( 'Everything checks out. No conflicts.', 'woo-product-filter' ) );
		update_option( 'wpf_selftest_banner', array( 'tone' => $fails ? 'fail' : 'pass', 'text' => $text, 'time' => time() ), false );

		$add( get_option( 'woocommerce_hide_out_of_stock_items' ) === 'yes' ? 'pass' : 'partial', __( 'Out of stock products are hidden from the catalogue', 'woo-product-filter' ), get_option( 'woocommerce_hide_out_of_stock_items' ) === 'yes' ? '' : __( 'Optional. Turn it on under WooCommerce > Settings > Products > Inventory so shoppers never land on sold out items.', 'woo-product-filter' ) );

		return apply_filters( 'woobewoo_pf_selftest_rows', $rows );
	}
}
