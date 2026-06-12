<?php
/**
 * Product Filter by WBW - ConsumerStrategies_AbstractConsumer Class
 *
 * Provides some base methods for use by a Consumer implementation.
 *
 * @version 3.1.8
 */

defined( 'ABSPATH' ) || exit;

require_once(dirname(__FILE__) . '/../Base/MixpanelBase.php');

abstract class ConsumerStrategies_AbstractConsumer extends Base_MixpanelBase {

	/**
	 * Creates a new AbstractConsumer.
	 *
	 * @param array $options
	 */
	public function __construct( $options = array() ) {

		parent::__construct($options);

		if ($this->_debug()) {
			$this->_log('Instantiated new Consumer');
		}
	}

	/**
	 * Encode an array to be persisted.
	 *
	 * @param array $params
	 *
	 * @return string
	 */
	protected function _encode( $params ) {
		return base64_encode(json_encode($params));
	}

	/**
	 * Handles errors that occur in a consumer.
	 *
	 * @param $code
	 * @param $msg
	 */
	protected function _handleError( $code, $msg ) {
	}

	/**
	 * Persist a batch of messages in whatever way the implementer sees fit.
	 *
	 * @param array $batch an array of messages to consume
	 *
	 * @return boolean success or fail
	 */
	abstract public function persist( $batch );
}
