<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Bot_Cache Class
 *
 * Known crawlers get the whole filtered page from a one hour transient keyed by URL, with
 * `Cache-Control: public, max-age=3600`, and never run the heavy query path. Humans never get the cached copy.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Bot_Cache {

	const TTL    = HOUR_IN_SECONDS;
	const PREFIX = 'wpf_bot_page_';

	/**
	 * Crawler user agent fragments (lowercase). The speed module exports the same list; this copy keeps the index
	 * module independent of it.
	 */
	public static $crawlers = array( 'googlebot', 'bingbot', 'yandexbot', 'yandex', 'duckduckbot', 'baiduspider', 'slurp', 'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot', 'petalbot', 'bytespider', 'facebookexternalhit', 'applebot', 'seznambot', 'sogou', 'exabot', 'ia_archiver', 'ccbot', 'dataforseobot', 'blexbot', 'serpstatbot', 'gptbot', 'claudebot', 'perplexitybot', 'google-extended', 'oai-searchbot', 'applebot-extended', 'crawler', 'spider' );

	private static $key = '';

	public static function boot() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		add_action( 'template_redirect', array( __CLASS__, 'maybeServe' ), 0 );
	}

	/**
	 * Is this user agent a known crawler? Reuses the speed module helper when it is loaded.
	 */
	public static function is_crawler( $ua = null ) {
		if ( null === $ua ) {
			$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		}
		$ua = strtolower( (string) $ua );
		if ( '' === $ua ) {
			return false;
		}
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		if ( $speed && method_exists( $speed, 'isCrawler' ) ) {
			return (bool) $speed->isCrawler( $ua );
		}
		$list = apply_filters( 'woobewoo_pf_crawler_agents', self::$crawlers );
		foreach ( $list as $fragment ) {
			if ( false !== strpos( $ua, strtolower( $fragment ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Does the request carry filter parameters (canonical, legacy or pretty path parsed by the platform)?
	 */
	public static function is_filter_request() {
		if ( ! class_exists( 'WooBeWoo_PF_Query' ) ) {
			return false;
		}
		$q = WooBeWoo_PF_Query::from_request();
		return ! $q->is_empty();
	}

	public static function maybeServe() {
		if ( is_user_logged_in() || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		if ( ! self::is_crawler() || ! apply_filters( 'woobewoo_pf_bot_cache_enabled', true ) ) {
			return;
		}
		if ( ! self::is_filter_request() ) {
			return;
		}
		// 4.0.1: only for a 4.0 engine filter on this page. Stores with classic filters only (every store updated
		// from 3.x) keep serving crawlers live pages; nothing of theirs is stored in wp_options.
		if ( ! class_exists( 'WooBeWoo_PF_Frontend4' ) || ! WooBeWoo_PF_Frontend4::active_filter() ) {
			return;
		}
		// A crawler with a cart or session cookie gets a live page (its copy would carry that session).
		foreach ( array_keys( (array) $_COOKIE ) as $c ) {
			if ( 0 === strpos( (string) $c, 'wp_woocommerce_session_' ) || 0 === strpos( (string) $c, 'woocommerce_' ) ) {
				return;
			}
		}
		$url       = ( is_ssl() ? 'https://' : 'http://' ) . ( isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '' ) . ( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/' );
		self::$key = self::PREFIX . md5( $url . '|' . WooBeWoo_PF_Index::version() );
		$cached    = get_transient( self::$key );
		if ( is_array( $cached ) && ! empty( $cached['html'] ) ) {
			if ( ! headers_sent() ) {
				status_header( 200 );
				header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
				header( 'Cache-Control: private, max-age=0' ); // 4.0.1: never let a shared proxy keep the bot copy for people
				header( 'Vary: User-Agent, Cookie' );
				header( 'X-WPF-Bot-Cache: HIT' );
				header( 'Age: ' . max( 0, time() - (int) $cached['time'] ) );
			}
			echo $cached['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- full page HTML stored by us.
			exit;
		}
		if ( ! headers_sent() ) {
			header( 'Vary: User-Agent, Cookie' );
			header( 'X-WPF-Bot-Cache: MISS' );
		}
		ob_start( array( __CLASS__, 'store' ) );
	}

	public static function store( $html ) {
		if ( self::$key && is_string( $html ) && strlen( $html ) > 500 && 200 === http_response_code() && false !== stripos( $html, '</html>' ) ) {
			// 4.0.1: without a persistent object cache every copy is a wp_options row; keep at most 300 of them.
			if ( ! wp_using_ext_object_cache() ) {
				global $wpdb;
				$n = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_' . self::PREFIX ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				if ( $n >= 300 ) {
					return $html;
				}
			}
			set_transient( self::$key, array( 'html' => $html, 'time' => time() ), self::TTL );
		}
		return $html;
	}
}
