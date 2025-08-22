<?php
/**
 * Product Filter by WBW - Config
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

if (!defined('WPLANG') || WPLANG == '') {
	define('WPF_WPLANG', 'en_GB');
} else {
	define('WPF_WPLANG', WPLANG);
}

if (!defined('DS')) {
	define('DS', DIRECTORY_SEPARATOR);
}

define('WPF_PLUG_NAME', basename(dirname(__FILE__)));
define('WPF_DIR', WP_PLUGIN_DIR . DS . WPF_PLUG_NAME . DS);
define('WPF_TPL_DIR', WPF_DIR . 'tpl' . DS);
define('WPF_CLASSES_DIR', WPF_DIR . 'classes' . DS);
define('WPF_TABLES_DIR', WPF_CLASSES_DIR . 'tables' . DS);
define('WPF_HELPERS_DIR', WPF_CLASSES_DIR . 'helpers' . DS);
define('WPF_LANG_DIR', WPF_DIR . 'languages' . DS);
define('WPF_IMG_DIR', WPF_DIR . 'img' . DS);
define('WPF_TEMPLATES_DIR', WPF_DIR . 'templates' . DS);
define('WPF_MODULES_DIR', WPF_DIR . 'modules' . DS);
define('WPF_FILES_DIR', WPF_DIR . 'files' . DS);
define('WPF_ADMIN_DIR', ABSPATH . 'wp-admin' . DS);

define('WPF_PLUGINS_URL', plugins_url());
if (!defined('WPF_SITE_URL')) {
	define('WPF_SITE_URL', get_bloginfo('wpurl') . '/');
}
define('WPF_JS_PATH', WPF_PLUGINS_URL . '/' . WPF_PLUG_NAME . '/js/');
define('WPF_CSS_PATH', WPF_PLUGINS_URL . '/' . WPF_PLUG_NAME . '/css/');
define('WPF_IMG_PATH', WPF_PLUGINS_URL . '/' . WPF_PLUG_NAME . '/img/');
define('WPF_MODULES_PATH', WPF_PLUGINS_URL . '/' . WPF_PLUG_NAME . '/modules/');
define('WPF_TEMPLATES_PATH', WPF_PLUGINS_URL . '/' . WPF_PLUG_NAME . '/templates/');
define('WPF_JS_DIR', WPF_DIR . 'js/');

define('WPF_URL', WPF_SITE_URL);

define('WPF_LOADER_IMG', WPF_IMG_PATH . 'loading.gif');
define('WPF_TIME_FORMAT', 'H:i:s');
define('WPF_DATE_DL', '/');
define('WPF_DATE_FORMAT', 'm/d/Y');
define('WPF_DATE_FORMAT_HIS', 'm/d/Y (' . WPF_TIME_FORMAT . ')');
define('WPF_DATE_FORMAT_JS', 'mm/dd/yy');
define('WPF_DATE_FORMAT_CONVERT', '%m/%d/%Y');
define('WPF_WPDB_PREF', $wpdb->prefix);
define('WPF_DB_PREF', 'wpf_');
define('WPF_MAIN_FILE', 'woo-product-filter.php');

define('WPF_DEFAULT', 'default');
define('WPF_CURRENT', 'current');

define('WPF_EOL', "\n");

define('WPF_PLUGIN_INSTALLED', true);
define('WPF_VERSION', '2.9.6');
define('WPF_PRO_REQUIRES', '2.0.0');
define('WPF_USER', 'user');

define('WPF_CLASS_PREFIX', 'wpfc');
define('WPF_FREE_VERSION', false);
define('WPF_TEST_MODE', true);

define('WPF_SUCCESS', 'Success');
define('WPF_FAILED', 'Failed');
define('WPF_ERRORS', 'wpfErrors');

define('WPF_ADMIN', 'admin');
define('WPF_LOGGED', 'logged');
define('WPF_GUEST', 'guest');

define('WPF_ALL', 'all');

define('WPF_METHODS', 'methods');
define('WPF_USERLEVELS', 'userlevels');
define('WPF_LANG_CODE', 'woo-product-filter');

/**
 * Framework instance code.
 */
define('WPF_CODE', 'wpf');

/**
 * Plugin name.
 */
define('WPF_WP_PLUGIN_NAME', 'WBW Product Filter');
define('WPF_WP_PLUGIN_URL', 'woobewoo.com');

/**
 * Custom defined for plugin.
 */
define('WPF_SHORTCODE', 'wpf-filters');
define('WPF_SHORTCODE_PRODUCTS', 'wpf-products');
define('WPF_SHORTCODE_SELECTED_FILTERS', 'wpf-selected-filters');
