<?php
/**
 * Product Filter by WBW - Config
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

define( 'WPF_WPLANG', ( ! defined( 'WPLANG' ) || WPLANG == '' ? 'en_GB' : WPLANG ) );

if ( ! defined( 'WPF_DS' ) ) {
	define( 'WPF_DS', DIRECTORY_SEPARATOR );
}

/**
 * 4.0.0: paths come from this file's real location, never from WP_PLUGIN_DIR + an assumed folder
 * name, so the plugin keeps working when the folder is renamed by an upload
 * (for example woo-product-filter-4.0.0) or lives behind a symlink.
 */
define( 'WPF_PLUG_NAME', basename( __DIR__ ) );
define( 'WPF_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPF_TPL_DIR', WPF_DIR . 'tpl' . WPF_DS );
define( 'WPF_CLASSES_DIR', WPF_DIR . 'classes' . WPF_DS );
define( 'WPF_TABLES_DIR', WPF_CLASSES_DIR . 'tables' . WPF_DS );
define( 'WPF_HELPERS_DIR', WPF_CLASSES_DIR . 'helpers' . WPF_DS );
define( 'WPF_LANG_DIR', WPF_DIR . 'languages' . WPF_DS );
define( 'WPF_IMG_DIR', WPF_DIR . 'img' . WPF_DS );
define( 'WPF_TEMPLATES_DIR', WPF_DIR . 'templates' . WPF_DS );
define( 'WPF_MODULES_DIR', WPF_DIR . 'modules' . WPF_DS );
define( 'WPF_FILES_DIR', WPF_DIR . 'files' . WPF_DS );

define( 'WPF_PLUGINS_URL', plugin_dir_url( __FILE__ ) );
if ( ! defined( 'WPF_SITE_URL' ) ) {
	define( 'WPF_SITE_URL', get_bloginfo( 'wpurl' ) . '/' );
}
define( 'WPF_JS_PATH', WPF_PLUGINS_URL . 'js/' );
define( 'WPF_CSS_PATH', WPF_PLUGINS_URL . 'css/' );
define( 'WPF_IMG_PATH', WPF_PLUGINS_URL . 'img/' );
define( 'WPF_MODULES_PATH', WPF_PLUGINS_URL . 'modules/' );
define( 'WPF_TEMPLATES_PATH', WPF_PLUGINS_URL . 'templates/' );
define( 'WPF_JS_DIR', WPF_DIR . 'js/' );

define( 'WPF_URL', WPF_SITE_URL );

define( 'WPF_LOADER_IMG', WPF_IMG_PATH . 'loading.gif' );
define( 'WPF_TIME_FORMAT', 'H:i:s' );
define( 'WPF_DATE_DL', '/' );
define( 'WPF_DATE_FORMAT', 'm/d/Y' );
define( 'WPF_DATE_FORMAT_HIS', 'm/d/Y (' . WPF_TIME_FORMAT . ')' );
define( 'WPF_DATE_FORMAT_JS', 'mm/dd/yy' );
define( 'WPF_DATE_FORMAT_CONVERT', '%m/%d/%Y' );
define( 'WPF_WPDB_PREF', $wpdb->prefix );
define( 'WPF_DB_PREF', 'wpf_' );
define( 'WPF_MAIN_FILE', 'woo-product-filter.php' );

define( 'WPF_DEFAULT', 'default' );
define( 'WPF_CURRENT', 'current' );

define( 'WPF_EOL', "\n" );

define( 'WPF_PLUGIN_INSTALLED', true );
define( 'WPF_VERSION', '4.0.0-dev-20261105-1940' );
define( 'WPF_PRO_REQUIRES', '4.0.0' ); // Pro 4.0 requires Free 4.0 (new modules, version history table, drawer settings).
/**
 * 4.0.0 platform modules shipped in Free. Bump WPF_MODULES_REV when a module is added so the
 * modules table is synced on the next request (see WooBeWoo_PF_Installer_Db_Updater::syncModules()).
 */
define( 'WPF_MODULES_REV', '4.0.0-platform-4' );
define( 'WPF_FREE_MODULES', 'setup,speed,help,links,schema,abilities,cli,storeapi,builders,importers,brands,conditions,measures,results,usage' );
/** Free registers the read abilities woobewoo/facets-list and woobewoo/products-filter (Pro skips them). */
define( 'WOOBEWOO_PF_ABILITIES', true );
define( 'WPF_USER', 'user' );

define( 'WPF_CLASS_PREFIX', 'WooBeWoo_PF_' );
define( 'WPF_FREE_VERSION', false );
define( 'WPF_TEST_MODE', false ); // Shipped builds never show raw database errors (see WooBeWoo_PF_Table::_addError).

define( 'WPF_SUCCESS', 'Success' );
define( 'WPF_FAILED', 'Failed' );
define( 'WPF_ERRORS', 'wpfErrors' );

define( 'WPF_ADMIN', 'admin' );
define( 'WPF_LOGGED', 'logged' );
define( 'WPF_GUEST', 'guest' );

define( 'WPF_ALL', 'all' );

define( 'WPF_METHODS', 'methods' );
define( 'WPF_USERLEVELS', 'userlevels' );
define( 'WPF_LANG_CODE', 'woo-product-filter' );

/**
 * Framework instance code.
 */
define( 'WPF_CODE', 'wpf' );

/**
 * Plugin name.
 */
define( 'WPF_WP_PLUGIN_NAME', 'WBW Product Filter' );
define( 'WPF_WP_PLUGIN_URL', 'woobewoo.com' );

/**
 * Custom defined for plugin.
 *
 * @version 3.1.7
 */
define( 'WPF_SHORTCODE', 'wpf-filters' );
define( 'WPF_SHORTCODE_PRODUCTS', 'wpf-products' );
define( 'WPF_SHORTCODE_SELECTED_FILTERS', 'wpf-selected-filters' );
define( 'WPF_COMMON', plugin_dir_path( __FILE__ ) . 'modules/templates/common-part/' );
