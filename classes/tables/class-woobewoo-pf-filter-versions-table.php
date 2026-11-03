<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Filter_Versions_Table Class
 *
 * Version history of filters (one row per saved copy).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Filter_Versions_Table extends WooBeWoo_PF_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->_table = '@__filter_versions';
		$this->_id    = 'id';
		$this->_alias = 'wpf_filter_versions';
		$this->_addField( 'id', 'text', 'int' )
			->_addField( 'filter_id', 'text', 'int' )
			->_addField( 'title', 'text', 'varchar' )
			->_addField( 'setting_data', 'text', 'text' )
			->_addField( 'created', 'text', 'varchar' )
			->_addField( 'user_id', 'text', 'int' );
	}
}
