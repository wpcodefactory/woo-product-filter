<?php
/**
 * Product Filter by WBW - TableModules_TypeWpf Class
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

class TableModules_TypeWpf extends TableWpf {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->_table = '@__modules_type';
		$this->_id    = 'id'; // Let's associate it with posts
		$this->_alias = 'sup_m_t';
		$this->_addField($this->_id, 'text', 'int', '', 'ID')
			 ->_addField('label', 'text', 'varchar', '', 'Label', 128);
	}

}
