<?php
/**
 * Product Filter by WBW - ViewWpf Class
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;
#[\AllowDynamicProperties]
abstract class ViewWpf extends WooBeWoo_PF_Base_Object {
	/*
	 * @deprecated
	 */
	protected $_tpl = WPF_DEFAULT;
	/*
	 * @var string name of theme to load from templates, if empty - default values will be used
	 */
	protected $_theme = '';
	/*
	 * @var string module code for this view
	 */
	protected $_code = '';

	public function display( $tpl = '' ) {
		$tpl     = ( empty( $tpl ) ) ? $this->_tpl : $tpl;
		$content = $this->getContent( $tpl );
		if ( false !== $content ) {
			HtmlWpf::echoEscapedHtml( $content );
		}
	}

	/**
	 * getPath.
	 *
	 * @version 3.3.2
	 */
	public function getPath( $tpl ) {
		$path         = '';
		$parentModule = WooBeWoo_PF_Frame::_()->getModule( $this->_code );
		if ( file_exists( $parentModule->getModDir() . 'views' . WPF_DS . 'tpl' . WPF_DS . $tpl . '.php' ) ) { // Then try to find it in module directory
			$path = $parentModule->getModDir() . WPF_DS . 'views' . WPF_DS . 'tpl' . WPF_DS . $tpl . '.php';
		}

		return $path;
	}

	/**
	 * getModule.
	 *
	 * @version 3.3.2
	 */
	public function getModule() {
		return WooBeWoo_PF_Frame::_()->getModule( $this->_code );
	}

	/**
	 * getModel.
	 *
	 * @version 3.3.2
	 */
	public function getModel( $code = '' ) {
		return WooBeWoo_PF_Frame::_()->getModule( $this->_code )->getController()->getModel( $code );
	}

	/**
	 * getContent.
	 *
	 * @version 3.3.2
	 */
	public function getContent( $tpl = '' ) {
		$tpl          = ( empty( $tpl ) ) ? $this->_tpl : $tpl;
		$path         = $this->getPath( $tpl );
		$parentModule = WooBeWoo_PF_Frame::_()->getModule( $this->_code );
		if ( $path ) {
			$content = '';
			ob_start();
			require $parentModule->getModDir() . WPF_DS . 'views' . WPF_DS . 'tpl' . WPF_DS . $tpl . '.php';
			$content = ob_get_contents();
			ob_end_clean();
			return $content;
		}
		return false;
	}
	public function setTheme( $theme ) {
		$this->_theme = $theme;
	}
	public function getTheme() {
		return $this->_theme;
	}
	public function setTpl( $tpl ) {
		$this->_tpl = $tpl;
	}
	public function getTpl() {
		return $this->_tpl;
	}
	public function init() {
	}
	public function assign( $name, $value ) {
		$this->$name = $value;
	}
	public function setCode( $code ) {
		$this->_code = $code;
	}
	public function getCode() {
		return $this->_code;
	}

	/**
	 * This will display form for our widgets
	 *
	 * @version 3.3.2
	 */
	public function displayWidgetForm( $data = array(), $widget = array(), $formTpl = 'form' ) {
		$this->assign( 'data', $data );
		$this->assign( 'widget', $widget );
		if ( WooBeWoo_PF_Frame::_()->isTplEditor() ) {
			if ( $this->getPath( $formTpl . '_ext' ) ) {
				$formTpl .= '_ext';
			}
		}
		self::display( $formTpl );
	}
	public function sizeToPxPt( $size ) {
		if ( ! strpos( $size, 'px' ) && ! strpos( $size, '%' ) ) {
			$size .= 'px';
		}
		return $size;
	}
	public function getInlineContent( $tpl = '' ) {
		return preg_replace( '/\s+/', ' ', $this->getContent( $tpl ) );
	}
}
