<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Speed_View Class
 *
 * Renders the Speed and SEO + Help screen (one template, canvas Help). The Help module and
 * the old Settings tab render the same screen with a different heading / open card.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Speed_View extends WooBeWoo_PF_View {

	/**
	 * @param string $screen speed | help | settings
	 */
	public function getTabContent( $screen = 'speed' ) {
		$module  = $this->getModule();
		$modPath = $module->getModPath();
		WooBeWoo_PF_Frame::_()->addStyle( 'woobewoo-pf-admin-speed', $modPath . 'css/admin.speed.css', array( 'woobewoo-pf-wbw-admin' ) );
		WooBeWoo_PF_Frame::_()->addScript( 'woobewoo-pf-admin-speed', $modPath . 'js/admin.speed.js', array(), false, true );
		$nonce = wp_create_nonce( 'woobewoo-pf-save-nonce' );
		WooBeWoo_PF_Frame::_()->addJSVar(
			'woobewoo-pf-admin-speed',
			'wpfSpeedData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => $nonce,
				'screen'  => $screen,
				'i18n'    => array(
					'saved'      => __( 'Saved.', 'woo-product-filter' ),
					'saving'     => __( 'Saving…', 'woo-product-filter' ),
					'error'      => __( 'Something went wrong. Try again.', 'woo-product-filter' ),
					'running'    => __( 'Checking your shop page…', 'woo-product-filter' ),
					'rebuilding' => __( 'Rebuilding', 'woo-product-filter' ),
					'fresh'      => __( 'Fresh', 'woo-product-filter' ),
					'needs'      => __( 'Needs a rebuild', 'woo-product-filter' ),
					'started'    => __( 'Rebuild started. The meter fills as it runs.', 'woo-product-filter' ),
					'done'       => __( 'Done. The index is fresh.', 'woo-product-filter' ),
					'justNow'    => __( 'just now', 'woo-product-filter' ),
					'sending'    => __( 'Sending…', 'woo-product-filter' ),
					'pass'       => __( 'Passes', 'woo-product-filter' ),
					'fail'       => __( 'Fix', 'woo-product-filter' ),
					'checking'   => __( 'Checking…', 'woo-product-filter' ),
					'copied'     => __( 'Copied', 'woo-product-filter' ),
					'copy'       => __( 'Copy', 'woo-product-filter' ),
					'score'      => __( '%d of 100', 'woo-product-filter' ),
					'llmsDone'   => __( 'llms.txt is ready.', 'woo-product-filter' ),
				),
			)
		);

		$values = array();
		foreach ( $module->optionKeys() as $key => $default ) {
			$values[ $key ] = $module->opt( $key, $default );
		}
		$help    = WooBeWoo_PF_Frame::_()->getModule( 'help' );
		$setup   = WooBeWoo_PF_Frame::_()->getModule( 'setup' );
		$filters = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->setSelectFields( 'id, title' )->setOrderBy( 'id' )->setSortOrder( 'desc' )->getFromTbl();
		$user    = wp_get_current_user();

		// Self test banner from the last run (cheap), else nothing until "Run the check".
		$banner = get_option( 'wpf_selftest_banner' );
		if ( ! is_array( $banner ) || empty( $banner['text'] ) ) {
			$banner = null;
		}

		$this->assign( 'screen', $screen );
		$this->assign( 'values', $values );
		$this->assign( 'health', $module->indexHealth() );
		$this->assign( 'robots', $module->robotsRules() );
		$this->assign( 'benchmarkLine', $module->benchmarkLine() );
		$this->assign( 'benchmarkUrl', apply_filters( 'woobewoo_pf_benchmark_url', 'https://woobewoo.com/benchmark/' ) );
		$this->assign( 'slowedToday', $module->slowedToday() );
		$this->assign( 'selfTestBanner', $banner );
		$this->assign( 'downloadUrl', add_query_arg( array( 'action' => 'woobewoo_pf_help_diagnostics', 'mod' => 'help', 'pl' => WPF_CODE, 'reqType' => 'ajax', 'wpfNonce' => $nonce ), admin_url( 'admin-ajax.php' ) ) );
		$this->assign( 'userEmail', $user ? $user->user_email : '' );
		$this->assign( 'supportEmail', $values['wbw_support_email'] ? $values['wbw_support_email'] : 'support@woobewoo.com' );
		$this->assign( 'tickets', $module->tickets() );
		$this->assign( 'filtersList', is_array( $filters ) ? $filters : array() );
		$this->assign( 'legacyOptions', $this->legacyOptions() );
		$this->assign( 'readiness', $this->readinessScore() );
		$this->assign( 'isPro', (bool) WooBeWoo_PF_Frame::_()->isPro() );
		return parent::getContent( 'speedTab' );
	}

	/**
	 * AI shopping readiness (Free model in the abilities module), or null when that module is off.
	 *
	 * @since 4.0.0
	 */
	public function readinessScore() {
		$abilities = WooBeWoo_PF_Frame::_()->getModule( 'abilities' );
		if ( ! $abilities || ! method_exists( $abilities, 'getModel' ) ) {
			return null;
		}
		$model = $abilities->getModel( 'readiness' );
		if ( ! $model || ! method_exists( $model, 'score' ) ) {
			return null;
		}
		$score = $model->score();
		return is_array( $score ) && isset( $score['score'], $score['checks'] ) && is_array( $score['checks'] ) ? $score : null;
	}

	/**
	 * Tag class for a readiness score: pass from 80, partial below, fail below 40.
	 */
	public function readinessTone( $score ) {
		if ( null === $score ) {
			return '';
		}
		$score = (int) $score;
		return $score >= 80 ? 'wbw-tag--pass' : ( $score >= 40 ? 'wbw-tag--partial' : 'wbw-tag--fail' );
	}

	/**
	 * True when the check with this id did not pass (so its one click fix button shows).
	 */
	public function readinessNeeds( $score, $checkId ) {
		foreach ( (array) $score['checks'] as $c ) {
			if ( isset( $c['id'] ) && $checkId === $c['id'] ) {
				return 'pass' !== $c['status'];
			}
		}
		return false;
	}

	/**
	 * "2 min", "3 h", "4 d" for the big number.
	 */
	public function shortAgo( $ts ) {
		$d = max( 0, time() - (int) $ts );
		if ( $d < 60 ) {
			return __( 'now', 'woo-product-filter' );
		}
		if ( $d < HOUR_IN_SECONDS ) {
			/* translators: %d: minutes */
			return sprintf( __( '%d min', 'woo-product-filter' ), (int) floor( $d / 60 ) );
		}
		if ( $d < DAY_IN_SECONDS ) {
			/* translators: %d: hours */
			return sprintf( __( '%d h', 'woo-product-filter' ), (int) floor( $d / HOUR_IN_SECONDS ) );
		}
		/* translators: %d: days */
		return sprintf( __( '%d d', 'woo-product-filter' ), (int) floor( $d / DAY_IN_SECONDS ) );
	}

	/**
	 * Every option of the old Settings screen (plugin options + Pro additions through
	 * optionsDefine) as plain rows. Labels pass through the plain words map (gettext filter).
	 */
	public function legacyOptions() {
		$all  = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getAll();
		$rows = array();
		foreach ( $all as $cat ) {
			if ( empty( $cat['opts'] ) ) {
				continue;
			}
			foreach ( $cat['opts'] as $key => $opt ) {
				$html = isset( $opt['html'] ) ? $opt['html'] : '';
				if ( ! $html ) {
					continue;
				}
				$row = array(
					'label'   => isset( $opt['label'] ) ? $opt['label'] : $key,
					'desc'    => isset( $opt['desc'] ) ? $opt['desc'] : '',
					'value'   => isset( $opt['value'] ) ? $opt['value'] : '',
					'type'    => 'text',
					'options' => array(),
					'pro'     => ! empty( $opt['pro'] ),
				);
				if ( 'checkboxHiddenVal' === $html || 'checkbox' === $html || 'checkboxToggle' === $html ) {
					$row['type'] = 'switch';
				} elseif ( 'selectbox' === $html || 'selectlist' === $html ) {
					$row['type']    = 'select';
					$row['options'] = isset( $opt['options'] ) ? ( is_callable( $opt['options'] ) ? call_user_func( $opt['options'] ) : (array) $opt['options'] ) : array();
				} elseif ( 'startMetaButton' === $html ) {
					$row['type']   = 'button';
					$row['button'] = __( 'Rebuild the index', 'woo-product-filter' );
				} elseif ( 'startOptimizingButton' === $html ) {
					$row['type']   = 'button';
					// The button runs the same full rebuild (it writes fresh, compact tables): say so.
					$row['button'] = __( 'Rebuild the index (tidies the tables too)', 'woo-product-filter' );
				}
				$rows[ $key ] = $row;
			}
		}
		return $rows;
	}
}
