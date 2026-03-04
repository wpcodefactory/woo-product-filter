<?php
class TableMeta_DataWpf extends TableWpf {
	public function __construct() {
		$this->_table = '@__meta_data';
		$this->_id = 'id';
		$this->_alias = 'wpf_meta_data';
		$this->_addField('id', 'text', 'int')
			 ->_addField('product_id', 'text', 'int')
			 ->_addField('is_var', 'text', 'int')
			 ->_addField('key_id', 'text', 'int')
			 ->_addField('val_int', 'text', 'int')
			 ->_addField('val_dec', 'text', 'decimal')
			 ->_addField('val_id', 'text', 'int')
			 ->_addField('updated', 'text', 'text');
	}
}
