<?php
class ModulesModelWpf extends ModelWpf {
	public function __construct() {
		$this->_setTbl('modules');
	}
	public function get( $d = array() ) {
		if (isset($d['id']) && $d['id'] && is_numeric($d['id'])) {
			$fields = FrameWpf::_()->getTable('modules')->fillFromDB($d['id'])->getFields();
			$fields['types'] = array();
			$types = FrameWpf::_()->getTable('modules_type')->fillFromDB();
			foreach ($types as $t) {
				$fields['types'][$t['id']->value] = $t['label']->value;
			}
			return $fields;
		} elseif (!empty($d)) {
			$data = FrameWpf::_()->getTable('modules')->get('*', $d);
			return $data;
		} else {
			return FrameWpf::_()->getTable('modules')
				->innerJoin(FrameWpf::_()->getTable('modules_type'), 'type_id')
				->getAll(FrameWpf::_()->getTable('modules')->alias() . '.*, ' . FrameWpf::_()->getTable('modules_type')->alias() . '.label as type');
		}
	}
	public function put( $d = array() ) {
		$res = new ResponseWpf();
		$id = $this->_getIDFromReq($d);
		$d = prepareParamsWpf($d);
		if (is_numeric($id) && $id) {
			if (isset($d['active'])) {
				$d['active'] = ( ( is_string($d['active']) && 'true' == $d['active'] ) || 1 == $d['active'] ) ? 1 : 0;           //mmm.... govnokod?....)))
			}		
			if (FrameWpf::_()->getTable('modules')->update($d, array('id' => $id))) {
				$res->messages[] = esc_html__('Module Updated', 'woo-product-filter');
				$mod = FrameWpf::_()->getTable('modules')->getById($id);
				$newType = FrameWpf::_()->getTable('modules_type')->getById($mod['type_id'], 'label');
				$newType = $newType['label'];
				$res->data = array(
					'id' => $id, 
					'label' => $mod['label'], 
					'code' => $mod['code'], 
					'type' => $newType,
					'active' => $mod['active'], 
				);
			} else {
				$tableErrors = FrameWpf::_()->getTable('modules')->getErrors();
				if ($tableErrors) {
					$res->errors = array_merge($res->errors, $tableErrors);
				} else {
					$res->errors[] = esc_html__('Module Update Failed', 'woo-product-filter');
				}
			}
		} else {
			$res->errors[] = esc_html__('Error module ID', 'woo-product-filter');
		}
		return $res;
	}
	protected function _getIDFromReq( $d = array() ) {
		$id = 0;
		if (isset($d['id'])) {
			$id = $d['id'];
		} elseif (isset($d['code'])) {
			$fromDB = $this->get(array('code' => $d['code']));
			if (isset($fromDB[0]) && $fromDB[0]['id']) {
				$id = $fromDB[0]['id'];
			}
		}
		return $id;
	}
}
