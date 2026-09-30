<?php
/**
 * Product Filter by WBW - Functions
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set first letter in a string as UPPERCASE.
 *
 * @version 3.4.0
 *
 * @param string $str string to modify
 *
 * @return string string with first Uppercase letter
 */
if ( ! function_exists( 'woobewoo_pf_str_first_up' ) ) {
	function woobewoo_pf_str_first_up( $str ) {
		return ucfirst( strtolower( $str ) );
	}
}

/**
 * woobewoo_pf_date_to_timestamp.
 *
 * @version 3.4.0
 */
if ( ! function_exists( 'woobewoo_pf_date_to_timestamp' ) ) {
	function woobewoo_pf_date_to_timestamp( $date ) {
		if ( empty( $a ) ) {
			return false;
		}
		$a = explode( WPF_DATE_DL, $date );

		return mktime( 0, 0, 0, $a[1], $a[0], $a[2] );
	}
}

/**
 * woobewoo_pf_import_class.
 *
 * @version 3.4.0
 */
if ( ! function_exists( 'woobewoo_pf_import_class' ) ) {
	function woobewoo_pf_import_class( $class, $path = '' ) {
		if ( ! class_exists( $class ) ) {
			$classFile = lcfirst( $class );
			if ( strpos( strtolower( $classFile ), WPF_CODE ) !== false ) {
				$classFile = preg_replace( '/' . WPF_CODE . '/i', '', $classFile );
			} else if ( strpos( $class, WPF_CLASS_PREFIX ) !== false ) {
				$classFile = str_replace( '_', '-', $classFile );
				$classFile = 'class-' . strtolower( $classFile );
			}

			$path = WPF_CLASSES_DIR . $classFile . '.php';
			if ( file_exists( $path ) ) {
				require WPF_CLASSES_DIR . $classFile . '.php';

				return true;
			}
			// return importWpf($path);
		}

		return false;
	}
}

/**
 * Check if class name exist with prefix or not.
 *
 * @version 3.4.0
 *
 * @param string $class preferred class name
 *
 * @return string existing class name
 */
if ( ! function_exists( 'woobewoo_pf_toe_get_class_name' ) ) {
	function woobewoo_pf_toe_get_class_name( $class ) {
		if ( class_exists( $class . woobewoo_pf_str_first_up( WPF_CODE ) ) ) {
			$className = $class . woobewoo_pf_str_first_up( WPF_CODE );
		} elseif ( class_exists( WPF_CLASS_PREFIX . ucwords( $class ) ) ) {
			$className = WPF_CLASS_PREFIX . ucwords( $class );
		} else {
			$className = $class;
		}

		return $className;
	}
}

/**
 * Create object of specified class.
 *
 * @version 3.4.0
 *
 * @param string $class  class that you want to create
 * @param array  $params array of arguments for class __construct function
 *
 * @return object new object of specified class
 */
if ( ! function_exists( 'woobewoo_pf_toe_create_obj' ) ) {
	function woobewoo_pf_toe_create_obj( $class, $params ) {
		$className = woobewoo_pf_toe_get_class_name( $class );
		$obj       = null;
		if ( class_exists( 'ReflectionClass' ) ) {
			$reflection = new ReflectionClass( $className );
			try {
				$obj = $reflection->newInstanceArgs( $params );
			} catch ( ReflectionException $e ) { // If class have no constructor
				$obj = $reflection->newInstanceArgs();
			}
		} else {
			$obj = new $className();
			call_user_func_array( array( $obj, '__construct' ), $params );
		}

		return $obj;
	}
}

/**
 * woobewoo_pf_json_encode_utf_normal.
 *
 * @version 3.4.0
 */
if ( ! function_exists( 'woobewoo_pf_json_encode_utf_normal' ) ) {
	function woobewoo_pf_json_encode_utf_normal( $value ) {
		if ( is_int( $value ) ) {
			return (string) $value;
		} elseif ( is_string( $value ) ) {
			$value   = str_replace(
				array( '\\', '/', '"', "\r", "\n", "\b", "\f", "\t" ),
				array( '\\\\', '\/', '\"', '\r', '\n', '\b', '\f', '\t' ),
				$value
			);
			$convmap = array( 0x80, 0xFFFF, 0, 0xFFFF );
			$result  = '';
			for ( $i = strlen( $value ) - 1; $i >= 0; $i-- ) {
				$mb_char = substr( $value, $i, 1 );
				$result  = $mb_char . $result;
			}

			return '"' . $result . '"';
		} elseif ( is_float( $value ) ) {
			return str_replace( ',', '.', $value );
		} elseif ( is_null( $value ) ) {
			return 'null';
		} elseif ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		} elseif ( is_array( $value ) ) {
			$with_keys = false;
			$n         = count( $value );
			for ( $i = 0, reset( $value ); $i < $n; $i++, next( $value ) ) {
				if ( key( $value ) !== $i ) {
					$with_keys = true;
					break;
				}
			}
		} elseif ( is_object( $value ) ) {
			$with_keys = true;
		} else {
			return '';
		}
		$result = array();
		if ( $with_keys ) {
			foreach ( $value as $key => $v ) {
				$result[] = woobewoo_pf_json_encode_utf_normal( (string) $key ) . ':' . woobewoo_pf_json_encode_utf_normal( $v );
			}

			return '{' . implode( ',', $result ) . '}';
		} else {
			foreach ( $value as $key => $v ) {
				$result[] = woobewoo_pf_json_encode_utf_normal( $v );
			}

			return '[' . implode( ',', $result ) . ']';
		}
	}
}

/**
 * Prepares the params values to store into db.
 *
 * @version 3.4.0
 *
 * @param array $d $_POST array
 *
 * @return array
 */
if ( ! function_exists( 'woobewoo_pf_prepare_params' ) ) {
	function woobewoo_pf_prepare_params( &$d = array(), &$options = array() ) {
		if ( ! empty( $d['params'] ) ) {
			if ( isset( $d['params']['options'] ) ) {
				$options = $d['params']['options'];
			}
			if ( is_array( $d['params'] ) ) {
				$params      = WooBeWoo_PF_Utils::jsonEncode( $d['params'] );
				$params      = str_replace( array( '\n\r', "\n\r", '\n', "\r", '\r', "\r" ), '<br />', $params );
				$params      = str_replace( array( '<br /><br />', '<br /><br /><br />' ), '<br />', $params );
				$d['params'] = $params;
			}
		} elseif ( isset( $d['params'] ) ) {
			$d['params']['attr']['class'] = '';
			$d['params']['attr']['id']    = '';
			$params                       = WooBeWoo_PF_Utils::jsonEncode( $d['params'] );
			$d['params']                  = $params;
		}
		if ( empty( $options ) ) {
			$options = array(
				'value' => array( 'EMPTY' ),
				'data'  => array(),
			);
		}
		if ( isset( $d['code'] ) ) {
			if ( '' == $d['code'] ) {
				$d['code'] = woobewoo_pf_prepare_field_code( $d['label'] ) . '_' . wp_rand( 0, 9999999 );
			}
		}

		return $d;
	}
}

/**
 * woobewoo_pf_prepare_field_code.
 *
 * @version 3.4.0
 */
if ( ! function_exists( 'woobewoo_pf_prepare_field_code' ) ) {
	function woobewoo_pf_prepare_field_code( $string ) {
		$string = preg_replace( '/[^a-zA-Z0-9\s]/', ' ', $string );
		$string = preg_replace( '/\s+/', ' ', $string );
		$string = preg_replace( '/ /', '', $string );

		$code = substr( $string, 0, 8 );
		$code = strtolower( $code );
		if ( '' == $code ) {
			$code = 'field_' . gmdate( 'dhis' );
		}

		return $code;
	}
}

/**
 * Recursive implode of array.
 *
 * @verson 3.4.0
 *
 * @param string $glue  imploder
 * @param array  $array array to implode
 *
 * @return string imploded array in string
 */
if ( ! function_exists( 'woobewoo_pf_recursive_implode' ) ) {
	function woobewoo_pf_recursive_implode( $glue, $array ) {
		$res   = '';
		$i     = 0;
		$count = count( $array );
		foreach ( $array as $el ) {
			$str = '';
			if ( is_array( $el ) ) {
				$str = woobewoo_pf_recursive_implode( '', $el );
			} else {
				$str = $el;
			}
			$res .= $str;
			if ( $i < ( $count - 1 ) ) {
				$res .= $glue;
			}
			++$i;
		}

		return $res;
	}
}

/**
 * woobewoo_pf_request.
 *
 * @version 3.4.0
 */
if ( ! function_exists( 'woobewoo_pf_request' ) ) {
	function woobewoo_pf_request() {
		$request = true;
		$uri     = (
		( isset( $_SERVER['REQUEST_URI'] ) && '' !== $_SERVER['REQUEST_URI'] )
			? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
			: ''
		);

		if ( '' === $uri ) {
			$request = false;
		} else {
			preg_match( '/\.png$|\.jpg$|\.ico$/', $uri, $matches );
			if ( ! empty( $matches ) ) {
				$request = false;
			}
		}

		return $request;
	}
}

/**
 * woobewoo_pf_install_base_msg.
 *
 * @version 3.4.0
 */
add_action( 'admin_notices', 'woobewoo_pf_install_base_msg' );
if ( ! function_exists( 'woobewoo_pf_install_base_msg' ) ) {
	function woobewoo_pf_install_base_msg() {
		if ( ! class_exists( 'WooBeWoo_PF_Frame' ) ) {
			return;
		}

		if ( apply_filters( 'woobewoo_pf_show_pro_notice', false ) ) {
			return;
		}
		if ( WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->get( 'start_indexing' ) == 2 ) {
			$plugName  = __( 'Product Filter by WBW', 'woo-product-filter' );

			echo '<div class="notice error is-dismissible"><p><strong>';
			printf(
				/* translators: %s: plugin name */
				esc_html__( 'The plugin %s started indexing the product database metadata. If you have a large database, this may take a while, but in the future it will significantly increase your filtering speed.', 'woo-product-filter' ),
				esc_html( $plugName )
			);
			echo '</strong></p></div>';
		} else {
			WooBeWoo_PF_Frame::_()->getModule( 'overview' )->getView()->showRestApiInfo();
		}
	}
}

/**
 * woobewoo_pf_deactivate.
 *
 * @version 3.4.0
 */
add_action( 'admin_init', 'woobewoo_pf_deactivate' );
if ( ! function_exists( 'woobewoo_pf_deactivate' ) ) {
	function woobewoo_pf_deactivate() {
		if ( ! class_exists( 'WooBeWoo_PF_Frame' ) ) {
			return;
		}
		$pathPro = woobewoo_pf_get_pro_full_path();
		if ( ! $pathPro || ! is_plugin_active( plugin_basename( $pathPro ) ) ) {
			return;
		}
		$pluginData = get_file_data( $pathPro, array( 'Version' => 'Version' ) );
		if ( ! version_compare( $pluginData['Version'], WPF_PRO_REQUIRES, '>=' ) ) {
			// Too old PRO: its modules can't be loaded by this version (see woobewoo_pf_pro_incompatible_notice()).
			return;
		}
		// PRO modules left inactive (e.g. by an older Free version) while PRO is active, compatible and licensed.
		if ( ! WooBeWoo_PF_Frame::_()->moduleActive( 'access' ) && woobewoo_pf_is_pro_license_active() ) {
			call_user_func_array( array( 'WooBeWoo_PF_Mod_Installer', 'activate' ), array( true ) );
		}
	}
}

/**
 * woobewoo_pf_get_pro_full_path.
 *
 * PRO main file path, for PRO >= 3.4.0 and for older PRO versions.
 *
 * @version 3.4.6
 * @since   3.4.6
 */
if ( ! function_exists( 'woobewoo_pf_get_pro_full_path' ) ) {
	function woobewoo_pf_get_pro_full_path() {
		if ( function_exists( 'woobeboo_pf_get_pro_plugin_full_path' ) ) {
			return woobeboo_pf_get_pro_plugin_full_path();
		}
		if ( function_exists( 'getProPlugFullPathWpf' ) ) {
			return getProPlugFullPathWpf();
		}
		return false;
	}
}

/**
 * woobewoo_pf_is_pro_license_active.
 *
 * @version 3.4.6
 * @since   3.4.6
 */
if ( ! function_exists( 'woobewoo_pf_is_pro_license_active' ) ) {
	function woobewoo_pf_is_pro_license_active() {
		$license = WooBeWoo_PF_Frame::_()->getModule( 'license' );
		$model   = $license ? $license->getModel() : null;
		return $model && method_exists( $model, 'isActive' ) && $model->isActive();
	}
}

/**
 * woobewoo_pf_pro_incompatible_notice.
 *
 * @version 3.4.6
 * @since   3.4.6
 */
add_action( 'admin_notices', 'woobewoo_pf_pro_incompatible_notice' );
if ( ! function_exists( 'woobewoo_pf_pro_incompatible_notice' ) ) {
	function woobewoo_pf_pro_incompatible_notice() {
		if ( ! current_user_can( 'activate_plugins' ) || ! class_exists( 'WooBeWoo_PF_Frame' ) ) {
			return;
		}
		$pathPro = woobewoo_pf_get_pro_full_path();
		if ( ! $pathPro || ! is_plugin_active( plugin_basename( $pathPro ) ) ) {
			return;
		}
		$pluginData = get_file_data( $pathPro, array( 'Version' => 'Version' ) );
		if ( version_compare( $pluginData['Version'], WPF_PRO_REQUIRES, '>=' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>';
		printf(
			/* translators: 1: PRO version, 2: Free version, 3: required PRO version */
			esc_html__( 'Product Filter by WBW: PRO version %1$s is not compatible with the Free (Base) version %2$s, so PRO features are disabled. Please update the PRO plugin to version %3$s or later.', 'woo-product-filter' ),
			esc_html( $pluginData['Version'] ),
			esc_html( WPF_VERSION ),
			esc_html( WPF_PRO_REQUIRES )
		);
		echo '</strong></p></div>';
	}
}

/**
 * woobewoo_pf_translate_string.
 *
 * @version 3.4.0
 */
if ( ! function_exists( 'woobewoo_pf_translate_string' ) ) {
	function woobewoo_pf_translate_string( $value, $name = '', $context = 'woo-product-filter', $register = null ) {
		if ( has_action( 'wpml_register_single_string' ) ) {
			// Each string needs its own name: with an empty name WPML/Polylang keep only the last string
			// registered and Polylang copies translations between them. The lookup below uses the value as name.
			if ( '' === (string) $name ) {
				$name = $value;
			}
			// Register the string only when a filter is saved or in wp-admin screens, never on
			// front-end page views or AJAX filtering (Polylang/WPML write to the DB on each call).
			if ( null === $register ) {
				$register = is_admin() && ! wp_doing_ajax();
			}
			if ( $register ) {
				do_action(
					'wpml_register_single_string', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
					$context,
					$name,
					$value
				);
			}

			// Get the translated value.
			return apply_filters(
				'wpml_translate_single_string', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				$value,
				$context,
				$value
			);
		}

		return $value;
	}
}
