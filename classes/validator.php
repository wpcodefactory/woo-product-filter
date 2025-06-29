<?php
class ValidatorWpf {
	public static $errors = array();
	public static function validate( $field, $label = '', $validate = array() ) {
		self::$errors = array();
		if (is_object($field) && get_class($field) != 'FieldWpf') {
			$value = $field;
			$field = new FieldWpf('noMatter');
			$field->label = $label;
			$field->setValue($value);
			$field->setValidation($validate);
		}
		if (!empty($field->validate)) {
			foreach ($field->validate as $v) {
				if (method_exists('ValidatorWpf', $v)) {
					self::$v($field);
				}
			}
		}
		if (method_exists('ValidatorWpf', $field->type)) {
			$validate = $field->type;
			self::$validate($field);
		}
		if ($field->maxlen) {
			self::validLen($field);
		}
		return self::$errors;
	}
	public static function validLen( $field, $label = '', $validate = array() ) {
		if ( !(bool) ( strlen($field->value) <= $field->maxlen ) ) {
			/* translators: 1: label 2: max length */
			self::addError(esc_html(sprintf(__('Invalid length for %1$s, max length is %2$s'), $field->label, $field->maxlen)), $field->name);
			return false;
		}
		return true;
	}
	public static function _( $field ) {
		return self::validate($field);
	}
	public static function getErrors() {
		return self::$errors;
	}
	public static function numeric( $field ) {
		if (!is_numeric($field->value) && !empty($field->value)) {
			/* translators: %s: label */
			self::addError(esc_html(sprintf(__('Invalid numeric value for %s'), $field->label)), $field->name);
			return false;
		}
		return true;
	}
	public static function int( $field ) {
		return self::numeric($field);
	}
	public static function float( $field ) {
		return self::numeric($field);
	}
	public static function double( $field ) {
		return self::numeric($field);
	}
	protected static function _notEmpty( $value ) {
		if (is_array($value)) {
			foreach ($value as $v) {
				if (self::_notEmpty($v)) {       //If at least 1 element of array are not empty - all array will be not empty
					$res = true;
					break;
				}
			}
		} else {
			$res = !empty($value);
		}
		return $res;
	}
	public static function notEmpty( $field ) {
		if (!self::_notEmpty($field->value)) {
			/* translators: %s: label */
			self::addError(esc_html(sprintf(__('Please enter %s'), $field->label)), $field->name);
			return false;
		}
		return true;
	}
	public static function selectNotEmpty( $field ) {
		if (empty($field->value)) {
			/* translators: %s: label */
			self::addError(esc_html(sprintf(__('Please select %s'), $field->label)), $field->name);
			return false;
		}
		return true;
	}
	public static function email( $field ) {
		if (!is_email($field->value)) {
			/* translators: %s: label */
			self::addError(esc_html(sprintf(__('Invalid %s'), $field->label)), $field->name);
			return false;
		} elseif (email_exists($field->value)) {
			/* translators: %s: label */
			self::addError(esc_html(sprintf(__('%s is already registered'), $field->label)), $field->name);
			return false;
		}
		return true;
	}
	public static function addError( $error, $key = '' ) {
		if ($key) {
			self::$errors[$key] = $error;
		} else {
			self::$errors[] = $error;
		}
	}
	public static function string( $field ) {
		if (preg_match('/([0-9].*)/', $field->value)) {
			/* translators: %s: label */
			self::addError(esc_html(sprintf(__('Invalid %s'), $field->label)), $field->name);
			return false;
		}
		return true;
	}
	public static function getProductValidationMethods() {
		$res = array();
		$all = get_class_methods('ValidatorWpf');
		foreach ($all as $m) {
			if (in_array($m, array('int', 'none', 'string'))) {
				$res[$m] = esc_html__($m);
			}
		}
		return $res;
	}
	public static function getUserValidationMethods() {
		// here validation for user fields
		$res = array();
		$all = get_class_methods('ValidatorWpf');
		foreach ($all as $m) {
			if (in_array($m, array('int', 'none', 'string', 'email', 'validLen'))) {
				$res[$m] = esc_html__($m);
			}
		}
		return $res;
	}
	public static function prepareInput( $input ) {
		global $wpdb;
		if (is_array($input)) {
			return array_map(array(validator, 'prepareInput'), $input);
		} else {
			return $wpdb->_real_escape($input);
		}
	}
}
