<?php
/**
 * Product Filter by WBW - Functions
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Set first letter in a string as UPPERCASE.
 *
 * @version 3.3.0
 *
 * @param string $str string to modify
 *
 * @return string string with first Uppercase letter
 */
if ( ! function_exists( 'strFirstUpWpf' ) ) {
	function strFirstUpWpf( $str ) {
		return ucfirst( strtolower( $str ) );
	}
}

/**
 * dateToTimestampWpf.
 */
if ( ! function_exists( 'dateToTimestampWpf' ) ) {
	function dateToTimestampWpf( $date ) {
		if ( empty( $a ) ) {
			return false;
		}
		$a = explode( WPF_DATE_DL, $date );

		return mktime( 0, 0, 0, $a[1], $a[0], $a[2] );
	}
}

/**
 * importClassWpf.
 *
 * @version 3.3.2
 */
if ( ! function_exists( 'importClassWpf' ) ) {
	function importClassWpf( $class, $path = '' ) {
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
 * @version 3.3.2
 *
 * @param string $class preferred class name
 *
 * @return string existing class name
 */
if ( ! function_exists( 'toeGetClassNameWpf' ) ) {
	function toeGetClassNameWpf( $class ) {
		if ( class_exists( $class . strFirstUpWpf( WPF_CODE ) ) ) {
			$className = $class . strFirstUpWpf( WPF_CODE );
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
 * @param string $class  class that you want to create
 * @param array  $params array of arguments for class __construct function
 *
 * @return object new object of specified class
 */
if ( ! function_exists( 'toeCreateObjWpf' ) ) {
	function toeCreateObjWpf( $class, $params ) {
		$className = toeGetClassNameWpf( $class );
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
 * jsonEncodeUTFnormalWpf.
 */
if ( ! function_exists( 'jsonEncodeUTFnormalWpf' ) ) {
	function jsonEncodeUTFnormalWpf( $value ) {
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
				$result[] = jsonEncodeUTFnormalWpf( (string) $key ) . ':' . jsonEncodeUTFnormalWpf( $v );
			}

			return '{' . implode( ',', $result ) . '}';
		} else {
			foreach ( $value as $key => $v ) {
				$result[] = jsonEncodeUTFnormalWpf( $v );
			}

			return '[' . implode( ',', $result ) . ']';
		}
	}
}

/**
 * Prepares the params values to store into db.
 *
 * @version 3.1.8
 *
 * @param array $d $_POST array
 *
 * @return array
 */
if ( ! function_exists( 'prepareParamsWpf' ) ) {
	function prepareParamsWpf( &$d = array(), &$options = array() ) {
		if ( ! empty( $d['params'] ) ) {
			if ( isset( $d['params']['options'] ) ) {
				$options = $d['params']['options'];
			}
			if ( is_array( $d['params'] ) ) {
				$params      = UtilsWpf::jsonEncode( $d['params'] );
				$params      = str_replace( array( '\n\r', "\n\r", '\n', "\r", '\r', "\r" ), '<br />', $params );
				$params      = str_replace( array( '<br /><br />', '<br /><br /><br />' ), '<br />', $params );
				$d['params'] = $params;
			}
		} elseif ( isset( $d['params'] ) ) {
			$d['params']['attr']['class'] = '';
			$d['params']['attr']['id']    = '';
			$params                       = UtilsWpf::jsonEncode( $d['params'] );
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
				$d['code'] = prepareFieldCodeWpf( $d['label'] ) . '_' . wp_rand( 0, 9999999 );
			}
		}

		return $d;
	}
}

/**
 * prepareFieldCodeWpf.
 */
if ( ! function_exists( 'prepareFieldCodeWpf' ) ) {
	function prepareFieldCodeWpf( $string ) {
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
 * @param string $glue  imploder
 * @param array  $array array to implode
 *
 * @return string imploded array in string
 */
if ( ! function_exists( 'recImplodeWpf' ) ) {
	function recImplodeWpf( $glue, $array ) {
		$res   = '';
		$i     = 0;
		$count = count( $array );
		foreach ( $array as $el ) {
			$str = '';
			if ( is_array( $el ) ) {
				$str = recImplodeWpf( '', $el );
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
 * trueRequestWpf.
 *
 * @version 3.1.8
 */
if ( ! function_exists( 'trueRequestWpf' ) ) {
	function trueRequestWpf() {
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
 * woofilterInstallBaseMsg.
 *
 * @version 3.3.2
 */
add_action( 'admin_notices', 'woofilterInstallBaseMsg' );
if ( ! function_exists( 'woofilterInstallBaseMsg' ) ) {
	function woofilterInstallBaseMsg() {
		if ( ! class_exists( 'WooBeWoo_PF_Frame' ) ) {
			return;
		}

		if ( apply_filters( 'woobee_show_pro_notice', false ) ) {
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
 * woofilterProDeactivate.
 *
 * @version 3.3.2
 */
add_action( 'admin_init', 'woofilterProDeactivate' );
if ( ! function_exists( 'woofilterProDeactivate' ) ) {
	function woofilterProDeactivate() {
		if ( class_exists( 'WooBeWoo_PF_Frame' ) && function_exists( 'getProPlugFullPathWpf' ) ) {
			$pathPro   = getProPlugFullPathWpf();
			$proPlugin = plugin_basename( $pathPro );
			if ( is_plugin_active( $proPlugin ) ) {
				$pluginData  = get_file_data( $pathPro, array( 'Version' => 'Version' ) );
				$isProActive = WooBeWoo_PF_Frame::_()->moduleActive( 'access' );
				if ( ! version_compare( $pluginData['Version'], WPF_PRO_REQUIRES, '>=' ) ) {
					if ( $isProActive ) {
						call_user_func_array( array( 'ModInstallerWpf', 'deactivate' ), array( array( 'license' ) ) );
					}
				} elseif ( ! $isProActive ) {
					call_user_func_array( array( 'ModInstallerWpf', 'activate' ), array( true ) );
				}
			}
		}
	}
}

/**
 * wpf_translate_string.
 *
 * @version 3.3.1
 */
if ( ! function_exists( 'wpf_translate_string' ) ) {
	function wpf_translate_string( $value, $name = '', $context = 'woo-product-filter' ) {
		if ( has_action( 'wpml_register_single_string' ) ) {
			// Register the string.
			do_action(
				'wpml_register_single_string',
				$context,
				$name,
				$value
			);

			// Get the translated value.
			return apply_filters(
				'wpml_translate_single_string',
				$value,
				$context,
				$value
			);
		}

		return $value;
	}
}
