<?php
class TableMeta_KeysWpf extends TableWpf {
	public function __construct() {
		$this->_table = '@__meta_keys';
		$this->_id = 'id';
		$this->_alias = 'wpf_meta_keys';
		$this->_addField('id', 'text', 'int')
			 ->_addField('meta_mode', 'text', 'int')
			 ->_addField('meta_key', 'text', 'text')
			 ->_addField('taxonomy', 'text', 'text')
			 ->_addField('meta_like', 'text', 'int')
			 ->_addField('parent', 'text', 'int')
			 ->_addField('meta_type', 'text', 'int')
			 ->_addField('status', 'text', 'int')
			 ->_addField('added', 'text', 'text')
			 ->_addField('updated', 'text', 'text')
			 ->_addField('locked', 'text', 'text')
			 ->_addField('calculated', 'text', 'text');
	}
}
