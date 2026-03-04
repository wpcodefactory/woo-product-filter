<?php
class TableFiltersWpf extends TableWpf {
	public function __construct() {
		$this->_table = '@__filters';
		$this->_id = 'id';
		$this->_alias = 'wpf_filters';
		$this->_addField('id', 'text', 'int')
			 ->_addField('title', 'text', 'varchar')
			 ->_addField('setting_data', 'text', 'text')
			 ->_addField('meta_keys', 'text', 'text');
	}
}
