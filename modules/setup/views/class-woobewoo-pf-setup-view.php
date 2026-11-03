<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Setup_View Class
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Setup_View extends WooBeWoo_PF_View {

	/**
	 * The Setup tab: wizard frame, data for the JS, assets.
	 */
	public function getTabContent() {
		$module  = $this->getModule();
		$model   = $this->getModel();
		$modPath = $module->getModPath();

		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-admin-setup', $modPath . 'css/admin.setup.css', array( 'woobewoo-pf-wbw-admin' ) );
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-wbw-preview', WooBeWoo_PF_Frame::_()->getModule( 'templates' )->getModPath() . 'js/wbw-preview.js', array(), false, true );
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-admin-setup', $modPath . 'js/admin.setup.js', array( 'woobewoo-pf-wbw-preview' ), false, true );

		$scan        = $model->getScan();
		$environment = $model->detectEnvironment();
		$styles      = $model->getStyles();
		$benchmarks  = $model->getBenchmarks();
		$types       = array();
		foreach ( $benchmarks as $key => $b ) {
			$types[ $key ] = $b['label'];
		}
		$speed  = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		$health = $speed ? $speed->indexHealth() : array();
		$opts   = WooBeWoo_PF_Frame::_()->getModule( 'options' );
		$data   = array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'woobewoo-pf-save-nonce' ),
			'shopUrl'        => $model->getShopUrl(),
			'filtersUrl'     => $opts->getTabUrl( 'woofilters' ),
			'speedUrl'       => $opts->getTabUrl( 'speed' ),
			'scan'           => $scan ? $this->publicScan( $scan ) : null,
			'suggestions'    => $scan ? $model->buildSuggestions( $scan ) : null,
			'attrsWorth'     => $scan ? $model->countAttributesWorthFilter( $scan ) : 0,
			'progress'       => $model->getScanProgress(),
			'environment'    => $environment,
			'styles'         => $styles,
			'types'          => $types,
			'colours'        => $model->getThemeColours(),
			'hasFilters'     => $module->countFilters(),
			'isPro'          => (bool) WooBeWoo_PF_Frame::_()->isPro(),
			'health'         => $health,
			'firstWeekDate'  => date_i18n( 'j F', time() + 7 * DAY_IN_SECONDS ),
			'builderExists'  => (bool) WooBeWoo_PF_Frame::_()->getModule( 'woofilters' ),
			'addProductUrl'  => admin_url( 'post-new.php?post_type=product' ),
			'comingSoon'     => $model->isComingSoon(),
			'i18n'           => array(
				'scanning'      => __( 'Looking at your products…', 'woo-product-filter' ),
				'scanFailed'    => __( 'The scan did not finish. Try again.', 'woo-product-filter' ),
				'scanReason'    => __( 'What the server said:', 'woo-product-filter' ),
				/* translators: %s: HTTP status code */
				'scanNotJson'   => __( 'The server answered with a page instead of data (HTTP %s). The first 200 characters:', 'woo-product-filter' ),
				'scanNoData'    => __( 'The server answered, but without scan data.', 'woo-product-filter' ),
				'scanNetwork'   => __( 'The request did not reach the server (network error or blocked by a security plugin).', 'woo-product-filter' ),
				'noProducts'    => __( 'Add products first', 'woo-product-filter' ),
				'noProductsP'   => __( 'There are no published products yet, so there is nothing to scan. Add a few products, then come back and we will build a filter for them.', 'woo-product-filter' ),
				'addProduct'    => __( 'Add a product', 'woo-product-filter' ),
				'checkAgain'    => __( 'Check again', 'woo-product-filter' ),
				'tryAgain'      => __( 'Try again', 'woo-product-filter' ),
				/* translators: 1: products scanned so far, 2: total products */
				'scanProgress'  => __( '%1$s of %2$s products', 'woo-product-filter' ),
				'error'         => __( 'Something went wrong. Try again.', 'woo-product-filter' ),
				'previewPlaced' => __( 'Your shop page does not show filters by itself, so the preview placed this one above the products. To show it on the live shop, add its shortcode to that page.', 'woo-product-filter' ),
			),
		);
		WooBeWoo_PF_Frame::_()->addJSVar( 'woobewoo-pf-admin-setup', 'wpfSetupData', $data );

		$this->assign( 'data', $data );
		return parent::getContent( 'setupWizard' );
	}

	/**
	 * Scan data that the wizard needs (scores stripped).
	 */
	public function publicScan( $scan ) {
		unset( $scan['store_type']['scores'] );
		$scan['attributes'] = array_values( $scan['attributes'] );
		return $scan;
	}

	/**
	 * "First week" card for the Filters list (ink card), or empty string.
	 */
	public function getFirstWeekCard() {
		$card = $this->getModel()->getFirstWeekCard();
		if ( ! $card ) {
			return '';
		}
		$this->assign( 'card', $card );
		$this->assign( 'editLink', WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getEditLink( $card['filter_id'] ) );
		$this->assign( 'nonce', wp_create_nonce( 'woobewoo-pf-save-nonce' ) );
		return parent::getContent( 'setupFirstWeekCard' );
	}
}
