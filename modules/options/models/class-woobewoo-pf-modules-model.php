<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Modules_Model Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Modules_Model extends ModelWpf {
	public function __construct() {
		$this->_setTbl( 'modules' );
	}

	/**
	 * get.
	 *
	 * @version 3.3.2
	 */
	public function get( $d = array() ) {
		if ( isset( $d['id'] ) && $d['id'] && is_numeric( $d['id'] ) ) {
			$fields          = WooBeWoo_PF_Frame::_()->getTable( 'modules' )->fillFromDB( $d['id'] )->getFields();
			$fields['types'] = array();
			$types           = WooBeWoo_PF_Frame::_()->getTable( 'modules_type' )->fillFromDB();
			foreach ( $types as $t ) {
				$fields['types'][ $t['id']->value ] = $t['label']->value;
			}
			return $fields;
		} elseif ( ! empty( $d ) ) {
			$data = WooBeWoo_PF_Frame::_()->getTable( 'modules' )->get( '*', $d );
			return $data;
		} else {
			return WooBeWoo_PF_Frame::_()->getTable( 'modules' )
				->innerJoin( WooBeWoo_PF_Frame::_()->getTable( 'modules_type' ), 'type_id' )
				->getAll( WooBeWoo_PF_Frame::_()->getTable( 'modules' )->alias() . '.*, ' . WooBeWoo_PF_Frame::_()->getTable( 'modules_type' )->alias() . '.label as type' );
		}
	}

	/**
	 * put.
	 *
	 * @version 3.3.2
	 */
	public function put( $d = array() ) {
		$res = new ResponseWpf();
		$id  = $this->_getIDFromReq( $d );
		$d   = prepareParamsWpf( $d );
		if ( is_numeric( $id ) && $id ) {
			if ( isset( $d['active'] ) ) {
				$d['active'] = ( ( is_string( $d['active'] ) && 'true' == $d['active'] ) || 1 == $d['active'] ) ? 1 : 0;           // mmm.... govnokod?....)))
			}
			if ( WooBeWoo_PF_Frame::_()->getTable( 'modules' )->update( $d, array( 'id' => $id ) ) ) {
				$res->messages[] = esc_html__( 'Module Updated', 'woo-product-filter' );
				$mod             = WooBeWoo_PF_Frame::_()->getTable( 'modules' )->getById( $id );
				$newType         = WooBeWoo_PF_Frame::_()->getTable( 'modules_type' )->getById( $mod['type_id'], 'label' );
				$newType         = $newType['label'];
				$res->data       = array(
					'id'     => $id,
					'label'  => $mod['label'],
					'code'   => $mod['code'],
					'type'   => $newType,
					'active' => $mod['active'],
				);
			} else {
				$tableErrors = WooBeWoo_PF_Frame::_()->getTable( 'modules' )->getErrors();
				if ( $tableErrors ) {
					$res->errors = array_merge( $res->errors, $tableErrors );
				} else {
					$res->errors[] = esc_html__( 'Module Update Failed', 'woo-product-filter' );
				}
			}
		} else {
			$res->errors[] = esc_html__( 'Error module ID', 'woo-product-filter' );
		}
		return $res;
	}
	protected function _getIDFromReq( $d = array() ) {
		$id = 0;
		if ( isset( $d['id'] ) ) {
			$id = $d['id'];
		} elseif ( isset( $d['code'] ) ) {
			$fromDB = $this->get( array( 'code' => $d['code'] ) );
			if ( isset( $fromDB[0] ) && $fromDB[0]['id'] ) {
				$id = $fromDB[0]['id'];
			}
		}
		return $id;
	}
}
