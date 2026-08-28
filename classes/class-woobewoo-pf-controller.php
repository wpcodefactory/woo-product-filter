<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Controller Class
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

abstract class WooBeWoo_PF_Controller {
	protected $_models      = array();
	protected $_views       = array();
	protected $_task        = '';
	protected $_defaultView = '';
	protected $_code        = '';
	public function __construct( $code ) {
		$this->setCode( $code );
		$this->_defaultView = $this->getCode();
	}
	public function init() {
		/*load model and other preload data goes here*/
	}
	protected function _onBeforeInit() {
	}
	protected function _onAfterInit() {
	}
	public function setCode( $code ) {
		$this->_code = $code;
	}
	public function getCode() {
		return $this->_code;
	}

	/**
	 * exec.
	 *
	 * @version 3.4.0
	 */
	public function exec( $task = '' ) {
		$permissions = $this->getPermissions();

		$allowed_methods = $permissions[ WPF_METHODS ] ?? array();

		if ( isset( $allowed_methods[ $task ] ) && method_exists( $this, $task ) ) {
			$this->_task = $task;

			return $this->$task();
		}

		return null;
	}
	public function getView( $name = '' ) {
		if ( empty( $name ) ) {
			$name = $this->getCode();
		}
		if ( ! isset( $this->_views[ $name ] ) ) {
			$this->_views[ $name ] = $this->_createView( $name );
		}
		return $this->_views[ $name ];
	}
	public function getModel( $name = '' ) {
		if ( ! $name ) {
			$name = $this->_code;
		}
		if ( ! isset( $this->_models[ $name ] ) ) {
			$this->_models[ $name ] = $this->_createModel( $name );
		}
		return $this->_models[ $name ];
	}

	/**
	 * _createModel.
	 *
	 * @version 3.4.0
	 */
	protected function _createModel( $name = '' ) {
		if ( empty( $name ) ) {
			$name = $this->getCode();
		}
		$parentModule = WooBeWoo_PF_Frame::_()->getModule( $this->getCode() );
		$className    = '';

		$modal_class_name     = WPF_CLASS_PREFIX . ucwords( $name, '_' ) . '_Model';
		$modal_class_file     = strtolower( str_replace( '_', '-', $modal_class_name ) ) . '.php';
		$modal_class_location = $parentModule->getModDir() . 'models' . WPF_DS . 'class-' . $modal_class_file;

		if ( file_exists( $modal_class_location ) ) {
			require $modal_class_location;
			$className = woobewoo_pf_toe_get_class_name( $modal_class_name );
		}

		if ( $className ) {
			$model = new $className();
			$model->setCode( $this->getCode() );

			return $model;
		}

		return null;
	}

	/**
	 * _createView.
	 *
	 * @version 3.4.0
	 */
	protected function _createView( $name = '' ) {
		if ( empty( $name ) ) {
			$name = $this->getCode();
		}
		$parentModule = WooBeWoo_PF_Frame::_()->getModule( $this->getCode() );
		$className    = '';

		$view_class_name     = WPF_CLASS_PREFIX . ucwords( $name ) . '_View';
		$view_class_file     = strtolower( str_replace( '_', '-', $view_class_name ) ) . '.php';
		$view_class_location = $parentModule->getModDir() . 'views'  . WPF_DS . 'class-' . $view_class_file;

		if ( file_exists( $view_class_location ) ) {
			require $view_class_location;
			$className = woobewoo_pf_toe_get_class_name( $view_class_name );
		}

		if ( $className ) {
			$view = new $className();
			$view->setCode( $this->getCode() );

			return $view;
		}

		return null;
	}

	public function display( $viewName = '' ) {
		$view = $this->getView( $viewName );
		if ( null === $view ) {
			$view = $this->getView();   // Get default view
		}
		if ( $view ) {
			$view->display();
		}
	}

	/**
	 * Magic method: __call
	 *
	 * @version 3.3.0
	 *
	 * @param $name
	 * @param $arguments
	 */
	public function __call( $name, $arguments ) {
		$blockedMethods = array( 'delete', 'clear', 'woobewoo_pf_remove_group' );
		if ( in_array( $name, $blockedMethods, true ) ) {
			return false;
		}

		$model = $this->getModel();
		if ( method_exists( $model, $name ) ) {
			return $model->$name( $arguments[0] );
		} else {
			return false;
		}
	}
	/**
	 * Retrive permissions for controller methods if exist.
	 * If need - should be redefined in each controller where it required.
	 *
	 * @return array with permissions
	 * Can be used on of sub-array - WPF_METHODS or WPF_USERLEVELS
	 */
	public function getPermissions() {
		return array();
	}
	/**
	 * Methods that require nonce to be generated
	 * If need - should be redefined in each controller where it required.
	 *
	 * @return array
	 */
	public function getNoncedMethods() {
		return array();
	}

	/**
	 * getModule.
	 *
	 * @version 3.4.0
	 */
	public function getModule() {
		return WooBeWoo_PF_Frame::_()->getModule( $this->getCode() );
	}
	protected function _prepareTextLikeSearch( $val ) {
		return ''; // Should be re-defined for each type
	}
	protected function _prepareModelBeforeListSelect( $model ) {
		return $model;
	}
	/**
	 * Common method for list table data
	 *
	 * @version 3.4.0
	 */
	public function woobewoo_pf_get_list_for_table() {
		WooBeWoo_PF_Req::verifyRequest();
		$res = new WooBeWoo_PF_Response();
		$res->ignoreShellData();
		$model = $this->getModel();

		$page      = (int) WooBeWoo_PF_Req::getVar( 'page' );
		$rowsLimit = (int) WooBeWoo_PF_Req::getVar( 'rows' );
		$orderBy   = WooBeWoo_PF_Req::getVar( 'sidx' );
		$sortOrder = WooBeWoo_PF_Req::getVar( 'sord' ) == 'asc' ? 'asc' : 'desc';

		// Our custom search
		$search = WooBeWoo_PF_Req::getVar( 'search' );
		if ( $search && ! empty( $search ) && is_array( $search ) ) {
			foreach ( $search as $k => $v ) {
				$v = trim( $v );
				if ( empty( $v ) ) {
					continue;
				}
				if ( 'text_like' == $k ) {
					$v = $this->_prepareTextLikeSearch( $v );
					if ( ! empty( $v ) ) {
						$model->addWhere( array( 'additionalCondition' => $v ) );
					}
				} else {
					$model->addWhere( array( $k => $v ) );
				}
			}
		}
		// jqGrid search
		$isSearch = WooBeWoo_PF_Req::getVar( '_search' );
		if ( $isSearch ) {
			$searchField  = trim( WooBeWoo_PF_Req::getVar( 'searchField', 'all', '' ) );
			$searchString = trim( WooBeWoo_PF_Req::getVar( 'searchString', 'all', '' ) );
			if ( ! empty( $searchField ) && ! empty( $searchString ) ) {
				// For some cases - we will need to modify search keys and/or values before put it to the model
				$model->addWhere( array( $this->_prepareSearchField( $searchField ) => $this->_prepareSearchString( $searchString ) ) );
			}
		}
		$model = $this->_prepareModelBeforeListSelect( $model );

		// Get total pages count for current request
		$totalCount = $model->getCount( array( 'clear' => array( 'selectFields' ) ) );
		$totalPages = 0;
		if ( $totalCount > 0 ) {
			$totalPages = ceil( $totalCount / $rowsLimit );
		}
		if ( $page > $totalPages ) {
			$page = $totalPages;
		}
		// Calc limits - to get data only for current set
		$limitStart = $rowsLimit * $page - $rowsLimit; // do not put $limit*($page - 1)
		if ( $limitStart < 0 ) {
			$limitStart = 0;
		}
		$tbl = WooBeWoo_PF_Frame::_()->getTable( $model->getTbl() );
		if ( is_null( $tbl ) || ! $tbl->haveField( $orderBy ) ) {
			$orderBy = 'id';
		}

		$data = $model
			->setLimit( $limitStart . ', ' . $rowsLimit )
			->setOrderBy( $this->_prepareSortOrder( $orderBy ) )
			->setSortOrder( $sortOrder )
			->setSimpleGetFields()
			->getFromTbl();

		$data = $this->_prepareListForTbl( $data );
		$res->addData( 'page', $page );
		$res->addData( 'total', $totalPages );
		$res->addData( 'rows', $data );
		$res->addData( 'records', $model->getLastGetCount() );
		$res = WooBeWoo_PF_Dispatcher::applyFilters( $this->getCode() . '_getListForTblResults', $res );
		$res->ajaxExec();
	}

	/**
	 * woobewoo_pf_remove_group.
	 *
	 * @version 3.4.0
	 */
	public function woobewoo_pf_remove_group() {
		WooBeWoo_PF_Req::verifyRequest();

		$res = new WooBeWoo_PF_Response();
		if (
			$this->getModel()->woobewoo_pf_remove_group( WooBeWoo_PF_Req::getVar( 'listIds', 'post' ) )
		) {
			$res->addMessage( esc_html__( 'Done', 'woo-product-filter' ) );
		} else {
			$res->pushError( $this->getModel()->getErrors() );
		}
		$res->ajaxExec();
	}

	/**
	 * clear.
	 *
	 * @version 3.4.0
	 */
	public function clear() {
		$res = new WooBeWoo_PF_Response();
		if ( $this->getModel()->clear() ) {
			$res->addMessage( esc_html__( 'Done', 'woo-product-filter' ) );
		} else {
			$res->pushError( $this->getModel()->getErrors() );
		}
		$res->ajaxExec();
	}
	protected function _prepareListForTbl( $data ) {
		return $data;
	}
	protected function _prepareSearchField( $searchField ) {
		return $searchField;
	}
	protected function _prepareSearchString( $searchString ) {
		return $searchString;
	}
	protected function _prepareSortOrder( $sortOrder ) {
		return $sortOrder;
	}
}
