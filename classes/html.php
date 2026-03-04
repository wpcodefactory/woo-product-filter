<?php
class HtmlWpf {
	public static $categoriesOptions = array();
	public static $productsOptions = array();
	public static function echoEscapedHtml( $html ) {
		remove_all_filters( 'esc_html');
		add_filter('esc_html', array('HtmlWpf', 'skipHtmlEscape'), 99, 2);
		echo esc_html($html);
		remove_filter('esc_html', array('HtmlWpf', 'skipHtmlEscape'), 99, 2);
	}
	public static function skipHtmlEscape( $safe_text, $text ) {
		return $text;
	}
	public static function block( $name, $params = array('attrs' => '', 'value' => '') ) {
		$output .= '<p class="toe_' . self::nameToClassId($name) . '">' . $params['value'] . '</p>';
		return $output;
	}
	public static function nameToClassId( $name, $params = array() ) {
		if (!empty($params) && isset($params['attrs']) && strpos($params['attrs'], 'id="') !== false) {
			preg_match('/id="(.+)"/ui', $params['attrs'], $idMatches);
			if ($idMatches[1]) {
				return $idMatches[1];
			}
		}
		return str_replace(array('[', ']'), '', $name);
	}
	public static function textarea( $name, $params = array('attrs' => '', 'value' => '', 'rows' => 3, 'cols' => 50) ) {
		$params['attrs'] = isset($params['attrs']) ? $params['attrs'] : '';
		$params['rows'] = isset($params['rows']) ? $params['rows'] : 3;
		$params['cols'] = isset($params['cols']) ? $params['cols'] : 50;
		if (isset($params['required']) && $params['required']) {
			$params['attrs'] .= ' required '; // HTML5 "required" validation attr
		}
		if (isset($params['placeholder']) && $params['placeholder']) {
			$params['attrs'] .= ' placeholder="' . esc_attr($params['placeholder']) . '"'; // HTML5 "required" validation attr
		}
		if (isset($params['disabled']) && $params['disabled']) {
			$params['attrs'] .= ' disabled ';
		}
		if (isset($params['readonly']) && $params['readonly']) {
			$params['attrs'] .= ' readonly ';
		}
		if (isset($params['auto_width']) && $params['auto_width']) {
			unset($params['rows']);
			unset($params['cols']);
		}
		echo '<textarea name="' . esc_attr($name) . '" ';
		if (!empty($params['attrs'])) {
			self::echoEscapedHtml($params['attrs']);
		}
		echo ( isset($params['rows']) ? ' rows="' . esc_attr($params['rows']) . '"' : '' ) .
			( isset($params['cols']) ? ' cols="' . esc_attr($params['cols']) . '"' : '' ) . '>' .
			( isset($params['value']) ? esc_html($params['value']) : '' ) .
		'</textarea>';
	}
	public static function input( $name, $params = array('attrs' => '', 'type' => 'text', 'value' => '') ) {
		$params['attrs'] = isset($params['attrs']) ? $params['attrs'] : '';
		$params['attrs'] .= self::_dataToAttrs($params);
		if (isset($params['required']) && $params['required']) {
			$params['attrs'] .= ' required '; // HTML5 "required" validation attr
		}
		if (isset($params['placeholder']) && $params['placeholder']) {
			$params['attrs'] .= ' placeholder="' . esc_attr($params['placeholder']) . '"'; // HTML5 "required" validation attr
		}
		if (isset($params['disabled']) && $params['disabled']) {
			$params['attrs'] .= ' disabled ';
		}
		if (isset($params['readonly']) && $params['readonly']) {
			$params['attrs'] .= ' readonly ';
		}
		$params['type'] = isset($params['type']) ? $params['type'] : 'text';
		$params['value'] = isset($params['value']) ? $params['value'] : '';

		echo '<input type="' . esc_attr($params['type']) . '" name="' . esc_attr($name) . '" value="' . esc_attr($params['value']) . '" ';
		if (!empty($params['attrs'])) {
			self::echoEscapedHtml($params['attrs']);
		}
		echo ' />';
	}
	private static function _dataToAttrs( $params ) {
		$res = '';
		foreach ($params as $k => $v) {
			if (strpos($k, 'data-') === 0) {
				$res .= ' ' . $k . '="' . $v . '"';
			}
		}
		return $res;
	}
	public static function text( $name, $params = array('attrs' => '', 'value' => '') ) {
		$params['type'] = 'text';
		self::input($name, $params);
	}
	public static function email( $name, $params = array('attrs' => '', 'value' => '') ) {
		$params['type'] = 'email';
		self::input($name, $params);
	}
	public static function reset( $name, $params = array('attrs' => '', 'value' => '') ) {
		$params['type'] = 'reset';
		self::input($name, $params);
	}
	public static function password( $name, $params = array('attrs' => '', 'value' => '') ) {
		$params['type'] = 'password';
		self::input($name, $params);
	}
	public static function hidden( $name, $params = array('attrs' => '', 'value' => '') ) {
		$params['type'] = 'hidden';
		self::input($name, $params);
	}
	public static function checkbox( $name, $params = array('attrs' => '', 'value' => '', 'checked' => '') ) {
		$params['type'] = 'checkbox';
		if ( isset($params['checked']) && $params['checked'] ) {
			$params['checked'] = 'checked';
		}
		if ( !isset($params['value']) || null == $params['value'] ) {
			$params['value'] = 1;
		}
		if (!isset($params['attrs'])) {
			$params['attrs'] = '';
		}
		$params['attrs'] .= ' ' . ( isset($params['checked']) ? $params['checked'] : '' );
		self::input($name, $params);
	}
	public static function checkboxToggle( $name, $params = array('attrs' => '', 'value' => '', 'checked' => '') ) {
		$params['type'] = 'checkbox';
		$params['checked'] = isset($params['checked']) && $params['checked'] ? 'checked' : '';
		if ( !isset($params['value']) || ( null === $params['value'] ) ) {
			$params['value'] = 1;
		}
		$id = ( empty($params['id']) ? self::nameToClassId($name) . mt_rand(9, 9999) : $params['id'] );
		$params['attrs'] = 'id="' . esc_attr($id) . '" class="toggle" ' . ( isset($params['attrs']) ? $params['attrs'] . ' ' : '' ) . $params['checked'];
		
		self::input($name, $params);
		echo '<label for="' . esc_attr($id) . '" class="toggle"></label>';
	}
	public static function checkboxlist( $name, $params = array('options' => array(), 'attrs' => '', 'checked' => '', 'delim' => '<br />', 'usetable' => 5), $delim = '<br />' ) {
		if (!strpos($name, '[]')) {
			$name .= '[]';
		}
		$i = 0;
		if ($params['options']) {
			if (!isset($params['delim'])) {
				$params['delim'] = $delim;
			}
			if (!empty($params['usetable'])) {
				echo '<table><tr>';
			}
			foreach ($params['options'] as $v) {
				if (!empty($params['usetable'])) {
					if ( ( 0 != $i ) && ( 0 == $i%$params['usetable'] ) ) {
						echo '</tr><tr>';
					}
					echo '<td>';
				}
				self::checkboxToggle($name, array(
					'attrs' => !empty($params['attrs']),
					'value' => empty($v['value']) ? $v['id'] : $v['value'],
					'checked' => $v['checked'],
					'id' => $v['id'],
				));
				echo '&nbsp;';
				if (!empty($v['text'])) {
					self::echoEscapedHtml($v['text']);
				}
				if (!empty($params['delim'])) {
					self::echoEscapedHtml($params['delim']);
				}
				if (!empty($params['usetable'])) {
					echo '</td>';
				}
				$i++;
			}
			if (!empty($params['usetable'])) {
				echo '</tr></table>';
			}
		}
	}
	public static function submit( $name, $params = array('attrs' => '', 'value' => '') ) {
		$params['type'] = 'submit';
		self::input($name, $params);
	}
	public static function img( $src, $usePlugPath = 1, $params = array('width' => '', 'height' => '', 'attrs' => '') ) {
		if ($usePlugPath) {
			$src = WPF_IMG_PATH . $src;
		}
		echo '<img src="' . esc_url($src) . '" '
				. ( isset($params['width']) ? 'width="' . esc_attr($params['width']) . '"' : '' )
				. ' '
				. ( isset($params['height']) ? 'height="' . esc_attr($params['height']) . '"' : '' )
				. ' ';
		if (!empty($params['attrs'])) {
			self::echoEscapedHtml($params['attrs']);
		}
		echo ' />';
	}
	public static function selectbox( $name, $params = array('attrs' => '', 'options' => array(), 'value' => '') ) {
		$params['attrs'] = isset($params['attrs']) ? $params['attrs'] : '';
		$params['attrs'] .= self::_dataToAttrs($params);
		if (isset($params['required']) && $params['required']) {
			$params['attrs'] .= ' required '; // HTML5 "required" validation attr
		}
		echo '<select name="' . esc_attr($name) . '" ';
		if (!empty($params['attrs'])) {
			self::echoEscapedHtml($params['attrs']);
		}
		echo '>';
		$existValue = isset($params['value']);
		if (!empty($params['options'])) {
			foreach ($params['options'] as $k => $v) {
				echo '<option value="' . esc_attr($k) . '"' . ( $existValue && $k == $params['value'] ? ' selected="true"' : '' ) . '>' . esc_html($v) . '</option>';
			}
		}
		echo '</select>';
	}
	public static function selectlist( $name, $params = array('attrs'=>'', 'size'=> 5, 'options' => array(), 'value' => '') ) {
		if (!strpos($name, '[]')) {
			$name .= '[]';
		}
		if ( !isset($params['size']) || !is_numeric($params['size']) || ( '' == $params['size'] ) ) {
			$params['size'] = 5;
		}
		$params['attrs'] = isset($params['attrs']) ? $params['attrs'] : '';
		$params['attrs'] .= self::_dataToAttrs($params);

		echo '<select multiple="multiple" size="' . esc_attr($params['size']) . '" name="' . esc_attr($name) . '" ';
		if (!empty($params['attrs'])) {
			self::echoEscapedHtml($params['attrs']);
		}
		echo '>';

		$params['value'] = isset($params['value']) ? $params['value'] : array();
		if (!empty($params['options'])) {
			foreach ($params['options'] as $k => $v) {
				$selected = ( in_array($k, (array) $params['value']) ? 'selected="true"' : '' );
				echo '<option value="' . esc_attr($k) . '" ' . esc_attr($selected) . '>' . esc_html($v) . '</option>';
			}
		}
		echo '</select>';
	}
	public static function file( $name, $params = array() ) {
		$params['type'] = 'file';
		self::input($name, $params);
	}
	public static function button( $params = array('attrs' => '', 'value' => '') ) {
		echo '<button ';
		if (!empty($params['attrs'])) {
			self::echoEscapedHtml($params['attrs']);
		}
		echo '>' . esc_html($params['value']) . '</button>';
	}
	public static function buttonA( $params = array('attrs' => '', 'value' => '') ) {
		echo '<a href="#" ';
		if (!empty($params['attrs'])) {
			self::echoEscapedHtml($params['attrs']);
		}
		echo '>' . esc_html($params['value']) . '</a>';
	}
	public static function inputButton( $params = array('attrs' => '', 'value' => '') ) {
		if (!is_array($params)) {
			$params = array();
		}
		$params['type'] = 'button';
		self::input('', $params);
	}
	public static function radiobuttons( $name, $params = array('attrs' => '', 'options' => array(), 'value' => '', '') ) {
		if (isset($params['options']) && is_array($params['options']) && !empty($params['options'])) {
			$params['labeled'] = isset($params['labeled']) ? $params['labeled'] : false;
			$params['attrs'] = isset($params['attrs']) ? $params['attrs'] : '';
			$params['no_br'] = isset($params['no_br']) ? $params['no_br'] : false;
			foreach ($params['options'] as $key => $val) {
				$checked = ( $key == $params['value'] ) ? 'checked' : '';
				if ($params['labeled']) {
					echo '<label>' . esc_html($val) . '&nbsp;';
				}
				self::input($name, array('attrs' => $params['attrs'] . ' ' . $checked, 'type' => 'radio', 'value' => $key));
				if ($params['labeled']) {
					echo '</label>';
				}
				if (!$params['no_br']) {
					echo '<br />';
				}
			}
		}
	}
	public static function radiobutton( $name, $params = array('attrs' => '', 'value' => '', 'checked' => '') ) {
		$params['type'] = 'radio';
		$params['attrs'] = isset($params['attrs']) ? $params['attrs'] : '';
		if (isset($params['checked']) && $params['checked']) {
			$params['attrs'] .= ' checked';
		}
		self::input($name, $params);
	}
	public static function formStart( $name, $params = array('action' => '', 'method' => 'GET', 'attrs' => '', 'hideMethodInside' => false) ) {
		$params['attrs'] = isset($params['attrs']) ? $params['attrs'] : '';
		$params['action'] = isset($params['action']) ? $params['action'] : '';
		$params['method'] = isset($params['method']) ? $params['method'] : 'GET';
		echo '<form name="' . esc_attr($name) . '" action="' . esc_attr($params['action']) . '" method="' . esc_attr($params['method']) . '" ';
		if (!empty($params['attrs'])) {
			self::echoEscapedHtml($params['attrs']);
		}
		echo '>';

		if (isset($params['hideMethodInside']) && $params['hideMethodInside']) {
			self::hidden('method', array('value' => $params['method']));
		}
	}
	public static function formEnd() {
		echo '</form>';
	}
	public static function categorySelectlist( $name, $params = array('attrs'=>'', 'size'=> 5, 'value' => '') ) {
		self::_loadCategoriesOptions();
		if (self::$categoriesOptions) {
			$params['options'] = self::$categoriesOptions;
			self::selectlist($name, $params);
		}
		return false;
	}
	public static function categorySelectbox( $name, $params = array('attrs'=>'', 'size'=> 5, 'value' => '') ) {
		self::_loadCategoriesOptions();
		if (!empty(self::$categoriesOptions)) {
			$params['options'] = self::$categoriesOptions;
			self::selectbox($name, $params);
		}
		return false;
	}
	public static function productsSelectlist( $name, $params = array('attrs'=>'', 'size'=> 5, 'value' => '') ) {
		self::_loadProductsOptions();
		if (!empty(self::$productsOptions)) {
			$params['options'] = self::$productsOptions;
			self::selectlist($name, $params);
		}
		return false;
	}
	public static function productsSelectbox( $name, $params = array('attrs'=>'', 'size'=> 5, 'value' => '') ) {
		self::_loadProductsOptions();
		if (!empty(self::$productsOptions)) {
			$params['options'] = self::$productsOptions;
			self::selectbox($name, $params);
		}
		return false;
	}
	protected static function _loadCategoriesOptions() {
		if (empty(self::$categoriesOptions)) {
			$categories = FrameWpf::_()->getModule('products')->getCategories();
			if (!empty($categories)) {
				foreach ($categories as $c) {
					self::$categoriesOptions[$c->term_taxonomy_id] = $c->cat_name;
				}
			}
		}
	}
	protected static function _loadProductsOptions() {
		if (empty(self::$productsOptions)) {
			$products = FrameWpf::_()->getModule('products')->getModel()->get(array('getFields' => 'post.ID, post.post_title'));
			if (!empty($products)) {
				foreach ($products as $p) {
					self::$productsOptions[$p['ID']] = $p['post_title'];
				}
			}
		}
	}
	public static function colorpicker( $name, $params = array('value' => '') ) {
		$value = isset($params['value']) ? $params['value'] : '';
		echo '<div class="woobewoo-color-picker">';
		self::text('', array('value' => $value, 'attrs' => ' data-alpha="true" class="woobewoo-color-result"'));
		self::text($name, array('value' => $value, 'attrs' => 'class="woobewoo-color-result-text"'));
		echo '</div>';
	}
	public static function checkboxHiddenVal( $name, $params = array('attrs' => '', 'value' => '', 'checked' => '') ) {
		$params['attrs'] = isset($params['attrs']) ? $params['attrs'] : '';
		$paramsCheck = $params;
		$paramsHidden = $params;

		$paramsCheck['attrs'] .= ' data-hiden-input=1';
		$paramsCheck['value'] = isset($paramsCheck['value']) ? $paramsCheck['value'] : '';
		$paramsCheck['checked'] = $paramsCheck['value'] ? '1' : '0';
		self::checkbox(self::nameToClassId($name), $paramsCheck);
		self::hidden($name, $paramsHidden);
	}
	public static function checkedOpt( $arr, $key, $value = true, $default = false ) {
		if (!isset($arr[ $key ])) {
			return $default ? true : false;
		}
		return true === $value ? $arr[ $key ] : $arr[ $key ] == $value;
	}
	public static function nonceForAction( $action ) {
		self::hidden('_wpnonce', array('value' => wp_create_nonce(strtolower($action))));
	}
	public static function selectIcon( $name, $params ) {
		echo '<div class="button chooseLoaderIcon">' . esc_html__('Choose Icon', 'woo-product-filter') . '</div>';
	}

	public static function startMetaButton( $name, $params ) {
		echo '<button id="wpfStartMetaIndexing" class="button button-primary"><i class="fa fa-play" aria-hidden="true"></i></button>';
	}
	public static function startOptimizingButton( $name, $params ) {
		echo '<button id="wpfStartOptimizing" class="button button-primary"><i class="fa fa-play" aria-hidden="true"></i></button>';
	}
}
