<?php
class TableUsage_StatWpf extends TableWpf {
	public function __construct() {
		$this->_table = '@__usage_stat';
		$this->_id = 'id';     
		$this->_alias = 'sup_usage_stat';
		$this->_addField('id', 'hidden', 'int', 0, 'id')
			->_addField('code', 'hidden', 'text', 0, 'code')
			->_addField('visits', 'hidden', 'int', 0, 'visits')
			->_addField('spent_time', 'hidden', 'int', 0, 'spent_time')
			->_addField('modify_timestamp', 'hidden', 'int', 0, 'modify_timestamp');
	}
}
