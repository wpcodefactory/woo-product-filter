<?php
class Meta_KeysModelWpf extends ModelWpf {
	// meta_mode: 0-global, 1-filter
	// meta_type: 0-text, 1-decimal, 2-int, 3-decimal+int, 7-array json, 8-serialised array, 9-list, 5-list with custom separator
	// key_size: for array count keys
	// value_size: for text max lenght
	// status: 1-calculated (global), 0-need calc, 2- lock, 9-don't recalc

	public $lockLimit = 10; //minutes

	public function __construct() {
		$this->_setTbl('meta_keys');
	}

	public function getAllKeys( $params = array() ) {
		if (!empty($params)) {
			$this->addWhere($params);
		}
		$data = $this->addWhere('meta_like=0')->getFromTbl();
		$keys = array();
		foreach ($data as $fields) {
			$key = $fields['meta_key'];
			$keys[$key] = $fields;
			$typ = $fields['meta_type'];
			$keys[$key]['field'] = ( 1 == $typ ? 'dec' : ( 2 == $typ || 3 == $typ ? 'int' : 'id' ) );
		}
		return $keys;
	}

	public function getKeysWithCalcControl( $params = array()) {
		if (FrameWpf::_()->getModule('options')->getModel()->get('start_indexing') == 2) {
			return array();
		}
		return $this->getAllKeys($params);
	}

	public function isOldLock( $lock ) {
		return is_null($lock) || empty($lock) || $lock > $this->lockLimit;
	}

	public function resetLockedKeys() {
		$query = 'UPDATE @__meta_keys SET status=0, updated=CURRENT_TIMESTAMP WHERE status=2 AND TIMESTAMPDIFF(MINUTE, locked, CURRENT_TIMESTAMP)>' . $this->lockLimit;
		if (!DbWpf::query($query)) { 
			$this->pushError(DbWpf::getError());
			return false;
		}
		return true;
	}

	public function getKeysForRecalc( $params ) {
		// $params['key_ids'] - array of ids

		$where = 'status!=9';
		if (!empty($params)) {
			foreach ($params as $key => $value) {
				$where .= ' AND ' . $key . ( is_array($value) ? " IN ('" . implode("','", $value) . "')" : "='" . $value . "'" );
			}
		}
		$keys = $this->addWhere($where)->setOrderBy('id')->getFromTbl();

		return $keys;
	}

	public function getKeyData( $key, $calcLock = false ) {
		$select = '*';
		if ($calcLock) {
			$select .= ', TIMESTAMPDIFF(MINUTE, locked, CURRENT_TIMESTAMP) as lock_duration';
		}
		return $this->setSelectFields($select)->addWhere(array('meta_key' => $key))->getFromTbl(array('return' => 'row'));
	}

	public function saveKeyData( $data ) {
		unset($data['id'], $data['added'], $data['calculated']);
		$data['status'] = 0;
		FrameWpf::_()->getModule('woofilters')->resetMetaKeys();
		return $this->insert( $data );
	}

	public function updateKeyData( $id, $data ) {
		$now = DbWpf::get('SELECT CURRENT_TIMESTAMP', 'one');
		if (!$now) {
			$this->pushError(DbWpf::getError());
			return false;
		}
		$data['updated'] = $now;
		
		if (isset($data['status'])) {
			if (1 == $data['status']) {	
				$data['calculated'] = $now;
			} else if (2 == $data['status']) {	
				$data['locked'] = $now;
			}
			FrameWpf::_()->getModule('woofilters')->resetMetaKeys();
		}
		if (!$this->updateById($data, $id)) {
			return false;
		}
		return true;
	}

	public function addFilterMetaKeys( $filterKeys, $remove = false ) {
		if (!is_array($filterKeys)) {
			return false;
		}
		$filterKeys = array_unique($filterKeys);
		$valuesMeta = FrameWpf::_()->getModule('meta')->getModel('meta_values');

		$allKeys = $this->getAllKeys();
		$keyIds = array();

		foreach ($filterKeys as $key) {
			if (isset($allKeys[$key])) {
				$data = $allKeys[$key];
				if (9 == $data['status']) {
					if (!$this->updateKeyData($data['id'], array('status' => 0))) {
						return false;
					}
					$keyIds[] = $data['id'];
				}
			} else if (!$this->insert(array('meta_key' => $key, 'meta_mode' => 1, 'status' => 0))) {
				return false;
			}
		}
		if (!empty($keyIds)) {
			$valuesMeta->restoreOldValues($keyIds);
			$keyIds = array();
		}
		
		if ($remove) {
			foreach ($allKeys as $key => $data) {
				if ( ( 1 == $data['meta_mode'] ) && ( 9 != $data['status'] ) && !in_array($key, $filterKeys) ) {
					if (!$this->updateKeyData($data['id'], array('status' => 9))) {
						return false;
					}
					$keyIds[] = $data['id'];
				}
			}
			if (!empty($keyIds)) {
				$valuesMeta->backupOldValues($keyIds);
			}
		}
		FrameWpf::_()->getModule('woofilters')->resetMetaKeys();
		return true;
	}
	public function controlFiltersMetaKeys( $deep = false ) {
		$filtersModel = FrameWpf::_()->getModule('woofilters')->getModel();
		$filterKeys = $filtersModel->getFiltersMetaKeys(0, $deep);
		if (false === $filterKeys) {
			$this->pushError($filtersModel->getErrors());
			return false;
		}
		return $this->addFilterMetaKeys($filterKeys, true);
	}
}
