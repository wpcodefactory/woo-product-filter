<?php
/**
 * Product Filter by WBW - ConsumerStrategies_FileConsumer Class
 *
 * Consumes messages and writes them to a file.
 *
 * @version 3.1.9
 */

defined( 'ABSPATH' ) || exit;

require_once(dirname(__FILE__) . '/AbstractConsumer.php');

class ConsumerStrategies_FileConsumer extends ConsumerStrategies_AbstractConsumer {

	private $_file;

	/**
	 * Creates a new FileConsumer and assigns properties from the $options array.
	 *
	 * @param array $options
	 */
	public function __construct( $options ) {
		parent::__construct($options);

		// what file to write to?
		$this->_file = array_key_exists('file', $options) ? $options['file'] :  dirname(__FILE__) . '/../../messages.txt';
	}

	/**
	 * Append $batch to a file.
	 *
	 * @version 3.1.9
	 *
	 * @param array $batch
	 *
	 * @return bool
	 */
	public function persist( $batch ) {
		if (count($batch) > 0) {
			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . '/wp-admin/includes/file.php';
				WP_Filesystem();
			}
			$existing = $wp_filesystem->exists( $this->_file ) ? $wp_filesystem->get_contents( $this->_file ) : '';
			return $wp_filesystem->put_contents( $this->_file, $existing . json_encode($batch) . "\n", FS_CHMOD_FILE );
		} else {
			return true;
		}
	}
}
