<?php
/**
 * Product Filter by WBW - TableModules_TypeWpf Class
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Modules_Type_Table extends WooBeWoo_PF_Table {

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
