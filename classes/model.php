<?php
/**
 * Product Filter by WBW - ModelWpf Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;
abstract class ModelWpf extends WooBeWoo_PF_Base_Object {
	protected $_data = array();
	protected $_code = '';

	protected $_orderBy      = '';
	protected $_sortOrder    = '';
	protected $_groupBy      = '';
	protected $_limit        = '';
	protected $_where        = array();
	protected $_stringWhere  = '';
	protected $_selectFields = '*';
	protected $_tbl          = '';
	protected $_lastGetCount = 0;
	protected $_idField      = 'id';
	protected $_indexes      = array();

	public function setCode( $code ) {
		$this->_code = $code;
	}
	public function getCode() {
		return $this->_code;
	}

	/**
	 * isEnableStatistics.
	 *
	 * @version 3.3.2
	 */
	public function getModule() {
		return WooBeWoo_PF_Frame::_()->getModule( $this->_code );
	}

	protected function _setTbl( $tbl ) {
		$this->_tbl = $tbl;
	}
	public function setOrderBy( $orderBy ) {
		$this->_orderBy = $orderBy;
		return $this;
	}
	public function setIndexes( $indexes ) {
		$this->_indexes = $indexes;
	}
	/**
	 * ASC, DESC
	 */
	public function setSortOrder( $sortOrder ) {
		$this->_sortOrder = $sortOrder;
		return $this;
	}
	public function setLimit( $limit ) {
		$this->_limit = $limit;
		return $this;
	}
	public function setWhere( $where ) {
		$this->_where = $where;
		return $this;
	}
	public function addWhere( $where ) {
		if ( empty( $this->_where ) && ! is_string( $where ) ) {
			$this->setWhere( $where );
		} elseif ( is_array( $this->_where ) && is_array( $where ) ) {
			$this->_where = array_merge( $this->_where, $where );
		} elseif ( is_string( $where ) ) {
			if ( ! isset( $this->_where['additionalCondition'] ) ) {
				$this->_where['additionalCondition'] = '';
			}
			if ( ! empty( $this->_where['additionalCondition'] ) ) {
				$this->_where['additionalCondition'] .= ' AND ';
			}
			$this->_where['additionalCondition'] .= $where;
		}
		return $this;
	}
	public function setSelectFields( $selectFields ) {
		if ( is_array( $selectFields ) ) {
			$selectFields = implode( ',', $selectFields );
		}
		$this->_selectFields = $selectFields;
		return $this;
	}
	public function groupBy( $groupBy ) {
		$this->_groupBy = $groupBy;
		return $this;
	}
	public function getLastGetCount() {
		return $this->_lastGetCount;
	}

	/**
	 * isEnableStatistics.
	 *
	 * @version 3.3.2
	 */
	public function getFromTbl( $params = array() ) {
		$this->_lastGetCount = 0;
		$tbl                 = isset( $params['tbl'] ) ? $params['tbl'] : $this->_tbl;
		$table               = WooBeWoo_PF_Frame::_()->getTable( $tbl );
		$this->_buildQuery( $table );
		$return = isset( $params['return'] ) ? $params['return'] : 'all';
		$data   = $table->get( $this->_selectFields, $this->_where, '', $return );
		if ( ! empty( $data ) ) {
			switch ( $return ) {
				case 'one':
					$this->_lastGetCount = 1;
					break;
				case 'row':
					$data                = $this->_afterGetFromTbl( $data );
					$this->_lastGetCount = 1;
					break;
				default:
					foreach ( $data as $i => $row ) {
						$data[ $i ] = $this->_afterGetFromTbl( $row );
					}
					$this->_lastGetCount = count( $data );
					break;
			}
		}
		$this->_clearQuery( $params );
		return $data;
	}
	protected function _clearQuery( $params = array() ) {
		$clear = isset( $params['clear'] ) ? $params['clear'] : array();
		if ( ! is_array( $clear ) ) {
			$clear = array( $clear );
		}
		if ( empty( $clear ) || in_array( 'limit', $clear ) ) {
			$this->_limit = '';
		}
		if ( empty( $clear ) || in_array( 'orderBy', $clear ) ) {
			$this->_orderBy = '';
		}
		if ( empty( $clear ) || in_array( 'sortOrder', $clear ) ) {
			$this->_sortOrder = '';
		}
		if ( empty( $clear ) || in_array( 'where', $clear ) ) {
			$this->_where = array();
		}
		if ( empty( $clear ) || in_array( 'selectFields', $clear ) ) {
			$this->_selectFields = '*';
		}
		if ( empty( $clear ) || in_array( 'groupBy', $clear ) ) {
			$this->_groupBy = '';
		}
	}

	/**
	 * getCount.
	 *
	 * @version 3.3.2
	 */
	public function getCount( $params = array() ) {
		$tbl   = isset( $params['tbl'] ) ? $params['tbl'] : $this->_tbl;
		$table = WooBeWoo_PF_Frame::_()->getTable( $tbl );
		$this->setSelectFields( 'COUNT(*) AS total' );
		$this->_buildQuery( $table );
		$data = (int) $table->get( $this->_selectFields, $this->_where, '', 'one' );
		$this->_clearQuery( $params );
		return $data;
	}
	protected function _afterGetFromTbl( $row ) {
		// You can re-define this method in your own model
		return $row;
	}

	/**
	 * _buildQuery.
	 *
	 * @version 3.3.2
	 */
	protected function _buildQuery( $table = null ) {
		if ( ! $table ) {
			$table = WooBeWoo_PF_Frame::_()->getTable( $this->_tbl );
		}
		if ( ! empty( $this->_orderBy ) ) {
			$order = $this->_orderBy;
			if ( ! empty( $this->_sortOrder ) ) {
				$order .= ' ' . strtoupper( $this->_sortOrder );
			}
			$table->orderBy( $order );
		}
		if ( ! empty( $this->_groupBy ) ) {
			$table->groupBy( $this->_groupBy );
		}
		if ( ! empty( $this->_limit ) ) {
			$table->setLimit( $this->_limit );
		}
	}

	/**
	 * woobewoo_pf_remove_group.
	 *
	 * @version 3.3.2
	 */
	public function woobewoo_pf_remove_group( $ids ) {
		if ( ! is_array( $ids ) ) {
			$ids = array( $ids );
		}
		// Remove all empty values
		$ids = array_filter( array_map( 'intval', $ids ) );
		if ( ! empty( $ids ) ) {
			if ( WooBeWoo_PF_Frame::_()->getTable( $this->_tbl )->delete( array( 'additionalCondition' => 'id IN (' . implode( ',', $ids ) . ')' ) ) ) {
				return true;
			} else {
				$this->pushError( esc_html__( 'Database error detected', 'woo-product-filter' ) );
			}
		} else {
			$this->pushError( esc_html__( 'Invalid ID', 'woo-product-filter' ) );
		}
		return false;
	}
	public function clear() {
		return $this->delete(); // Just delete all
	}

	/**
	 * delete.
	 *
	 * @version 3.3.2
	 */
	public function delete( $params = array() ) {
		if ( WooBeWoo_PF_Frame::_()->getTable( $this->_tbl )->delete( $params ) ) {
			return true;
		} else {
			$this->pushError( esc_html__( 'Database error detected', 'woo-product-filter' ) );
		}
		return false;
	}
	public function getById( $id ) {
		$data = $this->setWhere( array( $this->_idField => $id ) )->getFromTbl();
		return empty( $data ) ? false : array_shift( $data );
	}

	/**
	 * insert.
	 *
	 * @version 3.3.2
	 */
	public function insert( $data ) {
		$data = $this->_dataSave( $data, false );
		$id   = WooBeWoo_PF_Frame::_()->getTable( $this->_tbl )->insert( $data );
		if ( $id ) {
			return $id;
		}
		$this->pushError( WooBeWoo_PF_Frame::_()->getTable( $this->_tbl )->getErrors() );
		return false;
	}
	public function updateById( $data, $id = 0 ) {
		if ( ! $id ) {
			$id = isset( $data[ $this->_idField ] ) ? (int) $data[ $this->_idField ] : 0;
		}
		if ( $id ) {
			return $this->update( $data, array( $this->_idField => $id ) );
		} else {
			$this->pushError( esc_html__( 'Empty or invalid ID', 'woo-product-filter' ) );
		}
		return false;
	}

	/**
	 * update.
	 *
	 * @version 3.3.2
	 */
	public function update( $data, $where ) {
		$data = $this->_dataSave( $data, true );
		if ( WooBeWoo_PF_Frame::_()->getTable( $this->_tbl )->update( $data, $where ) ) {
			return true;
		}
		$this->pushError( WooBeWoo_PF_Frame::_()->getTable( $this->_tbl )->getErrors() );
		return false;
	}
	protected function _dataSave( $data, $update = false ) {
		return $data;
	}
	public function getTbl() {
		return $this->_tbl;
	}
	/**
	 * We can re-define this method to not retrive all data - for simple tables
	 */
	public function setSimpleGetFields() {
		return $this;
	}

	/**
	 * dropIndexes.
	 *
	 * @version 3.3.2
	 */
	public function dropIndexes( $withPrimary = false ) {
		$table   = WooBeWoo_PF_Db::sanitizeIdentifier( $this->_tbl );
		$indexes = WooBeWoo_PF_Db::get( 'SHOW INDEX FROM `@__' . $table . '`' );
		if ( ! $indexes ) {
			$this->pushError( WooBeWoo_PF_Db::getError() );
			return false;
		}

		$drop = array();
		foreach ( $indexes as $index ) {
			$name = WooBeWoo_PF_Db::sanitizeIdentifier( $index['Key_name'] );
			if ( $withPrimary || 'PRIMARY' != $name ) {
				$drop[] = ' DROP INDEX `' . $name . '`';
			}
		}
		if ( ! empty( $drop ) ) {
			if ( ! WooBeWoo_PF_Db::query( 'ALTER TABLE `@__' . $table . '`' . implode( ',', array_unique( $drop ) ) ) ) {
				$this->pushError( WooBeWoo_PF_Db::getError() );
				return false;
			}
		}
		return true;
	}

	/**
	 * addIndexes.
	 *
	 * @version 3.3.2
	 */
	public function addIndexes( $delete = true ) {
		if ( empty( $this->_indexes ) ) {
			return true;
		}
		$table   = WooBeWoo_PF_Db::sanitizeIdentifier( $this->_tbl );
		$indexes = WooBeWoo_PF_Db::get( 'SHOW INDEX FROM `@__' . $table . '`' );
		if ( ! $indexes ) {
			$this->pushError( WooBeWoo_PF_Db::getError() );
			return false;
		}
		$exists = array();
		foreach ( $indexes as $index ) {
			$exists[] = WooBeWoo_PF_Db::sanitizeIdentifier( $index['Key_name'] );
		}

		$alter = '';
		foreach ( $this->_indexes as $name => $index ) {
			if ( ! in_array( $name, $exists ) ) {
				$alter .= ' ADD ' . $index . ',';
			}
		}
		if ( ! empty( $alter ) ) {
			if ( ! WooBeWoo_PF_Db::query( 'ALTER TABLE `@__' . $table . '`' . substr( $alter, 0, -1 ) ) ) {
				$this->pushError( WooBeWoo_PF_Db::getError() );
				if ( $delete ) {
					$this->delete();
				}
				return false;
			}
		}
		return true;
	}
}
