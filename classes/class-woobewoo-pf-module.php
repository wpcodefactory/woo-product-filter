<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Module Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

abstract class WooBeWoo_PF_Module extends WooBeWoo_PF_Base_Object {

	protected $_controller = null;
	protected $_helper     = null;
	protected $_code       = '';
	protected $_onAdmin    = false;
	protected $_typeID     = 0;
	protected $_type       = '';
	protected $_label      = '';

	/**
	 * ID in modules table.
	 */
	protected $_id = 0;

	/**
	 * If module is not in primary package - here will be it's path.
	 */
	protected $_externalDir  = '';
	protected $_externalPath = '';
	protected $_isExternal   = false;

	/**
	 * __construct.
	 *
	 * @version 3.3.2
	 */
	public function __construct( $d ) {
		$this->setTypeID( $d['type_id'] );
		$this->setCode( $d['code'] );
		$this->setLabel( $d['label'] );
		if ( isset( $d['id'] ) ) {
			$this->_setID( $d['id'] );
		}
		if ( isset( $d['ex_plug_dir'] ) && ! empty( $d['ex_plug_dir'] ) ) {
			$this->isExternal( true );
			$this->setExternalDir( WooBeWoo_PF_Utils::getExtModDir( $d['ex_plug_dir'] ) );
			$this->setExternalPath( WooBeWoo_PF_Utils::getExtModPath( $d['ex_plug_dir'] ) );
		}
	}

	public function isExternal( $newVal = null ) {
		if ( is_null( $newVal ) ) {
			return $this->_isExternal;
		}
		$this->_isExternal = $newVal;
	}

	/**
	 * getModDir.
	 *
	 * @version 3.3.0
	 */
	public function getModDir() {
		if ( empty( $this->_externalDir ) ) {
			return WPF_MODULES_DIR . $this->getCode() . WPF_DS;
		} else {
			return $this->_externalDir . $this->getCode() . WPF_DS;
		}
	}

	public function getModPath() {
		if ( empty( $this->_externalPath ) ) {
			return WPF_MODULES_PATH . $this->getCode() . '/';
		} else {
			return $this->_externalPath . $this->getCode() . '/';
		}
	}

	/**
	 * getModRealDir.
	 *
	 * @version 3.3.0
	 */
	public function getModRealDir() {
		return __DIR__ . WPF_DS;
	}

	public function setExternalDir( $dir ) {
		$this->_externalDir = $dir;
	}

	public function getExternalDir() {
		return $this->_externalDir;
	}

	public function setExternalPath( $path ) {
		$this->_externalPath = $path;
	}

	public function getExternalPath() {
		return $this->_externalPath;
	}

	/**
	 * Set ID for module, protected - to limit opportunity change this value.
	 */
	protected function _setID( $id ) {
		$this->_id = $id;
	}

	/**
	 * Get module ID from modules table in database.
	 *
	 * @return int ID of module.
	 */
	public function getID() {
		return $this->_id;
	}

	public function setTypeID( $typeID ) {
		$this->_typeID = $typeID;
	}

	public function getTypeID() {
		return $this->_typeID;
	}

	public function setType( $type ) {
		$this->_type = $type;
	}

	public function getType() {
		return $this->_type;
	}

	public function getLabel() {
		return $this->_label;
	}

	public function setLabel( $label ) {
		$this->_label = $label;
	}

	public function init() {
	}

	public function exec( $task = '' ) {
		if ( $task ) {
			$controller = $this->getController();
			if ( $controller ) {
				return $controller->exec( $task );
			}
		}
		return null;
	}

	public function getController() {
		if ( ! $this->_controller ) {
			$this->_createController();
		}
		return $this->_controller;
	}

	/**
	 * _createController.
	 *
	 * @version 3.3.2
	 */
	protected function _createController() {
		$controller_class_name = WPF_CLASS_PREFIX . ucwords( $this->getCode() ) . '_Controller';
		$controller_class_file     = strtolower( str_replace( '_', '-', $controller_class_name ) ) . '.php';
		$controller_class_location = $this->getModDir() . 'class-' . $controller_class_file;

		if ( ! file_exists( $controller_class_location ) ) {
			return false; // EXCEPTION!!!
		}
		if ( $this->_controller ) {
			return true;
		}
		if ( file_exists( $controller_class_location ) ) {
			$className = '';
			require $controller_class_location;
			$className = toeGetClassNameWpf( $controller_class_name );
			if ( ! empty( $className ) ) {
				$this->_controller = new $className( $this->getCode() );
				$this->_controller->init();
				return true;
			}
		}
		return false;
	}

	/**
	 * Method to call module helper if it exists.
	 *
	 * @version 3.3.2
	 *
	 * @return class WooBeWoo_PF_Helper
	 */
	public function getHelper() {
		if ( ! $this->_helper ) {
			$this->_createHelper();
		}
		return $this->_helper;
	}

	/**
	 * Method to create class of module helper.
	 *
	 * @return class WooBeWoo_PF_Helper
	 */
	protected function _createHelper() {
		if ( $this->_helper ) {
			return true;
		}
		if ( file_exists( $this->getModDir() . 'helper.php' ) ) {
			$helper = $this->getCode() . 'Helper';
			if ( ! class_exists( $helper ) ) {
				if ( file_exists( $this->getModDir() . 'helper.php' ) ) {
					require $this->getModDir() . 'helper.php';
				}
			}

			if ( class_exists( $helper ) ) {
				$this->_helper = new $helper( $this->_code );
				$this->_helper->init();
				return true;
			}
		}
	}

	public function setCode( $code ) {
		$this->_code = $code;
	}

	public function getCode() {
		return $this->_code;
	}

	public function onAdmin() {
		return $this->_onAdmin;
	}

	public function getModel( $modelName = '' ) {
		return $this->getController()->getModel( $modelName );
	}

	public function getView( $viewName = '' ) {
		return $this->getController()->getView( $viewName );
	}

	public function install() {
	}

	public function uninstall() {
	}

	public function activate() {
	}

	/**
	 * Returns the available tabs.
	 *
	 * @return array of tab
	 */
	public function getTabs() {
		return array();
	}

	public function getConstant( $name ) {
		$thisClassRefl = new ReflectionObject( $this );
		return $thisClassRefl->getConstant( $name );
	}

	public function loadAssets() {
	}

	public function loadAdminAssets() {
	}

	/**
	 * translate.
	 *
	 * @version 3.1.8
	 *
	 * @todo (v3.1.8) rename the method?
	 */
	public function translate( $str ) {
		return esc_html( $str );
	}

	/**
	 * pro_label.
	 *
	 * @version 3.3.2
	 * @since   3.3.0
	 */
	public function pro_label() {
		$pro_link = WooBeWoo_PF_Frame::_()->getModule( 'adminmenu' )->getMainLink() . '&tab=gopro';
		return '<span class="wpfProLabel">
				<a href="' . esc_url( $pro_link ) . '" target="_blank">
					' . esc_html__( 'PRO Option', 'woo-product-filter' ) . '
				</a>
			</span>';
	}
}
