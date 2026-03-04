<?php
class TableModules_TypeWpf extends TableWpf {
	public function __construct() {
		$this->_table = '@__modules_type';
		$this->_id = 'id';     /*Let's associate it with posts*/
		$this->_alias = 'sup_m_t';
		$this->_addField($this->_id, 'text', 'int', '', 'ID')->
				_addField('label', 'text', 'varchar', '', 'Label', 128);
	}
}
