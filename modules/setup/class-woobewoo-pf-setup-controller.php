<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Setup_Controller Class
 *
 * AJAX endpoints of the smart setup wizard. Every handler verifies the nonce and the
 * manage_options capability through WooBeWoo_PF_Req::verifyRequest().
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Setup_Controller extends WooBeWoo_PF_Controller {

	protected $_code = 'setup';

	/**
	 * Start or poll the catalogue scan. Returns progress and, when done, the scan,
	 * the store type, the suggestions and the environment.
	 */
	public function woobewoo_pf_setup_scan() {
		WooBeWoo_PF_Req::verifyRequest();
		$res = new WooBeWoo_PF_Response();
		// Keep stray output (PHP notices, other plugins' echoes) out of the JSON body: it is
		// captured and handed to the wizard as text, so the screen shows the real reason.
		ob_start();
		try {
			$model = $this->getModel();
			$force = '1' === WooBeWoo_PF_Req::getVar( 'force', 'post' );
			$run   = $model->runScan( $force );
			$res->addData( 'done', $run['done'] );
			$res->addData( 'progress', $run['progress'] );
			$res->addData( 'offset', isset( $run['offset'] ) ? (int) $run['offset'] : 0 );
			$res->addData( 'total', isset( $run['total'] ) ? (int) $run['total'] : 0 );
			$res->addData( 'empty', ! empty( $run['empty'] ) );
			if ( ! empty( $run['empty'] ) ) {
				$res->addData( 'add_product_url', admin_url( 'post-new.php?post_type=product' ) );
			}
			if ( $run['done'] && $run['scan'] ) {
				$type = WooBeWoo_PF_Req::getVar( 'store_type', 'post' );
				$res->addData( 'scan', $this->publicScan( $run['scan'] ) );
				$res->addData( 'suggestions', $model->buildSuggestions( $run['scan'], $type ? sanitize_key( $type ) : '' ) );
				$res->addData( 'environment', $model->detectEnvironment() );
			}
		} catch ( Throwable $e ) { // phpcs:ignore PHPCompatibility.Classes.NewClasses.throwableFound -- PHP 7+ only, and this plugin requires 7.4
			$res->addData( 'done', false );
			$res->addData( 'error', $this->describeThrowable( $e ) );
		}
		$stray = trim( (string) ob_get_clean() );
		if ( '' !== $stray && current_user_can( 'manage_options' ) ) {
			$res->addData( 'notices', mb_substr( wp_strip_all_tags( $stray ), 0, 600 ) );
		}
		return $res->ajaxExec();
	}

	/**
	 * The owner left the wizard while a scan was running: queue the remainder in the background.
	 * Sent with navigator.sendBeacon on pagehide.
	 *
	 * @since 4.0.0
	 */
	public function woobewoo_pf_setup_scan_background() {
		WooBeWoo_PF_Req::verifyRequest();
		$res = new WooBeWoo_PF_Response();
		try {
			$res->addData( 'queued', $this->getModel()->queueRemainder() );
		} catch ( Throwable $e ) { // phpcs:ignore PHPCompatibility.Classes.NewClasses.throwableFound
			$res->addData( 'error', $this->describeThrowable( $e ) );
		}
		return $res->ajaxExec();
	}

	/**
	 * "<class>: <message> at file:line" for administrators, a plain sentence for anyone else.
	 *
	 * @since 4.0.0
	 */
	protected function describeThrowable( $e ) {
		if ( current_user_can( 'manage_options' ) ) {
			return get_class( $e ) . ': ' . $e->getMessage() . ' at ' . str_replace( ABSPATH, '', $e->getFile() ) . ':' . $e->getLine();
		}
		return __( 'Something went wrong. Try again.', 'woo-product-filter' );
	}

	/**
	 * Rebuild the suggestions for another store type (the owner can correct our guess).
	 */
	public function woobewoo_pf_setup_suggest() {
		WooBeWoo_PF_Req::verifyRequest();
		$res  = new WooBeWoo_PF_Response();
		$scan = $this->getModel()->getScan();
		if ( ! $scan ) {
			$res->pushError( esc_html__( 'Scan the catalogue first.', 'woo-product-filter' ) );
			return $res->ajaxExec();
		}
		$type = sanitize_key( (string) WooBeWoo_PF_Req::getVar( 'store_type', 'post' ) );
		$res->addData( 'suggestions', $this->getModel()->buildSuggestions( $scan, $type ) );
		return $res->ajaxExec();
	}

	/**
	 * Save the wizard choices as a draft for the live preview; returns the preview URL.
	 */
	public function woobewoo_pf_setup_draft() {
		WooBeWoo_PF_Req::verifyRequest();
		$res     = new WooBeWoo_PF_Response();
		$choices = $this->readChoices();
		$draftId = $this->getModel()->saveDraft( $choices, (string) WooBeWoo_PF_Req::getVar( 'draft', 'post' ) );
		$res->addData( 'draft', $draftId );
		$res->addData( 'preview_url', $this->getModel()->previewUrl( $draftId, isset( $choices['place'] ) ? $choices['place'] : '' ) );
		return $res->ajaxExec();
	}

	/**
	 * Builder preview: store a complete settings array (same shape the save endpoint gets)
	 * as a draft and return the preview URL.
	 *
	 * @since 4.0.0
	 */
	public function woobewoo_pf_setup_draft_settings() {
		WooBeWoo_PF_Req::verifyRequest();
		$res      = new WooBeWoo_PF_Response();
		$post     = WooBeWoo_PF_Req::get( 'post' );
		$settings = isset( $post['settings'] ) && is_array( $post['settings'] ) ? $post['settings'] : array();
		// The builder sends its settings as one JSON string; decode the raw value once (see WooBeWoo_PF_Req::getJson).
		$decoded = WooBeWoo_PF_Req::getJson( 'settings_json', null );
		if ( is_array( $decoded ) ) {
			$settings = $decoded;
		}
		$settings = $this->sanitizeSettingsArray( $settings, false );
		$draftId  = $this->getModel()->saveDraftSettings( isset( $post['title'] ) ? $post['title'] : '', $settings, isset( $post['draft'] ) ? (string) $post['draft'] : '' );
		$place    = isset( $settings['wbw_placement'] ) ? sanitize_key( $settings['wbw_placement'] ) : '';
		$res->addData( 'draft', $draftId );
		$res->addData( 'preview_url', $this->getModel()->previewUrl( $draftId, $place, isset( $settings['display_on_page'] ) ? sanitize_key( $settings['display_on_page'] ) : '' ) );
		return $res->ajaxExec();
	}

	/**
	 * Rendered HTML of a draft, for in place updates of the preview iframe (no reload).
	 *
	 * @since 4.0.0
	 */
	public function woobewoo_pf_setup_preview_html() {
		WooBeWoo_PF_Req::verifyRequest();
		$res   = new WooBeWoo_PF_Response();
		$draft = $this->getModel()->getDraft( (string) WooBeWoo_PF_Req::getVar( 'draft', 'post' ) );
		if ( ! $draft ) {
			$res->pushError( esc_html__( 'The draft expired. Reload the page.', 'woo-product-filter' ) );
			return $res->ajaxExec();
		}
		$html = $this->getModule()->renderDraftHtml( $draft, (string) WooBeWoo_PF_Req::getVar( 'draft', 'post' ) );
		$res->setHtml( $html );
		$res->addData( 'placement', empty( $draft['settings']['wbw_placement'] ) ? 'above' : $draft['settings']['wbw_placement'] );
		// For a shop page that never printed the draft (theme template without the WooCommerce hooks): the preview
		// puts this HTML on the page itself and needs the storefront stylesheet with it.
		$cssFile = WPF_MODULES_DIR . 'woofilters/css/frontend-4/filter.css';
		$res->addData( 'css', add_query_arg( 'ver', file_exists( $cssFile ) ? (string) filemtime( $cssFile ) : WPF_VERSION, WPF_MODULES_PATH . 'woofilters/css/frontend-4/filter.css' ) );
		return $res->ajaxExec();
	}

	/**
	 * Recursive text sanitising of a settings array (keys and scalar values).
	 */
	protected function sanitizeSettingsArray( $arr, $unslash = true ) {
		$out = array();
		foreach ( (array) $arr as $k => $v ) {
			$k = preg_replace( '/[^a-zA-Z0-9_\[\]\-]/', '', (string) $k );
			if ( is_array( $v ) ) {
				$out[ $k ] = $this->sanitizeSettingsArray( $v, $unslash );
			} elseif ( is_string( $v ) ) {
				// Values that came from a decoded JSON string are already unslashed: a second wp_unslash() would strip the escapes of nested JSON (the block list).
				$out[ $k ] = wp_kses_post( $unslash ? wp_unslash( $v ) : $v );
			} else {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/**
	 * Loop selector self test: fetch the shop page and look for the selector.
	 */
	public function woobewoo_pf_setup_test_selector() {
		WooBeWoo_PF_Req::verifyRequest();
		$res      = new WooBeWoo_PF_Response();
		$selector = sanitize_text_field( (string) WooBeWoo_PF_Req::getVar( 'selector', 'post' ) );
		if ( '' === $selector ) {
			$selector = 'ul.products';
		}
		try {
			$result = $this->getModel()->testLoopSelector( $selector );
		} catch ( Throwable $e ) { // phpcs:ignore PHPCompatibility.Classes.NewClasses.throwableFound
			$result = array( 'found' => false, 'selector' => $selector, 'suggest' => null, 'result_count' => false, 'error' => $this->describeThrowable( $e ) );
		}
		foreach ( $result as $k => $v ) {
			$res->addData( $k, $v );
		}
		if ( $result['found'] ) {
			$res->addMessage( esc_html__( 'Your product loop was found.', 'woo-product-filter' ) );
		} elseif ( ! empty( $result['skipped'] ) ) {
			$res->addMessage( esc_html__( 'Your store is in "coming soon" mode, so we could not check the product grid. The usual selector is set; run the check again once the store is live.', 'woo-product-filter' ) );
		} elseif ( $result['suggest'] ) {
			/* translators: %s: CSS selector */
			$res->addMessage( sprintf( esc_html__( 'Not found. Set the selector to %s instead.', 'woo-product-filter' ), $result['suggest'] ) );
		} elseif ( $result['error'] ) {
			/* translators: %s: error text */
			$res->addMessage( sprintf( esc_html__( 'We could not load your shop page: %s', 'woo-product-filter' ), $result['error'] ) );
		} else {
			$res->addMessage( esc_html__( 'Not found. Your theme prints products in an unusual way; paste the selector of the product list from your browser inspector.', 'woo-product-filter' ) );
		}
		return $res->ajaxExec();
	}

	/**
	 * Publish: create the real filter row and remember the choices.
	 */
	public function woobewoo_pf_setup_publish() {
		WooBeWoo_PF_Req::verifyRequest();
		$res     = new WooBeWoo_PF_Response();
		$choices = $this->readChoices();
		if ( empty( $choices['blocks'] ) ) {
			$res->pushError( esc_html__( 'Pick at least one filter.', 'woo-product-filter' ) );
			return $res->ajaxExec();
		}
		$id = $this->getModel()->publish( $choices );
		if ( ! $id ) {
			$res->pushError( $this->getModel()->getErrors() );
			return $res->ajaxExec();
		}
		$woofilters = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
		$res->addMessage( esc_html__( 'Published.', 'woo-product-filter' ) );
		$res->addData( 'id', $id );
		$res->addData( 'edit_link', $woofilters->getEditLink( $id ) );
		$res->addData( 'filters_link', WooBeWoo_PF_Frame::_()->getModule( 'options' )->getTabUrl( 'woofilters' ) );
		$res->addData( 'shortcode', '[' . WPF_SHORTCODE . ' id=' . $id . ']' );
		$res->addData( 'block', 'wbw/woofilters' );
		$res->addData( 'shop_url', $this->getModel()->getShopUrl() );
		$state = $this->getModel()->getState();
		$res->addData( 'paused', isset( $state['paused'] ) && is_array( $state['paused'] ) ? array_values( $state['paused'] ) : array() );
		return $res->ajaxExec();
	}

	/**
	 * Dismiss the first week card.
	 */
	public function woobewoo_pf_setup_dismiss_first_week() {
		WooBeWoo_PF_Req::verifyRequest();
		$res = new WooBeWoo_PF_Response();
		$this->getModel()->dismissFirstWeek();
		$res->addMessage( esc_html__( 'Done', 'woo-product-filter' ) );
		return $res->ajaxExec();
	}

	/**
	 * Wizard choices from POST (JSON string in `choices`), sanitized.
	 */
	protected function readChoices() {
		$raw = isset( $_POST['choices'] ) ? wp_unslash( $_POST['choices'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in verifyRequest(); JSON decoded and sanitized below
		$data = is_string( $raw ) ? json_decode( $raw, true ) : array();
		if ( ! is_array( $data ) ) {
			$data = array();
		}
		$out = array(
			'title'           => isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '',
			'store_type'      => isset( $data['store_type'] ) ? sanitize_key( (string) $data['store_type'] ) : '',
			'style'           => isset( $data['style'] ) ? sanitize_key( (string) $data['style'] ) : 'clean',
			'place'           => isset( $data['place'] ) ? sanitize_key( (string) $data['place'] ) : 'above',
			'selector'        => isset( $data['selector'] ) ? sanitize_text_field( (string) $data['selector'] ) : '',
			'display_on_page' => isset( $data['display_on_page'] ) ? sanitize_key( (string) $data['display_on_page'] ) : 'both',
			'blocks'          => array(),
			'phone'           => array(),
			'speed'           => array(),
			'colours'         => array(),
		);
		if ( isset( $data['blocks'] ) && is_array( $data['blocks'] ) ) {
			foreach ( $data['blocks'] as $b ) {
				if ( ! is_array( $b ) || empty( $b['block'] ) ) {
					continue;
				}
				$out['blocks'][] = array(
					'key'     => isset( $b['key'] ) ? sanitize_text_field( (string) $b['key'] ) : '',
					'kind'    => isset( $b['kind'] ) ? sanitize_key( (string) $b['kind'] ) : '',
					'block'   => sanitize_text_field( (string) $b['block'] ),
					'attr_id' => isset( $b['attr_id'] ) ? absint( $b['attr_id'] ) : 0,
					'name'    => isset( $b['name'] ) ? sanitize_text_field( (string) $b['name'] ) : '',
				);
			}
		}
		if ( isset( $data['phone'] ) && is_array( $data['phone'] ) ) {
			$out['phone'] = array(
				'drawer'         => empty( $data['phone']['drawer'] ) ? 0 : 1,
				'button_text'    => isset( $data['phone']['button_text'] ) ? sanitize_text_field( (string) $data['phone']['button_text'] ) : '',
				'chips'          => empty( $data['phone']['chips'] ) ? 0 : 1,
				'results_button' => empty( $data['phone']['results_button'] ) ? 0 : 1,
				'remember'       => empty( $data['phone']['remember'] ) ? 0 : 1,
			);
		}
		if ( isset( $data['speed'] ) && is_array( $data['speed'] ) ) {
			foreach ( array( 'wbw_cache_counts', 'wbw_clean_links', 'canonical_links', 'wbw_bot_limit', 'wbw_allow_ai_shoppers' ) as $k ) {
				$out['speed'][ $k ] = empty( $data['speed'][ $k ] ) ? 0 : 1;
			}
		}
		if ( isset( $data['colours'] ) && is_array( $data['colours'] ) ) {
			foreach ( $data['colours'] as $k => $v ) {
				$hex = sanitize_hex_color( (string) $v );
				if ( $hex ) {
					$out['colours'][ sanitize_key( $k ) ] = $hex;
				}
			}
		}
		return $out;
	}

	/**
	 * Scan data that the wizard needs (scores stripped).
	 */
	protected function publicScan( $scan ) {
		unset( $scan['store_type']['scores'] );
		$scan['attributes'] = array_values( $scan['attributes'] );
		return $scan;
	}

	/**
	 * getPermissions.
	 */
	public function getPermissions() {
		return array(
			WPF_METHODS => array(
				'woobewoo_pf_setup_scan'               => array( WPF_ADMIN ),
				'woobewoo_pf_setup_scan_background'    => array( WPF_ADMIN ),
				'woobewoo_pf_setup_suggest'            => array( WPF_ADMIN ),
				'woobewoo_pf_setup_draft'              => array( WPF_ADMIN ),
				'woobewoo_pf_setup_draft_settings'     => array( WPF_ADMIN ),
				'woobewoo_pf_setup_preview_html'       => array( WPF_ADMIN ),
				'woobewoo_pf_setup_test_selector'      => array( WPF_ADMIN ),
				'woobewoo_pf_setup_publish'            => array( WPF_ADMIN ),
				'woobewoo_pf_setup_dismiss_first_week' => array( WPF_ADMIN ),
			),
		);
	}
}
