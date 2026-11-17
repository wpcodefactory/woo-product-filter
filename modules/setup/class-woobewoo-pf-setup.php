<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Setup Class
 *
 * One click smart setup wizard: Setup tab, live preview of a draft on the real shop page,
 * automatic placement of published filters, activation redirect, first week card.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Setup extends WooBeWoo_PF_Module {

	/**
	 * Filters already rendered by the auto placement on this request (id => true).
	 */
	protected $_placed = array();

	/**
	 * Draft being previewed on this request, or null.
	 */
	protected $_previewDraft = null;

	/**
	 * Id of the draft being previewed.
	 */
	protected $_previewDraftId = '';

	/**
	 * True while the draft itself is rendered (other filters are hidden during a preview).
	 */
	protected $_renderingDraft = false;

	/**
	 * init.
	 */
	public function init() {
		parent::init();
		WooBeWoo_PF_Dispatcher::addFilter( 'mainAdminTabs', array( $this, 'addAdminTab' ) );
		WooBeWoo_PF_Dispatcher::addFilter( 'mainBreadcrumbs', array( $this, 'addBreadcrumb' ) );

		add_action( WooBeWoo_PF_Setup_Model_Events::SCAN_EVENT, array( $this, 'runScanBatchEvent' ) );
		add_action( WooBeWoo_PF_Setup_Model_Events::FIRST_WEEK_EVENT, array( $this, 'firstWeekEvent' ) );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'maybeRedirectAfterActivation' ), 5 );
		} else {
			add_action( 'wp', array( $this, 'maybeStartPreview' ), 1 );
			add_action( 'woocommerce_before_shop_loop', array( $this, 'renderAbove' ), 15 );
			add_action( 'woocommerce_sidebar', array( $this, 'renderSidebar' ), 5 );
			add_action( 'wp_footer', array( $this, 'renderFallback' ), 5 );
		}
	}

	/**
	 * Setup tab, first in the rail.
	 */
	public function addAdminTab( $tabs ) {
		$tabs['setup'] = array(
			'label'      => esc_html__( 'Setup', 'woo-product-filter' ),
			'callback'   => array( $this, 'getTabContent' ),
			'fa_icon'    => 'fa-magic',
			'sort_order' => 1,
		);
		return $tabs;
	}

	public function addBreadcrumb( $list ) {
		if ( 'setup' === WooBeWoo_PF_Frame::_()->getModule( 'options' )->getActiveTab() ) {
			$list[] = array( 'label' => esc_html__( 'Setup', 'woo-product-filter' ), 'url' => '' );
		}
		return $list;
	}

	public function getTabContent() {
		return $this->getView()->getTabContent();
	}

	public function runScanBatchEvent() {
		$this->getModel()->runScanBatchEvent();
	}

	public function firstWeekEvent() {
		$this->getModel()->firstWeekEvent();
	}

	/**
	 * Right after activation, when no filter exists, open the wizard.
	 */
	public function maybeRedirectAfterActivation() {
		if ( ! get_transient( 'wpf_setup_redirect' ) ) {
			return;
		}
		delete_transient( 'wpf_setup_redirect' );
		if ( wp_doing_ajax() || ! current_user_can( 'manage_options' ) || is_network_admin() || isset( $_GET['activate-multi'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( $this->countFilters() > 0 ) {
			return;
		}
		wp_safe_redirect( $this->getSetupUrl() );
		exit;
	}

	public function countFilters() {
		return (int) WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getCount();
	}

	public function getSetupUrl() {
		return WooBeWoo_PF_Frame::_()->getModule( 'options' )->getTabUrl( 'setup' );
	}

	/* ------------------------------------------------------------------ live preview of a draft */

	/**
	 * `?wpf_preview=<draft>` on the shop page: render the draft above the products for admins.
	 */
	public function maybeStartPreview() {
		$draftId = WooBeWoo_PF_Req::getVar( 'wpf_preview', 'get' );
		if ( empty( $draftId ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$draft = $this->getModel()->getDraft( $draftId );
		if ( ! $draft ) {
			return;
		}
		$place = WooBeWoo_PF_Req::getVar( 'wpf_place', 'get' );
		if ( $place && in_array( $place, array( 'sidebar', 'above', 'drawer', 'shortcode' ), true ) ) {
			$draft['settings']['wbw_placement'] = $place;
		}
		$this->_previewDraft   = $draft;
		$this->_previewDraftId = (string) $draftId;
		// The draft page changes with every edit under the same address: no page cache may keep it.
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
		// Hide published filters while previewing so the owner sees only the draft.
		add_filter( 'wpf_woobewoo_pf_display_mode', array( $this, 'hideOthersInPreview' ), 999 );
		add_action( 'wp_head', array( $this, 'previewHead' ) );
		add_filter( 'show_admin_bar', '__return_false', 999 );
	}

	public function hideOthersInPreview( $mode ) {
		return ( $this->_previewDraft && ! $this->_renderingDraft ) ? 0 : $mode;
	}

	public function previewHead() {
		echo '<style id="wpf-preview-css">html{scroll-behavior:auto}'
			. '.wpf-preview-note{position:fixed;left:16px;bottom:16px;z-index:999999;display:flex;align-items:center;gap:10px;max-width:calc(100vw - 32px);box-sizing:border-box;padding:10px 10px 10px 16px;border-radius:999px;background:#1A1A1A;color:#FFFFFF;font:600 13px/1.3 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;box-shadow:0 8px 24px rgba(0,0,0,.25)}'
			. '.wpf-preview-note button{flex:none;border:0;border-radius:999px;background:#FFFFFF;color:#1A1A1A;font:inherit;padding:6px 12px;cursor:pointer}'
			. '.wpf-preview-note.is-busy{opacity:.75}'
			. '</style>';
		add_action( 'wp_footer', array( $this, 'previewFoot' ), 99 );
	}

	/**
	 * The draft preview opened in its own tab ("View on my shop"): a small note that says only the owner sees it,
	 * and a listener that reloads the page (same scroll) each time the wizard or builder saves this draft, or
	 * opens the live shop once the draft is published. Inside the admin preview iframe nothing of this runs.
	 *
	 * @since 4.0.0
	 */
	public function previewFoot() {
		$i18n = array(
			'note'    => __( 'Draft preview. Only you can see it. It updates as you change the setup.', 'woo-product-filter' ),
			'refresh' => __( 'Refresh', 'woo-product-filter' ),
			'busy'    => __( 'Updating…', 'woo-product-filter' ),
		);
		?>
		<script id="wpf-preview-live">
		( function () {
			if ( window.top !== window ) {
				return;
			}
			var draft = <?php echo wp_json_encode( $this->_previewDraftId ); ?>, place = <?php echo wp_json_encode( (string) WooBeWoo_PF_Req::getVar( 'wpf_place', 'get' ) ); ?>, T = <?php echo wp_json_encode( $i18n ); ?>, last = 0, KEY = 'wpf-preview-y';
			try {
				var y = window.sessionStorage.getItem( KEY );
				if ( y !== null ) {
					window.sessionStorage.removeItem( KEY );
					window.addEventListener( 'load', function () { window.scrollTo( 0, parseInt( y, 10 ) || 0 ); } );
				}
			} catch ( e ) {}
			var note = document.createElement( 'div' ), text = document.createElement( 'span' ), btn = document.createElement( 'button' );
			note.className = 'wpf-preview-note';
			note.setAttribute( 'role', 'status' );
			text.textContent = T.note;
			btn.type = 'button';
			btn.textContent = T.refresh;
			note.appendChild( text );
			note.appendChild( btn );
			document.body.appendChild( note );
			/** This page again with the draft: the filter drops the preview keys from the address when a shopper picks. */
			function selfUrl() {
				var u = new URL( window.location.href );
				u.searchParams.set( 'wpf_preview', draft );
				if ( place ) {
					u.searchParams.set( 'wpf_place', place );
				}
				return u.toString();
			}
			function go( url ) {
				note.classList.add( 'is-busy' );
				text.textContent = T.busy;
				url = String( url || '' );
				var same = ! url || ( url.indexOf( 'wpf_preview=' + draft ) !== -1 && ( url.match( /[?&]wpf_place=([a-z]+)/ ) || [ '', '' ] )[ 1 ] === place );
				if ( same ) {
					try {
						window.sessionStorage.setItem( KEY, String( window.pageYOffset || 0 ) );
					} catch ( e ) {}
				}
				// Same draft and place: reload with the shopper's own picks kept. Else the placement changed, or the
				// filter was published: open the address the setup sent.
				window.location.replace( same ? selfUrl() : url );
			}
			btn.addEventListener( 'click', function () { go( '' ); } );
			function onMsg( m ) {
				if ( ! m || m.draft !== draft || ! m.t || m.t <= last ) {
					return;
				}
				last = m.t;
				go( m.url );
			}
			try {
				new BroadcastChannel( 'wpf-preview' ).onmessage = function ( e ) { onMsg( e.data ); };
			} catch ( e ) {}
			window.addEventListener( 'storage', function ( e ) {
				if ( e.key === 'wpf-preview-live' && e.newValue ) {
					try { onMsg( JSON.parse( e.newValue ) ); } catch ( x ) {}
				}
			} );
		} )();
		</script>
		<?php
	}

	/**
	 * Render one filter (published row or preview draft) once per request.
	 */
	protected function renderFilterHtml( $filter, $isDraft = false ) {
		$view = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getView();
		if ( $isDraft ) {
			$params = array(
				'id'       => $this->getModel()->previewId( $this->_previewDraftId ),
				'settings' => $filter['settings'],
				'mode'     => '',
			);
			$this->_renderingDraft = true;
			$html                  = $view->renderHtml( $params );
			$this->_renderingDraft = false;
			return $html ? '<div class="wpfPreviewDraft" data-wpf-draft="' . esc_attr( $this->_previewDraftId ) . '">' . $html . '</div>' : '';
		}
		// Placed by "Where on the page": the filter's "Show on" and "Which devices" rules apply, as for a widget
		// (mode '' is the shortcode, which shows wherever it is pasted).
		return (string) $view->renderHtml( array( 'id' => (int) $filter['id'], 'mode' => 'widget' ) );
	}

	/**
	 * Draft HTML for the admin preview (in place refresh of the iframe), with the placement wrapper.
	 *
	 * @since 4.0.0
	 */
	public function renderDraftHtml( $draft, $draftId ) {
		$this->_previewDraft   = $draft;
		$this->_previewDraftId = $draftId;
		$place                 = empty( $draft['settings']['wbw_placement'] ) ? 'above' : $draft['settings']['wbw_placement'];
		if ( 'shortcode' === $place ) {
			$place = 'above';
		}
		$html = $this->renderFilterHtml( $draft, true );
		return $html ? '<div class="wpfAutoPlace wpfAutoPlace-' . esc_attr( $place ) . '">' . $html . '</div>' : '';
	}

	/**
	 * Published filters with an automatic placement (set by the wizard), keyed by placement.
	 */
	protected function getPlacedFilters() {
		static $cache = null;
		if ( is_null( $cache ) ) {
			$cache = array( 'above' => array(), 'sidebar' => array(), 'drawer' => array() );
			$rows  = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->setSelectFields( 'id, setting_data' )->getFromTbl();
			foreach ( (array) $rows as $row ) {
				if ( empty( $row['setting_data'] ) || false === strpos( $row['setting_data'], 'wbw_placement' ) ) {
					continue;
				}
				$settings = woobewoo_pf_decode_settings( $row['setting_data'] );
				$place    = empty( $settings['settings']['wbw_placement'] ) ? '' : $settings['settings']['wbw_placement'];
				if ( isset( $cache[ $place ] ) ) {
					$cache[ $place ][] = array( 'id' => (int) $row['id'] );
				}
			}
		}
		return $cache;
	}

	protected function outputPlacement( $place, $wrapClass ) {
		if ( $this->_previewDraft ) {
			$draftPlace = empty( $this->_previewDraft['settings']['wbw_placement'] ) ? 'above' : $this->_previewDraft['settings']['wbw_placement'];
			if ( 'shortcode' === $draftPlace ) {
				$draftPlace = 'above'; // The shortcode goes wherever the owner pastes it; preview it above the products.
			}
			if ( $draftPlace === $place && empty( $this->_placed['draft'] ) ) {
				$this->_placed['draft'] = true;
				$html                   = $this->renderFilterHtml( $this->_previewDraft, true );
				if ( $html ) {
					echo '<div class="wpfAutoPlace ' . esc_attr( $wrapClass ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filter HTML is built and escaped by the woofilters view
				}
			}
			return;
		}
		$list = $this->getPlacedFilters();
		foreach ( $list[ $place ] as $filter ) {
			if ( ! empty( $this->_placed[ $filter['id'] ] ) ) {
				continue;
			}
			$this->_placed[ $filter['id'] ] = true;
			$html                           = $this->renderFilterHtml( $filter );
			if ( $html ) {
				echo '<div class="wpfAutoPlace ' . esc_attr( $wrapClass ) . '">' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filter HTML is built and escaped by the woofilters view
			}
		}
	}

	public function renderAbove() {
		// woocommerce_before_shop_loop also fires inside [products paginate="true"] on ordinary pages and page builder
		// pages: the wizard's placements are for the shop and product archives only.
		if ( ! $this->_previewDraft && ! ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) ) {
			return;
		}
		$this->outputPlacement( 'above', 'wpfAutoPlace-above' );
	}

	public function renderSidebar() {
		if ( ! $this->_previewDraft && ! ( function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() ) ) ) {
			return;
		}
		$this->outputPlacement( 'sidebar', 'wpfAutoPlace-sidebar' );
	}

	/**
	 * Block themes and builders fire no shop loop hooks: print in the footer and let a tiny
	 * script move the filter before the product loop. "Drawer only" always renders here.
	 */
	public function renderFallback() {
		$isShopLike = function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
		if ( ! $isShopLike && ! $this->_previewDraft ) {
			return;
		}
		ob_start();
		$this->outputPlacement( 'drawer', 'wpfAutoPlace-drawer' );
		$this->outputPlacement( 'above', 'wpfAutoPlace-above wpfAutoPlace-late' );
		$this->outputPlacement( 'sidebar', 'wpfAutoPlace-sidebar wpfAutoPlace-late' );
		$html = ob_get_clean();
		if ( '' === trim( $html ) ) {
			return;
		}
		$selector = 'ul.products';
		if ( $this->_previewDraft && ! empty( $this->_previewDraft['settings']['product_container_selector'] ) ) {
			$selector = $this->_previewDraft['settings']['product_container_selector'];
		}
		echo '<div class="wpfAutoPlaceLate" hidden>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filter HTML is built and escaped by the woofilters view
		// The loop is the first candidate that is a real grid: never a menu item or header element with class "products".
		$js = '(function(){var w=document.querySelector(".wpfAutoPlaceLate");if(!w){return;}var sel=' . wp_json_encode( $selector ) . ';var cands=[sel,"ul.products",".wc-block-product-template",".wp-block-woocommerce-product-collection",".wc-block-grid__products",".e-loop-container",".elementor-loop-container",".woocommerce-loop",".products"];var loop=null;'
			. 'var ok=function(n){return n&&!n.closest("nav,header,footer,.menu,.site-header,.site-footer,.wpfAutoPlaceLate,[data-wpf4]");};'
			. 'for(var i=0;i<cands.length&&!loop;i++){var list;try{list=document.querySelectorAll(cands[i]);}catch(e){list=[];}for(var j=0;j<list.length;j++){if(ok(list[j])){loop=list[j];break;}}}'
			. 'Array.prototype.slice.call(w.children).forEach(function(el){if(el.classList.contains("wpfAutoPlace-drawer")){document.body.appendChild(el);return;}if(loop&&loop.parentNode){loop.parentNode.insertBefore(el,loop);}else{document.body.appendChild(el);}});w.removeAttribute("hidden");w.remove();})();';
		echo '<script id="wpf-auto-place">' . $js . '</script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.Security.EscapeOutput.OutputNotEscaped -- static inline script with json encoded selector
	}
}

/**
 * Event names shared with the model (kept in a tiny class so the module can hook them
 * before the model is created).
 *
 * @since 4.0.0
 */
class WooBeWoo_PF_Setup_Model_Events {
	const SCAN_EVENT       = 'wpf_setup_scan_batch';
	const FIRST_WEEK_EVENT = 'wpf_setup_first_week';
}
