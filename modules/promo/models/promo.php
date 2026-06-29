<?php
/**
 * Product Filter by WBW - PromoModelWpf Class
 *
 * @version 3.1.9
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class PromoModelWpf extends ModelWpf {

	/**
	 * _apiUrl.
	 */
	private $_apiUrl = '';

	/**
	 * _bigCli.
	 */
	private $_bigCli = null;

	/**
	 * _getApiUrl.
	 */
	private function _getApiUrl() {
		if (empty($this->_apiUrl)) {
			$this->_initApiUrl();
		}
		return $this->_apiUrl;
	}

	/**
	 * welcomePageSaveInfo.
	 *
	 * @version 3.1.9
	 */
	public function welcomePageSaveInfo( $d = array() ) {
		return;
	}

	/**
	 * saveUsageStat.
	 */
	public function saveUsageStat( $code, $unique = false ) {
		return; // Nothing todo for now
		if ($unique && $this->_checkUniqueStat($code)) {
			return;
		}
		$query = 'INSERT INTO @__usage_stat SET code = "' . DbWpf::escape($code) . '", visits = 1
			ON DUPLICATE KEY UPDATE visits = visits + 1';
		return DbWpf::query($query);
	}

	/**
	 * _checkUniqueStat.
	 */
	private function _checkUniqueStat( $code ) {
		$uniqueStats = get_option(WPF_CODE . '_unique_stats');
		if (empty($uniqueStats)) {
			$uniqueStats = array();
		}
		if (in_array($code, $uniqueStats)) {
			return true;
		}
		$uniqueStats[] = $code;
		update_option(WPF_CODE . '_unique_stats', $uniqueStats);
		return false;
	}

	/**
	 * saveSpentTime.
	 */
	public function saveSpentTime( $code, $spent ) {
		$spent = (int) $spent;
		$query = 'UPDATE @__usage_stat SET spent_time = spent_time + ' . $spent . ' WHERE code = "' . $code . '"';
		return DbWpf::query($query);
	}

	/**
	 * getAllUsageStat.
	 */
	public function getAllUsageStat() {
		$query = 'SELECT * FROM @__usage_stat';
		return DbWpf::get($query);
	}

	/**
	 * sendUsageStat.
	 *
	 * @version 3.1.9
	 */
	public function sendUsageStat() {
		return;
	}

	/**
	 * clearUsageStat.
	 */
	public function clearUsageStat() {
		$query = 'DELETE FROM @__usage_stat';
		return DbWpf::query($query);
	}

	/**
	 * getUserStatsCount.
	 */
	public function getUserStatsCount() {
		$query = 'SELECT SUM(visits) AS total FROM @__usage_stat';
		return (int) DbWpf::get($query, 'one');
	}

	/**
	 * checkAndSend.
	 */
	public function checkAndSend( $force = false ) {
		return; // Nothing todo for now
		$statCount = $this->getUserStatsCount();
		if ($statCount >= $this->getModule()->getMinStatSend() || $force) {
			$this->sendUsageStat();
		}
	}

	/**
	 * _initApiUrl.
	 */
	protected function _initApiUrl() {
		$this->_apiUrl = implode('', array('', 'h', 't', 'tp', ':', '/', '/u', 'p', 'da', 't', 'e', 's.', 's', 'u', 'ps', 'y', 'st', 'i', 'c.', 'c', 'o', 'm'));
	}

	/**
	 * getTourHst.
	 */
	public function getTourHst() {
		$hst = get_user_meta(get_current_user_id(), WPF_CODE . '-tour-hst', true);
		if (empty($hst)) {
			$hst = array();
		}
		if (!isset($hst['passed'])) {
			$hst['passed'] = array();
		}
		return $hst;
	}

	/**
	 * setTourHst.
	 */
	public function setTourHst( $hst ) {
		update_user_meta(get_current_user_id(), WPF_CODE . '-tour-hst', $hst);
	}

	/**
	 * clearTourHst.
	 */
	public function clearTourHst() {
		delete_user_meta(get_current_user_id(), WPF_CODE . '-tour-hst');
	}

	/**
	 * addTourStep.
	 */
	public function addTourStep( $d = array() ) {
		$hst = $this->getTourHst();
		$pointKey = $d['tourId'] . '-' . $d['pointId'];
		$hst['passed'][ $pointKey ] = 1;
		$this->setTourHst( $hst );
		$this->saveUsageStat('tour_pass_' . $pointKey);
	}

	/**
	 * closeTour.
	 */
	public function closeTour( $d = array() ) {
		$hst = $this->getTourHst();
		$pointKey = $d['tourId'] . '-' . $d['pointId'];
		$hst['closed'] = 1;
		$this->setTourHst( $hst );
		$this->saveUsageStat('tour_closed_on_' . $pointKey);
	}

	/**
	 * addTourFinish.
	 */
	public function addTourFinish( $d = array() ) {
		$hst = $this->getTourHst();
		$pointKey = $d['tourId'] . '-' . $d['pointId'];
		$hst['finished'] = 1;
		$this->setTourHst( $hst );
		$this->saveUsageStat('tour_finished_on_' . $pointKey);
	}

	/**
	 * _getBigStatClient.
	 *
	 * @version 3.1.9
	 */
	private function _getBigStatClient() {
		if (!$this->_bigCli) {
			if (!class_exists('Mixpanel')) {
				require_once($this->getModule()->getModDir() . 'models' . WPF_DS . 'classes' . WPF_DS . 'lib' . WPF_DS . 'Mixpanel.php');
			}
			$opts = array();
			if (!function_exists('curl_init')) {
				$opts['consumer'] = 'socket';
			}
			if (class_exists('Mixpanel')) {
				$this->_bigCli = Mixpanel::getInstance('f2d1696c52737908fa4ecc471e88fa47', $opts);
			}
		}
		return $this->_bigCli;
	}

	/**
	 * bigStatAdd.
	 */
	public function bigStatAdd( $key, $properties = array() ) {
		if (function_exists('json_encode')) {
			$this->_getBigStatClient();
			if ($this->_bigCli) {
				$this->_bigCli->track( $key, $properties );
			}
		}
	}

	/**
	 * bigStatAddCheck.
	 */
	public function bigStatAddCheck( $key, $properties = array() ) {
		$canSend = (int) FrameWpf::_()->getModule('options')->get('send_stats');
		if ($canSend) {
			$this->bigStatAdd( $key, $properties );
		}
	}

	/**
	 * saveDeactivateData.
	 */
	public function saveDeactivateData( $d ) {
		$deactivateParams = array();
		$reasonsLabels = array(
			'not_working'  => esc_attr__( 'Not working', 'woo-product-filter' ),
			'found_better' => esc_attr__( 'Found better', 'woo-product-filter' ),
			'not_need'     => esc_attr__( 'Not need', 'woo-product-filter' ),
			'temporary'    => esc_attr__( 'Temporary', 'woo-product-filter' ),
			'other'        => esc_attr__( 'Other', 'woo-product-filter' ),
		);
		$deactivateParams['Reason'] = isset($d['deactivate_reason']) && $d['deactivate_reason'] ? $reasonsLabels[ $d['deactivate_reason'] ] : esc_attr__( 'No reason', 'woo-product-filter' );
		if (isset($d['deactivate_reason']) && $d['deactivate_reason']) {
			switch ($d['deactivate_reason']) {
				case 'found_better':
					$deactivateParams['Better plugin'] = $d['better_plugin'];
					break;
				case 'other':
					$deactivateParams['Other'] = $d['other'];
					break;
			}
		}
		$this->bigStatAdd('Deactivated', $deactivateParams);
		$startUsage = (int) FrameWpf::_()->getModule('options')->get('plug_welcome_show');
		if ($startUsage) {
			$usedTime = time() - $startUsage;
			$this->bigStatAdd('Used Time', array(
				'Seconds' => $usedTime,
				'Hours'   => round($usedTime / 60 / 60),
				'Days'    => round($usedTime / 60 / 60 / 24),
			));
		}
		return true;
	}

}
