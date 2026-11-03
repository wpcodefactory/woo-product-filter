<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Usage Class
 *
 * Anonymous feature usage, opt-in. The store owner is asked once, on the plugin's own screens; nothing is sent
 * before a "Yes" (and nothing ever after a "No"). Once a week the store sends counts only: how many filters use
 * each feature, block type and display, which store-wide switches are on, which Pro modules are active, and how
 * often shoppers pick each block type (sampled, one request in ten). No product, order, customer, address, e-mail
 * or site address is sent; the store is identified by a one-way hash of its address and salt.
 *
 * The report goes to WPF_USAGE_ENDPOINT (default https://woobewoo.com/wp-json/wbw-usage/v1/report, filter
 * `woobewoo_pf_usage_endpoint`). Pro adds its own counts through `woobewoo_pf_usage_report`. The choice can be
 * changed any time from the Plugins screen ("Usage sharing: on / off").
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Usage extends WooBeWoo_PF_Module {

	const OPTION   = 'wpf_usage_share';    // '' not asked yet, 'yes', 'no'
	const COUNTERS = 'wpf_usage_counters'; // shopper picks since the last report
	const LAST     = 'wpf_usage_last';     // time of the last successful report
	const CRON     = 'wpf_usage_report';
	const ENDPOINT = 'https://woobewoo.com/wp-json/wbw-usage/v1/report';
	const SAMPLE   = 10;

	public function init() {
		parent::init();
		add_action( 'admin_notices', array( $this, 'notice' ) );
		add_action( 'admin_post_wpf_usage_choice', array( $this, 'choice' ) );
		add_action( self::CRON, array( $this, 'send' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WPF_DIR . 'woo-product-filter.php' ), array( $this, 'actionLink' ) );
		if ( self::sharing() ) {
			add_filter( 'woobewoo_pf_frontend4_response', array( $this, 'countPicks' ), 30, 4 );
			add_action( 'init', array( $this, 'schedule' ) );
		}
	}

	public static function sharing() {
		return 'yes' === get_option( self::OPTION, '' ) && (bool) apply_filters( 'woobewoo_pf_usage_share', true );
	}

	public static function endpoint() {
		$url = defined( 'WPF_USAGE_ENDPOINT' ) ? (string) WPF_USAGE_ENDPOINT : self::ENDPOINT;
		return (string) apply_filters( 'woobewoo_pf_usage_endpoint', $url );
	}

	public function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'weekly', self::CRON );
		}
	}

	/* ------------------------------------------------------------------ asking */

	private function onPluginScreen() {
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only, picks where the notice shows
		return 0 === strpos( $page, 'wpf-' );
	}

	/**
	 * One plain question on the plugin's screens until it is answered.
	 */
	public function notice() {
		if ( '' !== get_option( self::OPTION, '' ) || ! current_user_can( 'manage_options' ) || ! $this->onPluginScreen() ) {
			return;
		}
		$yes = wp_nonce_url( admin_url( 'admin-post.php?action=wpf_usage_choice&choice=yes' ), 'wpf_usage_choice' );
		$no  = wp_nonce_url( admin_url( 'admin-post.php?action=wpf_usage_choice&choice=no' ), 'wpf_usage_choice' );
		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Help us make Product Filter better?', 'woo-product-filter' ) . '</strong> ';
		echo esc_html__( 'Share which filter features you use, once a week. Only counts are sent, such as "3 filters use colour swatches". No products, orders, customers, e-mails or your site address.', 'woo-product-filter' ) . '</p>';
		echo '<p><a class="button button-primary" href="' . esc_url( $yes ) . '">' . esc_html__( 'Yes, share usage', 'woo-product-filter' ) . '</a> <a class="button" href="' . esc_url( $no ) . '">' . esc_html__( 'No thanks', 'woo-product-filter' ) . '</a></p></div>';
	}

	public function choice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'woo-product-filter' ) );
		}
		check_admin_referer( 'wpf_usage_choice' );
		$choice = isset( $_GET['choice'] ) && 'yes' === $_GET['choice'] ? 'yes' : 'no';
		update_option( self::OPTION, $choice, false );
		if ( 'yes' === $choice ) {
			$this->schedule();
			$this->send();
		} else {
			wp_clear_scheduled_hook( self::CRON );
			delete_option( self::COUNTERS );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'plugins.php' ) );
		exit;
	}

	public function actionLink( $links ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}
		$on        = self::sharing();
		$url       = wp_nonce_url( admin_url( 'admin-post.php?action=wpf_usage_choice&choice=' . ( $on ? 'no' : 'yes' ) ), 'wpf_usage_choice' );
		$links[]   = '<a href="' . esc_url( $url ) . '">' . esc_html( $on ? __( 'Usage sharing: on (turn off)', 'woo-product-filter' ) : __( 'Usage sharing: off (turn on)', 'woo-product-filter' ) ) . '</a>';
		return $links;
	}

	/* ------------------------------------------------------------------ shopper picks (sampled) */

	/**
	 * woobewoo_pf_frontend4_response: one request in ten adds ten to the block types the shopper picked.
	 */
	public function countPicks( $out, $query = null, $filter = null, $ctx = null ) {
		if ( ! is_object( $query ) || ! is_array( $filter ) || ! method_exists( $query, 'params' ) || wp_rand( 1, self::SAMPLE ) !== 1 ) {
			return $out;
		}
		$params = $query->params();
		if ( ! $params ) {
			return $out;
		}
		$types = array();
		foreach ( WooBeWoo_PF_Frontend4::blocks( isset( $filter['settings'] ) ? $filter['settings'] : array() ) as $b ) {
			if ( ! empty( $b['key'] ) && isset( $b['block']['id'] ) ) {
				$types[ (string) $b['key'] ] = (string) $b['block']['id'];
			}
		}
		$c = get_option( self::COUNTERS, array() );
		$c = is_array( $c ) ? $c : array();
		$c['requests'] = ( isset( $c['requests'] ) ? (int) $c['requests'] : 0 ) + self::SAMPLE;
		foreach ( array_keys( $params ) as $name ) {
			$key = preg_replace( '/^wpf_/', '', (string) $name );
			if ( 'page' === $key ) {
				continue;
			}
			$type = isset( $types[ $key ] ) ? $types[ $key ] : 'other';
			$k    = 'picks_' . preg_replace( '/[^A-Za-z0-9_]/', '', $type );
			$c[ $k ] = ( isset( $c[ $k ] ) ? (int) $c[ $k ] : 0 ) + self::SAMPLE;
		}
		update_option( self::COUNTERS, $c, false );
		return $out;
	}

	/* ------------------------------------------------------------------ the report */

	private static function bump( &$arr, $key, $n = 1 ) {
		$arr[ $key ] = ( isset( $arr[ $key ] ) ? $arr[ $key ] : 0 ) + $n;
	}

	private static function on( $v ) {
		return ! empty( $v ) && '0' !== (string) $v;
	}

	/**
	 * Counts only. Public for the Help screen's "what is sent" preview and for tests.
	 */
	public static function report() {
		global $wpdb;
		if ( ! class_exists( 'WooBeWoo_PF_Frontend4' ) || ! class_exists( 'WooBeWoo_PF_Db' ) ) {
			return array();
		}
		$features = array();
		$blocks   = array();
		$displays = array();
		$total    = 0;
		$engine4  = 0;
		$rows     = (array) WooBeWoo_PF_Db::get( 'SELECT id, setting_data FROM `@__filters`' );
		foreach ( $rows as $r ) {
			$settings = woobewoo_pf_decode_settings( $r['setting_data'] );
			$s        = isset( $settings['settings'] ) && is_array( $settings['settings'] ) ? $settings['settings'] : array();
			++$total;
			$e4 = '4' === WooBeWoo_PF_Frontend4::engine_of( (int) $r['id'], $settings );
			if ( $e4 ) {
				++$engine4;
			}
			$f    = array();
			$sv   = function ( $k ) use ( $settings ) {
				return WooBeWoo_PF_Frontend4::setting( $settings, $k );
			};
			$f[ 'engine_' . ( $e4 ? '4' : 'classic' ) ] = 1;
			$place = isset( $s['wbw_placement'] ) ? sanitize_key( (string) $s['wbw_placement'] ) : '';
			$f[ 'placement_' . ( '' === $place ? 'shortcode' : $place ) ] = 1;
			if ( ! empty( $s['wbw_style'] ) ) {
				$f['made_with_setup_wizard'] = 1;
				$f[ 'look_' . sanitize_key( (string) $s['wbw_style'] ) ] = 1;
			}
			if ( $e4 ) {
				$map = array(
					'ajax'               => 'enable_ajax',
					'apply_as_you_pick'  => 'auto_update_filter',
					'phone_drawer'       => 'mobile_drawer',
					'drawer_on_desktop'  => 'mobile_drawer_always',
					'remember_filters'   => 'remember_filters',
					'picked_chips'       => 'show_chips',
					'live_found_count'   => 'show_found_count',
					'skeleton_loader'    => 'loading_skeleton',
					'sizes_left_badge'   => 'sizes_left_badge',
					'clear_button'       => 'show_clean_button',
				);
				foreach ( $map as $feature => $key ) {
					if ( self::on( $sv( $key ) ) ) {
						$f[ $feature ] = 1;
					}
				}
				$bar = (string) $sv( 'top_bar' );
				if ( '1' === $bar || ( '' === $bar && 'above' === $place ) ) {
					$f['filter_bar_all_filters'] = 1;
				}
				$finder = $sv( 'finder' );
				if ( is_array( $finder ) && ! empty( $finder['enabled'] ) ) {
					$f['step_by_step_finder'] = 1;
				}
				if ( ! empty( $s['popular_picks'] ) ) {
					$f['pro_popular_picks'] = 1;
				}
			}
			$order = empty( $s['filters']['order'] ) ? array() : json_decode( (string) $s['filters']['order'], true );
			foreach ( (array) $order as $b ) {
				if ( empty( $b['id'] ) || ! is_string( $b['id'] ) ) {
					continue;
				}
				$id = preg_replace( '/[^A-Za-z0-9_]/', '', $b['id'] );
				$bs = isset( $b['settings'] ) && is_array( $b['settings'] ) ? $b['settings'] : array();
				self::bump( $blocks, $id );
				$disp = ! empty( $bs['display_type'] ) ? $bs['display_type'] : ( ! empty( $bs['f_frontend_type'] ) ? $bs['f_frontend_type'] : '' );
				if ( is_string( $disp ) && '' !== $disp ) {
					self::bump( $displays, sanitize_key( $disp ) );
				}
				if ( ! $e4 ) {
					continue;
				}
				if ( isset( $bs['show_more_top'] ) && (int) $bs['show_more_top'] > 0 ) {
					$f['top_values_then_show_more'] = 1;
				}
				if ( 'wpfPrice' === $id && ( ! isset( $bs['f_price_bars'] ) || self::on( $bs['f_price_bars'] ) ) ) {
					$f['price_bars'] = 1;
				}
				if ( ! isset( $bs['f_few_as_tabs'] ) || self::on( $bs['f_few_as_tabs'] ) ) {
					$f['few_choices_as_tabs'] = 1;
				}
				foreach ( $bs as $k => $v ) {
					if ( 0 === strpos( (string) $k, 'f_cond_' ) && '' !== (string) $v && '0' !== (string) $v ) {
						$f['dependent_or_conditional_blocks'] = 1;
						break;
					}
				}
				if ( isset( $bs['f_enable_title'] ) && 'yes_close' === $bs['f_enable_title'] ) {
					$f['block_starts_folded'] = 1;
				}
			}
			foreach ( $f as $k => $one ) {
				self::bump( $features, $k );
			}
		}

		// Store-wide switches (counted once per store, as "filters" = 1 when on).
		$site  = array();
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		if ( $speed && method_exists( $speed, 'isOn' ) ) {
			foreach ( array( 'wbw_clean_links' => 'clean_links', 'canonical_links' => 'canonical_noindex', 'wbw_bot_limit' => 'bot_speed_limit', 'wbw_robots_rules' => 'robots_rules', 'wbw_short_links' => 'short_links_qr', 'wbw_cache_counts' => 'count_cache', 'wbw_allow_ai_shoppers' => 'allow_ai_shoppers' ) as $key => $name ) {
				if ( $speed->isOn( $key ) ) {
					$site[ $name ] = 1;
				}
			}
		}
		$log = get_option( 'wpf_switch_log', array() );
		if ( ! empty( $log ) ) {
			$site['importer_used'] = 1;
		}

		$products = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$bucket   = $products < 100 ? '<100' : ( $products < 1000 ? '100-999' : ( $products < 10000 ? '1k-9k' : ( $products < 100000 ? '10k-99k' : '100k+' ) ) );
		$theme    = wp_get_theme();
		$counters = get_option( self::COUNTERS, array() );

		$report = array(
			'schema'   => 1,
			'site'     => hash( 'sha256', home_url() . '|' . ( defined( 'AUTH_SALT' ) ? AUTH_SALT : '' ) ),
			'plugin'   => WPF_VERSION,
			'pro'      => defined( 'WOOBEWOO_PF_PRO_VERSION' ) ? WOOBEWOO_PF_PRO_VERSION : '',
			'licensed' => class_exists( 'WooBeWoo_PF_Pro_Gate' ) && WooBeWoo_PF_Pro_Gate::licensed() ? 1 : 0,
			'wp'       => preg_replace( '/^(\d+\.\d+).*$/', '$1', get_bloginfo( 'version' ) ),
			'wc'       => defined( 'WC_VERSION' ) ? preg_replace( '/^(\d+\.\d+).*$/', '$1', WC_VERSION ) : '',
			'php'      => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'theme'    => $theme ? sanitize_key( $theme->get_template() ) : '',
			'block_theme' => function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ? 1 : 0,
			'products' => $bucket,
			'filters'  => array( 'total' => $total, 'engine4' => $engine4 ),
			'features' => $features,
			'site_features' => $site,
			'blocks'   => $blocks,
			'displays' => $displays,
			'picks'    => is_array( $counters ) ? array_map( 'intval', $counters ) : array(),
			'pro_modules' => array(),
		);
		/**
		 * Pro (and add-ons) add their counts: 'pro_modules' => array( code => 1 ), 'features' => array( 'pro_x' => n ).
		 * Counts and short keys only.
		 *
		 * @since 4.0.0
		 */
		return (array) apply_filters( 'woobewoo_pf_usage_report', $report );
	}

	/**
	 * Weekly send; the pick counters start again after a report was received.
	 */
	public function send() {
		if ( ! self::sharing() ) {
			return false;
		}
		$report = self::report();
		if ( empty( $report['site'] ) ) {
			return false;
		}
		$res = wp_remote_post(
			self::endpoint(),
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $report ),
			)
		);
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$body = $code ? json_decode( (string) wp_remote_retrieve_body( $res ), true ) : null;
		// Kept only when the report was stored (a resend within the hour is answered "skipped": keep the counts).
		if ( $code >= 200 && $code < 300 && is_array( $body ) && ! empty( $body['ok'] ) && empty( $body['skipped'] ) ) {
			delete_option( self::COUNTERS );
			update_option( self::LAST, time(), false );
			return true;
		}
		return false;
	}
}
