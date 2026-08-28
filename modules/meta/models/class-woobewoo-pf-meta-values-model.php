<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Meta_Values_Model Class
 *
 * @version 3.4.0
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Meta_Values_Model extends WooBeWoo_PF_Model {

	/**
	 * keyValueIds.
	 */
	private $keyValueIds = array();

	/**
	 * Constructor.
	 *
	 * @version 3.4.0
	 */
	public function __construct() {
		$this->_setTbl( 'meta_values' );
		WooBeWoo_PF_Frame::_()->getTable( 'meta_values' )->setEscape( true );
		$this->setIndexes(
			array(
				'uniq_key' => 'UNIQUE INDEX `uniq_key` (`key_id`, `key2`, `key3`, `key4`, `value`)',
				'i_key'    => 'INDEX `i_key` (key_id, value(10))',
			)
		);
	}

	/**
	 * selectMetaValues.
	 */
	public function selectMetaValues( $keyId ) {
		$this->keyValueIds = $this->getKeyValueIds( $keyId );
	}

	/**
	 * resetMetaValues.
	 */
	public function resetMetaValues() {
		$this->keyValueIds = array();
	}

	/**
	 * getKeyValueIds.
	 *
	 * @version 3.4.0
	 */
	public function getKeyValueIds( $keyId, $keys = array(), $reverse = false ) {
		$this->addIndexes();
		$metaModel  = WooBeWoo_PF_Frame::_()->getModule( 'meta' )->getModel( 'meta' );
		$maxKeySize = $metaModel->maxKeySize;
		$select     = 'id,value';
		$where      = array( 'key_id' => $keyId );
		$uniq       = array();
		for ( $k = $maxKeySize; $k >= 2; $k-- ) {
			$key = 'key' . $k;
			if ( empty( $keys[ $key ] ) ) {
				$select .= ',' . $key;
				$uniq[]  = $key;
			} else {
				$where[ $key ] = $metaModel->getCutKeyValue( $keys[ $key ] );
			}
		}

		$data   = $this->setSelectFields( $select )->addWhere( $where )->getFromTbl();
		$values = array();

		foreach ( $data as $fields ) {
			$key = $fields['value'];
			foreach ( $uniq as $k ) {
				$key = ( empty( $fields[ $k ] ) ? ( $reverse ? '' : '|' ) : $fields[ $k ] . '|' ) . $key;
			}
			$id = (int) $fields['id'];
			if ( $reverse ) {
				$values[ $id ] = $key;
			} else {
				$values[ $key ] = $id;
			}
		}
		return $values;
	}

	/**
	 * getFieldValuesList.
	 *
	 * @version 3.4.0
	 */
	public function getFieldValuesList( $keyId, $field, $keys = array(), $group = false ) {
		$metaModel  = WooBeWoo_PF_Frame::_()->getModule( 'meta' )->getModel( 'meta' );
		$maxKeySize = $metaModel->maxKeySize;
		$where      = array( 'key_id' => $keyId );
		for ( $k = $maxKeySize; $k >= 2; $k-- ) {
			$key = 'key' . $k;
			if ( ! empty( $keys[ $key ] ) ) {
				$where[ $key ] = $metaModel->getCutKeyValue( $keys[ $key ] );
			}
		}
		if ( empty( $keys['ids'] ) ) {
			$this->addWhere( 'product_cnt>0' );
		} else {
			$this->addWhere( 'id IN (' . implode( ',', WooBeWoo_PF_Utils::controlNumericValues( $keys['ids'], 'id' ) ) . ')' );
		}
		$list = $this->setSelectFields( ( $group ? ' DISTINCT ' : '' ) . $field )->addWhere( $where )->getFromTbl( array( 'return' => 'col' ) );
		return empty( $list ) ? array() : $list;
	}

	/**
	 * getMetaValueTerms.
	 *
	 * @version 3.4.0
	 */
	public function getMetaValueTerms( $keyId, $keys = array() ) {
		$cntField = ( empty( $keys['fbv'] ) ? 'product_cnt' : 'variation_cnt' );

		if ( ! isset( $keys['field'] ) || ( 'id' == $keys['field'] ) ) {
			$metaModel  = WooBeWoo_PF_Frame::_()->getModule( 'meta' )->getModel( 'meta' );
			$maxKeySize = $metaModel->maxKeySize;
			$select     = 'id,value,' . $cntField . ' as cnt';
			$where      = array( 'key_id' => $keyId );
			for ( $k = $maxKeySize; $k >= 2; $k-- ) {
				$key = 'key' . $k;
				if ( ! empty( $keys[ $key ] ) ) {
					$where[ $key ] = $metaModel->getCutKeyValue( $keys[ $key ] );
				}
			}

			$cntVariation = ( 'variation_cnt' === $cntField ) ? ' OR variation_cnt > 0' : '';
			$this->addWhere( 'product_cnt > 0' . $cntVariation );

			if ( ! empty( $keys['include'] ) && is_array( $keys['include'] ) ) {
				$this->addWhere( 'id IN (' . implode( ',', WooBeWoo_PF_Utils::controlNumericValues( $keys['include'], 'id' ) ) . ')' );
			}
			if ( isset( $keys['order'] ) ) {
				$this->setOrderBy( 'value' );
				if ( 'desc' == $keys['order'] ) {
					$this->setSortOrder( 'DESC' );
				}
			}
			$data = $this->setSelectFields( $select )->addWhere( $where )->getFromTbl();
		} else {
			$field = $keys['field'];
			$where = ( 'int' === $field ) ? ' AND val_int IS NOT NULL' : '';
			$query = "SELECT val_{$field} as id, val_{$field} as value, count(*) as cnt FROM @__meta_data WHERE key_id={$keyId} {$where} GROUP BY val_{$field}";
			if ( isset( $keys['order'] ) ) {
				$query .= ' ORDER BY value ' . ( 'desc' == $keys['order'] ? 'desc' : 'asc' );
			}
			$data = WooBeWoo_PF_Db::get( $query );
		}
		$terms = array();

		foreach ( $data as $fields ) {
			$term          = new stdClass();
			$term->term_id = $fields['id'];
			$term->name    = $fields['value'];
			$term->slug    = $fields['id'];
			$term->count   = $fields['cnt'];
			$terms[]       = $term;
		}
		return empty( $terms ) ? array() : $terms;
	}

	/**
	 * insertValueId.
	 */
	public function insertValueId( $keyId, $keys, $value ) {
		$value = trim( $value );
		$key   = implode( '|', $keys ) . '|' . $value;
		if ( ! isset( $this->keyValueIds[ $key ] ) ) {
			$id = $this->insert(
				array_merge(
					$keys,
					array(
						'key_id' => $keyId,
						'value'  => $value,
					)
				)
			);
			if ( $id ) {
				$this->keyValueIds[ $key ] = $id;
			} else {
				return false;
			}
		}
		return $this->keyValueIds[ $key ];
	}

	/**
	 * getMetaValueId.
	 */
	public function getMetaValueId( $keyId, $value, $keys = array() ) {
		if ( ! empty( $keys ) ) {
			$this->addWhere( $keys );
		}
		$id = $this->setSelectFields( 'id' )->addWhere(
			array(
				'key_id' => $keyId,
				'value'  => $value,
			)
		)->getFromTbl( array( 'return' => 'one' ) );
		return $id ? $id : 0;
	}

	/**
	 * getMetaValueIds.
	 *
	 * @version 3.4.0
	 *
	 * @param $keyId
	 * @param $values
	 * @param $like
	 * @param $keys
	 *
	 * @return int[]
	 */
	public function getMetaValueIds( $keyId, $values, $like = '', $keys = array() ) {

		if ( is_array( $values ) && ! empty( $values ) ) {

			foreach ( $values as &$value ) {
				if ( false === strpos( $value, '%' ) ) {
					$value = strtolower( rawurlencode( $value ) );
				}
				$value = WooBeWoo_PF_Db::escape( $value );
			}

			$valueStr = implode( "','", $values );

			$this->addWhere( "value in ('" . $valueStr . "')" );
		} elseif ( ! empty( $like ) ) {
			$this->addWhere( 'value' . $like );
		} else {
			return array( 0 );
		}

		if ( ! empty( $keys ) ) {
			$this->addWhere( array( $keys ) );
		}

		$ids = $this->setSelectFields( 'id' )->addWhere( array( 'key_id' => $keyId ) )->getFromTbl( array( 'return' => 'col' ) );

		return empty( $ids ) ? array( 0 ) : $ids;
	}

	/**
	 * recalcValuesCount.
	 *
	 * @version 3.4.0
	 */
	public function recalcValuesCount( $keyIds = array() ) {

		$keyIds = array_map( 'intval', (array) $keyIds );
		$keyIds = array_filter( $keyIds );
		if ( empty( $keyIds ) ) {
			return true;
		}

		$query = 'UPDATE `@__meta_values` as v SET ' .
			' product_cnt=IF(exists(SELECT 1 FROM `@__meta_data` m WHERE m.key_id=v.key_id AND m.val_id=v.id AND m.is_var!=1 LIMIT 1),1,0),
			  variation_cnt=IF(exists(SELECT 1 FROM `@__meta_data` m WHERE m.key_id=v.key_id AND m.val_id=v.id AND m.is_var=1 LIMIT 1),1,0) ';
		if ( ! empty( $keyIds ) ) {
			$query .= ' WHERE v.key_id IN (' . implode( ',', $keyIds ) . ')';
		}
		if ( ! WooBeWoo_PF_Db::query( $query ) ) {
			$this->pushError( WooBeWoo_PF_Db::getError() );
			return false;
		}

		return true;
	}

	/**
	 * backupOldValues.
	 *
	 * @version 3.4.0
	 */
	public function backupOldValues( $keyIds ) {
		$keyIds = array_map( 'intval', (array) $keyIds );
		$keyIds = array_filter( $keyIds );
		if ( empty( $keyIds ) ) {
			return true;
		}
		$where = ' WHERE key_id' . ( count( $keyIds ) ? ' IN (' . implode( ',', $keyIds ) . ')' : '=' . $keyIds[0] );
		$query = 'INSERT IGNORE INTO `@__meta_values_bk` SELECT id, key_id, key2, key3, key4, value FROM `@__meta_values`' . $where;
		if ( ! WooBeWoo_PF_Db::query( $query ) ) {
			$this->pushError( WooBeWoo_PF_Db::getError() );
			return false;
		}
		if ( ! WooBeWoo_PF_Db::query( 'DELETE FROM `@__meta_values`' . $where ) ) {
			$this->pushError( WooBeWoo_PF_Db::getError() );
			return false;
		}
		return true;
	}

	/**
	 * restoreOldValues.
	 *
	 * @version 3.4.0
	 */
	public function restoreOldValues( $keyIds ) {
		$where = ' WHERE key_id' . ( count( $keyIds ) ? ' IN (' . implode( ',', $keyIds ) . ')' : '=' . $keyIds[0] );
		$query = 'INSERT IGNORE INTO `@__meta_values` SELECT id, key_id, key2, key3, key4, value, 0, 0 FROM `@__meta_values_bk`' . $where;
		if ( ! WooBeWoo_PF_Db::query( $query ) ) {
			$this->pushError( WooBeWoo_PF_Db::getError() );
			return false;
		}
		return true;
	}
}
