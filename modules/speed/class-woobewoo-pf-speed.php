<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Speed Class
 *
 * Speed and SEO: index health, counts cache, clean links, canonical and noindex on
 * filtered pages, bot speed limit with an allow list for AI shoppers, robots.txt rules.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Speed extends WooBeWoo_PF_Module {

	const CACHE_SALT_OPTION = 'wpf_counts_cache_ver';
	const CACHE_TTL         = 10 * MINUTE_IN_SECONDS;

	/**
	 * Common crawler user agent fragments (lowercase).
	 */
	public static $crawlers = array( 'googlebot', 'bingbot', 'yandexbot', 'yandex', 'duckduckbot', 'baiduspider', 'slurp', 'ahrefsbot', 'semrushbot', 'mj12bot', 'dotbot', 'petalbot', 'bytespider', 'facebookexternalhit', 'applebot', 'seznambot', 'sogou', 'exabot', 'ia_archiver', 'ccbot', 'dataforseobot', 'blexbot', 'serpstatbot', 'crawler', 'spider' );

	/**
	 * AI shopper and AI search agents that are never slowed down when "Allow AI shoppers" is on.
	 */
	public static $aiShoppers = array( 'GPTBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended', 'OAI-SearchBot', 'Applebot-Extended' );

	public function init() {
		parent::init();
		WooBeWoo_PF_Dispatcher::addFilter( 'mainAdminTabs', array( $this, 'addAdminTab' ) );
		WooBeWoo_PF_Dispatcher::addFilter( 'mainBreadcrumbs', array( $this, 'addBreadcrumb' ) );

		// Counts cache invalidation.
		add_action( 'woocommerce_update_product', array( $this, 'bumpCacheSalt' ) );
		add_action( 'woocommerce_new_product', array( $this, 'bumpCacheSalt' ) );
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'bumpCacheSalt' ) );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'bumpCacheSalt' ) );
		add_action( 'deleted_post', array( $this, 'bumpCacheSaltOnDelete' ), 10, 2 );
		add_action( 'wpf_calc_meta_indexing', array( $this, 'bumpCacheSalt' ), 1 );
		add_action( 'woobewoo_pf_filter_restored', array( $this, 'bumpCacheSalt' ) );
		WooBeWoo_PF_Dispatcher::addFilter( 'woobewoo_pf_filter_settings_saving_data', array( $this, 'bumpCacheSaltOnSave' ) );

		if ( ! is_admin() ) {
			add_action( 'init', array( $this, 'maybeLimitBots' ), 0 );
			add_action( 'wp', array( $this, 'frontendHooks' ) );
		}
		add_filter( 'robots_txt', array( $this, 'robotsTxt' ), 20, 2 );
		// "Give bots a saved copy" (index/class-woobewoo-pf-bot-cache.php asks this filter).
		add_filter( 'woobewoo_pf_bot_cache_enabled', array( $this, 'botCacheEnabled' ) );
	}

	public function botCacheEnabled( $on ) {
		return $on && $this->isOn( 'wbw_bot_cache', '1' );
	}

	/**
	 * "Update results without reloading" off: the 4.0 engine loads the filtered page instead of swapping the products.
	 *
	 * @since 4.0.0
	 */
	public function ajaxResultsOn() {
		return $this->isOn( 'wbw_ajax_results', '1' );
	}

	/**
	 * "Short links to share": the Share button with a short link and a QR code under every 4.0 filter.
	 *
	 * @since 4.0.0
	 */
	public function shortLinksOn() {
		return $this->isOn( 'wbw_short_links', '1' );
	}

	public function addAdminTab( $tabs ) {
		$tabs['speed'] = array(
			'label'      => esc_html__( 'Speed and SEO', 'woo-product-filter' ),
			'callback'   => array( $this, 'getTabContent' ),
			'fa_icon'    => 'fa-bolt',
			'sort_order' => 25,
		);
		return $tabs;
	}

	public function addBreadcrumb( $list ) {
		if ( 'speed' === WooBeWoo_PF_Frame::_()->getModule( 'options' )->getActiveTab() ) {
			$list[] = array( 'label' => esc_html__( 'Speed and SEO', 'woo-product-filter' ), 'url' => '' );
		}
		return $list;
	}

	public function getTabContent() {
		return $this->getView()->getTabContent();
	}

	/* ------------------------------------------------------------------ options */

	public function opt( $key, $default = '0' ) {
		$v = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->get( $key );
		return false === $v ? $default : $v;
	}

	public function isOn( $key, $default = '0' ) {
		return '1' === (string) $this->opt( $key, $default );
	}

	/**
	 * Option keys of this screen with defaults (off for existing installs; the wizard turns them on).
	 */
	public function optionKeys() {
		return array(
			'wbw_cache_counts'      => '0',
			'wbw_ajax_results'      => '1',
			'wbw_bot_cache'         => '1', // the bot copy was always served before the switch was wired: keep that
			'wbw_clean_links'       => '0',
			'canonical_links'       => '0',
			'wbw_short_links'       => '1', // the Share button showed on every 4.0 filter before the switch was wired
			'wbw_bot_limit'         => '0',
			'wbw_bot_limit_per_min' => '30',
			'wbw_allow_ai_shoppers' => '1',
			'wbw_robots_rules'      => '0',
			'wbw_support_email'     => 'support@woobewoo.com',
		);
	}

	/* ------------------------------------------------------------------ counts cache */

	public function cacheEnabled() {
		return $this->isOn( 'wbw_cache_counts' );
	}

	public function cacheSalt() {
		$salt = get_option( self::CACHE_SALT_OPTION );
		if ( ! $salt ) {
			$salt = (string) time();
			update_option( self::CACHE_SALT_OPTION, $salt, false );
		}
		return (string) $salt;
	}

	public function bumpCacheSalt() {
		update_option( self::CACHE_SALT_OPTION, (string) microtime( true ), false );
	}

	public function bumpCacheSaltOnDelete( $postId, $post = null ) {
		if ( $post && isset( $post->post_type ) && in_array( $post->post_type, array( 'product', 'product_variation' ), true ) ) {
			$this->bumpCacheSalt();
		}
	}

	public function bumpCacheSaltOnSave( $data ) {
		$this->bumpCacheSalt();
		return $data;
	}

	/**
	 * Transient key for a counts request.
	 */
	public function cacheKey( $parts ) {
		return 'wpf_cnt_' . md5( $this->cacheSalt() . '|' . wp_json_encode( $parts ) );
	}

	/* ------------------------------------------------------------------ index health */

	/**
	 * Index health: the 4.0 index class when the engine ships it, else the legacy status.
	 *
	 * Returns running, last_run, indexed, total, percent, fresh, scheduled, last_query_ms.
	 */
	public function indexHealth() {
		if ( class_exists( 'WooBeWoo_PF_Index' ) && method_exists( 'WooBeWoo_PF_Index', 'health' ) ) {
			$h = WooBeWoo_PF_Index::health();
			if ( is_array( $h ) ) {
				$total   = isset( $h['total'] ) ? (int) $h['total'] : ( isset( $h['products'] ) ? (int) $h['products'] : 0 );
				$indexed = isset( $h['indexed'] ) ? (int) $h['indexed'] : ( isset( $h['products_indexed'] ) ? (int) $h['products_indexed'] : 0 );
				$pct     = isset( $h['percent'] ) ? (int) $h['percent'] : ( $total ? min( 100, (int) round( $indexed / $total * 100 ) ) : 100 );
				$prog    = get_option( 'wpf_index_rebuild_progress' );
				$running = ! empty( $h['running'] ) || ( is_array( $prog ) && empty( $prog['done'] ) && ! empty( $prog['total'] ) );
				// A rebuild that is queued (cron not fired yet) or still in the legacy meta pass is not done: keep the meter polling.
				$queued = (bool) wp_next_scheduled( 'wpf_calc_meta_indexing' );
				$meta   = WooBeWoo_PF_Frame::_()->getModule( 'meta' );
				if ( $meta && method_exists( $meta, 'isGlobalCalcRunning' ) && $meta->isGlobalCalcRunning() ) {
					$queued = true;
				}
				if ( $running && is_array( $prog ) && ! empty( $prog['total'] ) ) {
					$pct = min( 99, (int) floor( (int) $prog['offset'] / max( 1, (int) $prog['total'] ) * 100 ) );
				}
				return array(
					'running'       => (bool) $running,
					'last_run'      => isset( $h['last_run'] ) ? (int) $h['last_run'] : ( isset( $h['updated'] ) ? (int) $h['updated'] : 0 ),
					'indexed'       => $indexed,
					'total'         => $total,
					'percent'       => $pct,
					'fresh'         => isset( $h['fresh'] ) ? (bool) $h['fresh'] : $pct >= 95,
					'scheduled'     => ! empty( $h['scheduled'] ) || $running || $queued,
					'last_query_ms' => isset( $h['last_query_ms'] ) ? (float) $h['last_query_ms'] : ( isset( $h['query_ms'] ) ? (float) $h['query_ms'] : 0 ),
				);
			}
		}
		return $this->legacyIndexHealth();
	}

	public function legacyIndexHealth() {
		global $wpdb;
		$opts    = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel();
		$running = 2 === (int) $opts->get( 'start_indexing' );
		$changed = (int) $opts->getChanged( 'start_indexing' );
		$indexed = 0;
		if ( WooBeWoo_PF_Db::exist( '@__meta_data' ) ) {
			$indexed = (int) $wpdb->get_var( 'SELECT COUNT(DISTINCT product_id) FROM ' . WooBeWoo_PF_Db::getTableName( 'meta_data' ) . ' WHERE is_var = 0' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- our own index table name from WooBeWoo_PF_Db::getTableName(), no user input
		}
		$counts = wp_count_posts( 'product' );
		$total  = isset( $counts->publish ) ? (int) $counts->publish : 0;
		$pct    = $total ? min( 100, (int) round( $indexed / $total * 100 ) ) : 100;
		return array(
			'running'  => $running,
			'last_run' => $changed ? $changed : 0,
			'indexed'  => $indexed,
			'total'    => $total,
			'percent'  => $pct,
			'fresh'    => $changed ? ( time() - $changed < 7 * DAY_IN_SECONDS ) : false,
			'scheduled' => (bool) wp_next_scheduled( 'wpf_calc_meta_indexing' ),
			'last_query_ms' => (float) get_option( 'wpf_last_count_query_ms', 0 ),
		);
	}

	/**
	 * Public benchmark line from the `wpf_benchmark_results` option when present.
	 *
	 * @since 4.0.0
	 */
	public function benchmarkLine() {
		$r = get_option( 'wpf_benchmark_results' );
		if ( is_array( $r ) && ! empty( $r ) ) {
			$parts = array();
			$rows  = isset( $r['rows'] ) && is_array( $r['rows'] ) ? $r['rows'] : $r;
			foreach ( $rows as $k => $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$n  = isset( $row['products'] ) ? (int) $row['products'] : (int) $k;
				$ms = isset( $row['count_ms'] ) ? $row['count_ms'] : ( isset( $row['ms'] ) ? $row['ms'] : null );
				if ( $n && null !== $ms ) {
					/* translators: 1: number of products, 2: milliseconds */
					$parts[] = sprintf( __( '%1$s products %2$s ms', 'woo-product-filter' ), number_format_i18n( $n ), number_format_i18n( (float) $ms ) );
				}
			}
			if ( $parts ) {
				/* translators: %s: list of measurements */
				return sprintf( __( 'Our public benchmark: %s per count query, on the demo store.', 'woo-product-filter' ), implode( ' · ', $parts ) );
			}
		}
		return __( 'Our public benchmark: numbers land after the first benchmark run.', 'woo-product-filter' );
	}

	/**
	 * Bot requests slowed down today.
	 *
	 * @since 4.0.0
	 */
	public function slowedToday() {
		$log = get_option( 'wpf_bot_limit_log', array() );
		$n   = 0;
		$day = gmdate( 'Y-m-d' );
		foreach ( (array) $log as $entry ) {
			if ( isset( $entry['time'] ) && gmdate( 'Y-m-d', (int) $entry['time'] ) === $day ) {
				++$n;
			}
		}
		$counter = get_option( 'wpf_bot_limit_today' );
		if ( is_array( $counter ) && isset( $counter['day'], $counter['n'] ) && $counter['day'] === $day ) {
			$n = max( $n, (int) $counter['n'] );
		}
		return $n;
	}

	/**
	 * Support tickets sent from the Help card (copies kept in an option list).
	 *
	 * @since 4.0.0
	 */
	public function tickets() {
		$t = get_option( 'wpf_support_tickets', array() );
		return is_array( $t ) ? $t : array();
	}

	/**
	 * Send a ticket through wp_mail and keep a copy.
	 *
	 * @since 4.0.0
	 */
	public function sendTicket( $subject, $message, $email, $to, $attach ) {
		$to      = is_email( $to ) ? $to : $this->opt( 'wbw_support_email', 'support@woobewoo.com' );
		$subject = sanitize_text_field( $subject );
		$message = sanitize_textarea_field( $message );
		$email   = sanitize_email( $email );
		$body    = $message . "\n\n" . __( 'Reply to:', 'woo-product-filter' ) . ' ' . $email . "\n" . __( 'Site:', 'woo-product-filter' ) . ' ' . home_url( '/' ) . "\n" . __( 'Plugin:', 'woo-product-filter' ) . ' ' . WPF_VERSION . ( WooBeWoo_PF_Frame::_()->isPro() ? ' Pro' : ' Free' );
		$files   = array();
		if ( $attach ) {
			$help = WooBeWoo_PF_Frame::_()->getModule( 'help' );
			if ( $help ) {
				$json = wp_json_encode( $help->diagnostics(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
				$dir  = wp_upload_dir();
				$file = trailingslashit( $dir['basedir'] ) . 'wbw-diagnostics-' . gmdate( 'Ymd-His' ) . '.json';
				if ( ! empty( $dir['basedir'] ) && wp_is_writable( $dir['basedir'] ) && false !== file_put_contents( $file, $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
					$files[] = $file;
				} else {
					$body .= "\n\n--- diagnostics ---\n" . $json;
				}
			}
		}
		$headers = array( 'Reply-To: ' . $email );
		$sent    = wp_mail( $to, '[Product Filter] ' . $subject, $body, $headers, $files );
		foreach ( $files as $f ) {
			wp_delete_file( $f );
		}
		$list   = $this->tickets();
		$list[] = array( 'time' => time(), 'subject' => $subject, 'message' => $message, 'email' => $email, 'to' => $to, 'attached' => (bool) $attach, 'sent' => (bool) $sent );
		update_option( 'wpf_support_tickets', array_slice( $list, -50 ), false );
		return (bool) $sent;
	}

	/* ------------------------------------------------------------------ frontend: canonical, noindex, clean links */

	public function hasFilterParams() {
		return WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->existsWpfParams();
	}

	public function frontendHooks() {
		if ( $this->isOn( 'canonical_links' ) && $this->hasFilterParams() ) {
			// Our canonical points to the unfiltered URL; SEO plugins are told the same.
			add_action( 'wp_head', array( $this, 'printCanonical' ), 1 );
			remove_action( 'wp_head', 'rel_canonical' );
			add_filter( 'wpseo_canonical', array( $this, 'unfilteredUrl' ) );
			add_filter( 'rank_math/frontend/canonical', array( $this, 'unfilteredUrl' ) );
			add_filter( 'aioseo_canonical_url', array( $this, 'unfilteredUrl' ) );
			add_filter( 'get_canonical_url', array( $this, 'unfilteredUrl' ) );
			add_filter( 'wp_robots', array( $this, 'noindexFollow' ), 30 );
			add_filter( 'wpseo_robots', array( $this, 'noindexFollowString' ) );
			add_filter( 'rank_math/frontend/robots', array( $this, 'noindexFollowArray' ) );
		}
		if ( $this->isOn( 'wbw_clean_links' ) ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueueCleanLinks' ), 999 );
		}
	}

	/**
	 * Current URL without plugin parameters.
	 */
	public function unfilteredUrl( $url = '' ) {
		global $wp;
		$base = home_url( isset( $wp->request ) ? '/' . ltrim( $wp->request, '/' ) : '/' );
		$base = user_trailingslashit( $base );
		// Keep harmless, non filter query params (e.g. product-page) out too: canonical is the clean page.
		return $base;
	}

	public function printCanonical() {
		echo '<link rel="canonical" href="' . esc_url( $this->unfilteredUrl() ) . '" />' . "\n";
	}

	public function noindexFollow( $robots ) {
		$robots['noindex'] = true;
		$robots['follow']  = true;
		unset( $robots['index'], $robots['nofollow'] );
		return $robots;
	}

	public function noindexFollowString() {
		return 'noindex, follow';
	}

	public function noindexFollowArray( $robots ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';
		return $robots;
	}

	public function enqueueCleanLinks() {
		if ( ! wp_script_is( 'woobewoo-pf-frontend-filters', 'enqueued' ) && ! wp_script_is( 'woobewoo-pf-frontend-filters', 'registered' ) ) {
			return;
		}
		$js = '(function(){var helpers=["wpf_count","wpf_fbv","wpf_dpv","wpf_skip","wpf_fid","_"];function clean(){try{var u=new URL(window.location.href),changed=false;helpers.forEach(function(k){if(u.searchParams.has(k)){u.searchParams.delete(k);changed=true;}});if(changed){window.history.replaceState(window.history.state,"",u.toString());}}catch(e){}}document.addEventListener("wpfAjaxSuccess",clean);clean();})();';
		wp_add_inline_script( 'woobewoo-pf-frontend-filters', $js, 'after' );
	}

	/* ------------------------------------------------------------------ bot speed limit */

	public function isAiShopper( $ua ) {
		foreach ( self::$aiShoppers as $agent ) {
			if ( false !== stripos( $ua, $agent ) ) {
				return true;
			}
		}
		return false;
	}

	public function isCrawler( $ua ) {
		$ua = strtolower( $ua );
		if ( '' === $ua ) {
			return false;
		}
		foreach ( self::$crawlers as $fragment ) {
			if ( false !== strpos( $ua, $fragment ) ) {
				return true;
			}
		}
		return $this->isAiShopper( $ua );
	}

	/**
	 * At most N filter requests per minute per IP for crawlers; 429 with Retry-After beyond that.
	 */
	public function maybeLimitBots() {
		if ( ! $this->isOn( 'wbw_bot_limit' ) || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( ! $this->hasFilterParams() ) {
			return;
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		if ( ! $this->isCrawler( $ua ) ) {
			return;
		}
		if ( $this->isOn( 'wbw_allow_ai_shoppers', '1' ) && $this->isAiShopper( $ua ) ) {
			return;
		}
		$limit = max( 1, (int) $this->opt( 'wbw_bot_limit_per_min', '30' ) ); // same default as optionKeys() and the wizard's words
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		// A 60 second window that starts at the crawler's first counted request (a clock-minute window let a
		// crawler send nearly twice the limit across a minute boundary).
		$key    = 'wpf_bot_' . md5( $ip );
		$now    = time();
		$window = get_transient( $key );
		if ( ! is_array( $window ) || ! isset( $window['t'], $window['n'] ) || $now - (int) $window['t'] >= MINUTE_IN_SECONDS ) {
			$window = array(
				't' => $now,
				'n' => 0,
			);
		}
		++$window['n'];
		set_transient( $key, $window, 2 * MINUTE_IN_SECONDS );
		$count = (int) $window['n'];
		if ( $count > $limit ) {
			$this->logBot( $ua, $ip );
			status_header( 429 );
			nocache_headers();
			header( 'Retry-After: 60' );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'Too many filter requests. Please slow down and retry in a minute.';
			exit;
		}
	}

	protected function logBot( $ua, $ip ) {
		$log = get_option( 'wpf_bot_limit_log', array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = array( 'time' => time(), 'ua' => mb_substr( $ua, 0, 120 ), 'ip' => $ip );
		update_option( 'wpf_bot_limit_log', array_slice( $log, -20 ), false );
	}

	/**
	 * Accessibility rows for a filter: the engine's report when it exists, else our own
	 * check of the rendered markup (buttons, labels, live region, focus ring, targets).
	 *
	 * @since 4.0.0
	 */
	public function a11yReport( $filterId ) {
		$rows = apply_filters( 'woobewoo_pf_a11y_report', null, $filterId );
		if ( is_array( $rows ) && $rows ) {
			return array_values( array_map( function ( $r ) {
				return array(
					'label'  => isset( $r['label'] ) ? (string) $r['label'] : ( isset( $r['check'] ) ? (string) $r['check'] : '' ),
					'pass'   => ! empty( $r['pass'] ),
					'detail' => isset( $r['detail'] ) ? (string) $r['detail'] : '',
				);
			}, $rows ) );
		}
		$html = (string) WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getView()->renderHtml( array( 'id' => (int) $filterId, 'mode' => '' ) );
		$out  = array();
		$add  = function ( $label, $pass, $detail = '' ) use ( &$out ) {
			$out[] = array( 'label' => $label, 'pass' => (bool) $pass, 'detail' => $detail );
		};
		$hasInputs = (bool) preg_match( '/<(input|button|select)\b/i', $html );
		$add( __( 'Real buttons and inputs, not clickable divs', 'woo-product-filter' ), $hasInputs, $hasInputs ? '' : __( 'No form controls found in the rendered filter.', 'woo-product-filter' ) );
		$inputs   = preg_match_all( '/<input\b[^>]*type="(checkbox|radio|text|number|search)"[^>]*>/i', $html, $m );
		$labelled = preg_match_all( '/<label\b/i', $html ) + preg_match_all( '/aria-label(?:ledby)?=/i', $html );
		$add( __( 'Every control has a label', 'woo-product-filter' ), ! $inputs || $labelled >= $inputs, $inputs && $labelled < $inputs ? sprintf( '%d controls, %d labels', $inputs, $labelled ) : '' );
		$add( __( 'Result count announced to screen readers (aria-live)', 'woo-product-filter' ), (bool) preg_match( '/aria-live=/i', $html ) || (bool) preg_match( '/role="status"/i', $html ) );
		$add( __( 'Titles are headings or buttons', 'woo-product-filter' ), (bool) preg_match( '/<(h[2-6]|button)\b/i', $html ) );
		$add( __( 'No title-only hints (tooltips)', 'woo-product-filter' ), ! preg_match( '/<[^>]+title="[^"]+"[^>]*>(\s|<[^>]*>)*<\/(i|span)>/i', $html ) );
		$add( __( 'Keyboard: nothing uses tabindex above 0', 'woo-product-filter' ), ! preg_match( '/tabindex="[1-9]/i', $html ) );
		$add( __( 'Drawer dialog has role="dialog" when the drawer is on', 'woo-product-filter' ), ! preg_match( '/wpf4-drawer|wpfDrawer/i', $html ) || (bool) preg_match( '/role="dialog"/i', $html ) );
		return $out;
	}

	/* ------------------------------------------------------------------ robots.txt */

	/**
	 * Rules for filter parameters. Preview text in the admin; added to robots.txt when the switch is on.
	 */
	public function robotsRules() {
		$lines = array(
			'# Product Filter by WBW: keep crawlers out of filter combinations',
			'User-agent: *',
			'Disallow: /*?wpf_*',
			'Disallow: /*&wpf_*',
			'Disallow: /*?pr_*',
			'Disallow: /*&pr_*',
			'Disallow: /*?product_tag_*',
			'Disallow: /*&product_tag_*',
		);
		if ( $this->isOn( 'wbw_allow_ai_shoppers', '1' ) ) {
			$lines[] = '';
			$lines[] = '# AI shoppers may read filtered pages';
			foreach ( self::$aiShoppers as $agent ) {
				$lines[] = 'User-agent: ' . $agent;
				$lines[] = 'Allow: /';
			}
		}
		return implode( "\n", $lines );
	}

	public function robotsTxt( $output, $public ) {
		if ( $public && $this->isOn( 'wbw_robots_rules' ) ) {
			$output .= "\n" . $this->robotsRules() . "\n";
		}
		return $output;
	}
}
