<?php
class TableMeta_ValuesWpf extends TableWpf {
	public function __construct() {
		$this->_table = '@__meta_values';
		$this->_id = 'id';
		$this->_alias = 'wpf_meta_values';
		$this->_addField('id', 'text', 'int')
			 ->_addField('key_id', 'text', 'int')
			 ->_addField('key2', 'text', 'text')
			 ->_addField('key3', 'text', 'text')
			 ->_addField('key4', 'text', 'text')
			 ->_addField('value', 'text', 'text')
			 ->_addField('product_cnt', 'text', 'int')
			 ->_addField('variation_cnt', 'text', 'int');
	}
}
