<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Speed_Controller Class
 *
 * AJAX: index status for the segmented meter, start a rebuild, save switches, clear cache.
 * Every handler verifies the nonce and manage_options through WooBeWoo_PF_Req::verifyRequest().
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Speed_Controller extends WooBeWoo_PF_Controller {

	protected $_code = 'speed';

	/**
	 * Index health for the meter. Also kicks WP-Cron so a background rebuild moves on.
	 */
	public function woobewoo_pf_speed_index_status() {
		WooBeWoo_PF_Req::verifyRequest();
		$res = new WooBeWoo_PF_Response();
		if ( wp_next_scheduled( 'wpf_calc_meta_indexing' ) && ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) ) {
			spawn_cron();
		}
		foreach ( $this->getModule()->indexHealth() as $k => $v ) {
			$res->addData( $k, $v );
		}
		return $res->ajaxExec();
	}

	/**
	 * One click rebuild: schedule the existing full index run in the background
	 * (same job as the Settings button with "run in background").
	 */
	public function woobewoo_pf_speed_rebuild() {
		WooBeWoo_PF_Req::verifyRequest();
		$res  = new WooBeWoo_PF_Response();
		$meta = WooBeWoo_PF_Frame::_()->getModule( 'meta' );
		if ( $meta->isGlobalCalcRunning() ) {
			$res->addMessage( esc_html__( 'A rebuild is already running.', 'woo-product-filter' ) );
			return $res->ajaxExec();
		}
		if ( '1' === WooBeWoo_PF_Req::getVar( 'inline', 'post' ) ) {
			$ok = $meta->getModel()->recalcMetaValues();
			if ( ! $ok ) {
				$res->pushError( $meta->getModel()->getErrors() );
			}
		} elseif ( ! wp_next_scheduled( 'wpf_calc_meta_indexing' ) ) {
			wp_schedule_single_event( time() + 2, 'wpf_calc_meta_indexing' );
			if ( ! defined( 'DISABLE_WP_CRON' ) || ! DISABLE_WP_CRON ) {
				spawn_cron();
			}
		}
		$this->getModule()->bumpCacheSalt();
		$res->addMessage( esc_html__( 'Rebuild started.', 'woo-product-filter' ) );
		foreach ( $this->getModule()->indexHealth() as $k => $v ) {
			$res->addData( $k, $v );
		}
		return $res->ajaxExec();
	}

	/**
	 * Save the Speed and SEO switches.
	 */
	public function woobewoo_pf_speed_save() {
		WooBeWoo_PF_Req::verifyRequest();
		$res  = new WooBeWoo_PF_Response();
		$opts = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel();
		$keys = $this->getModule()->optionKeys();
		$post = WooBeWoo_PF_Req::getVar( 'opts', 'post' );
		if ( ! is_array( $post ) ) {
			$post = array();
		}
		$values = array();
		foreach ( $keys as $key => $default ) {
			if ( 'wbw_bot_limit_per_min' === $key ) {
				$values[ $key ] = isset( $post[ $key ] ) ? (string) max( 1, min( 600, (int) $post[ $key ] ) ) : $default;
			} else {
				$values[ $key ] = ( isset( $post[ $key ] ) && '1' === (string) $post[ $key ] ) ? '1' : '0';
			}
		}
		$values['wbw_speed_saved'] = (string) time();
		$opts->woobewoo_pf_save_group( array( 'opt_values' => $values ) );
		$this->getModule()->bumpCacheSalt();
		$res->addMessage( esc_html__( 'Saved.', 'woo-product-filter' ) );
		return $res->ajaxExec();
	}

	/**
	 * Save one switch or value (the screen saves as you change).
	 *
	 * @since 4.0.0
	 */
	public function woobewoo_pf_speed_save_one() {
		WooBeWoo_PF_Req::verifyRequest();
		$res  = new WooBeWoo_PF_Response();
		$key  = sanitize_key( (string) WooBeWoo_PF_Req::getVar( 'key', 'post' ) );
		$val  = (string) WooBeWoo_PF_Req::getVar( 'value', 'post' );
		$keys = $this->getModule()->optionKeys();
		if ( ! isset( $keys[ $key ] ) ) {
			$res->pushError( esc_html__( 'Unknown setting.', 'woo-product-filter' ) );
			return $res->ajaxExec();
		}
		if ( 'wbw_bot_limit_per_min' === $key ) {
			$val = (string) max( 1, min( 600, (int) $val ) );
		} elseif ( 'wbw_support_email' === $key ) {
			$val = sanitize_email( $val );
		} else {
			$val = '1' === $val ? '1' : '0';
		}
		WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->woobewoo_pf_save_group( array( 'opt_values' => array( $key => $val, 'wbw_speed_saved' => (string) time() ) ) );
		$this->getModule()->bumpCacheSalt();
		// The 4.0 engine caches whole answers (links included): clean links, canonical or bot rules must not be
		// served from the old ones.
		if ( class_exists( 'WooBeWoo_PF_Index' ) ) {
			WooBeWoo_PF_Index::bump_version();
		}
		$res->addMessage( esc_html__( 'Saved.', 'woo-product-filter' ) );
		$res->addData( 'robots', $this->getModule()->robotsRules() );
		return $res->ajaxExec();
	}

	/**
	 * Save one legacy option from the Advanced settings card.
	 *
	 * @since 4.0.0
	 */
	public function woobewoo_pf_speed_save_legacy() {
		WooBeWoo_PF_Req::verifyRequest();
		$res  = new WooBeWoo_PF_Response();
		$key  = sanitize_key( (string) WooBeWoo_PF_Req::getVar( 'key', 'post' ) );
		$val  = sanitize_text_field( (string) WooBeWoo_PF_Req::getVar( 'value', 'post' ) );
		$all  = WooBeWoo_PF_Frame::_()->getModule( 'options' )->getAll();
		$def  = null;
		foreach ( $all as $cat ) {
			if ( isset( $cat['opts'][ $key ] ) ) {
				$def = $cat['opts'][ $key ];
			}
		}
		if ( ! $def ) {
			$res->pushError( esc_html__( 'Unknown setting.', 'woo-product-filter' ) );
			return $res->ajaxExec();
		}
		if ( 'checkboxHiddenVal' === $def['html'] ) {
			$val = '1' === $val ? '1' : '0';
		}
		WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->woobewoo_pf_save_group( array( 'opt_values' => array( $key => $val ) ) );
		$res->addMessage( esc_html__( 'Saved.', 'woo-product-filter' ) );
		return $res->ajaxExec();
	}

	/**
	 * Send a support ticket (wp_mail) and keep a copy.
	 *
	 * @since 4.0.0
	 */
	public function woobewoo_pf_speed_ticket() {
		WooBeWoo_PF_Req::verifyRequest();
		$res     = new WooBeWoo_PF_Response();
		$subject = (string) WooBeWoo_PF_Req::getVar( 'subject', 'post' );
		$message = (string) WooBeWoo_PF_Req::getVar( 'message', 'post' );
		$email   = (string) WooBeWoo_PF_Req::getVar( 'email', 'post' );
		$to      = (string) WooBeWoo_PF_Req::getVar( 'to', 'post' );
		if ( '' === trim( $subject ) || '' === trim( $message ) || ! is_email( $email ) ) {
			$res->pushError( esc_html__( 'Fill in the subject, the message and your email.', 'woo-product-filter' ) );
			return $res->ajaxExec();
		}
		if ( is_email( $to ) ) {
			WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->woobewoo_pf_save_group( array( 'opt_values' => array( 'wbw_support_email' => sanitize_email( $to ) ) ) );
		}
		$sent = $this->getModule()->sendTicket( $subject, $message, $email, $to, '1' === (string) WooBeWoo_PF_Req::getVar( 'attach', 'post' ) );
		if ( $sent ) {
			$res->addMessage( esc_html__( 'Sent. Pro: first reply within one business day. A copy is kept here.', 'woo-product-filter' ) );
		} else {
			$res->addMessage( esc_html__( 'Saved here, but your site could not send email. Paste the diagnostics into a forum post instead.', 'woo-product-filter' ) );
		}
		$res->addData( 'sent', $sent );
		return $res->ajaxExec();
	}

	/**
	 * Accessibility report for one filter (engine endpoint woobewoo_pf_a11y_report, else a
	 * markup check of the rendered filter).
	 *
	 * @since 4.0.0
	 */
	public function woobewoo_pf_speed_a11y() {
		WooBeWoo_PF_Req::verifyRequest();
		$res = new WooBeWoo_PF_Response();
		$id  = absint( WooBeWoo_PF_Req::getVar( 'filter_id', 'post' ) );
		$res->addData( 'rows', $this->getModule()->a11yReport( $id ) );
		return $res->ajaxExec();
	}

	/**
	 * Clear the counts cache now.
	 */
	public function woobewoo_pf_speed_clear_cache() {
		WooBeWoo_PF_Req::verifyRequest();
		$res = new WooBeWoo_PF_Response();
		$this->getModule()->bumpCacheSalt();
		// The 4.0 engine keeps its own saved counts and results: start them fresh too.
		if ( class_exists( 'WooBeWoo_PF_Index' ) && method_exists( 'WooBeWoo_PF_Index', 'bump_version' ) ) {
			WooBeWoo_PF_Index::bump_version();
		}
		$res->addMessage( esc_html__( 'Saved counts cleared. Shoppers get fresh counts from the next page they open.', 'woo-product-filter' ) );
		return $res->ajaxExec();
	}

	public function getPermissions() {
		return array(
			WPF_METHODS => array(
				'woobewoo_pf_speed_index_status' => array( WPF_ADMIN ),
				'woobewoo_pf_speed_rebuild'      => array( WPF_ADMIN ),
				'woobewoo_pf_speed_save'         => array( WPF_ADMIN ),
				'woobewoo_pf_speed_clear_cache'  => array( WPF_ADMIN ),
				'woobewoo_pf_speed_save_one'     => array( WPF_ADMIN ),
				'woobewoo_pf_speed_save_legacy'  => array( WPF_ADMIN ),
				'woobewoo_pf_speed_ticket'       => array( WPF_ADMIN ),
				'woobewoo_pf_speed_a11y'         => array( WPF_ADMIN ),
			),
		);
	}
}
