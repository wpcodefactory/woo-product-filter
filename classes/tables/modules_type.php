<?php
/**
 * Product Filter by WBW - TableModules_TypeWpf Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class TableModules_TypeWpf extends WooBeWoo_PF_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->_table = '@__modules_type';
		$this->_id    = 'id'; // Let's associate it with posts
		$this->_alias = 'sup_m_t';
		$this->_addField( $this->_id, 'text', 'int', '', 'ID' )
			->_addField( 'label', 'text', 'varchar', '', 'Label', 128 );
	}
}
