<?php
/**
 * Product Filter by WBW - FrameWpf Class
 *
 * @author  woobewoo
 */

defined( 'ABSPATH' ) || exit;

class FrameWpf {

	private $_modules            = array();
	private $_tables             = array();
	private $_allModules         = array();
	/**
	 * Uses to know if we are on one of the plugin pages
	 */
	private $_inPlugin           = false;
	/**
	 * Array to hold all scripts and add them in one time in addScripts method
	 */
	private $_scripts            = array();
	private $_scriptsInitialized = false;
	private $_styles             = array();
	private $_stylesInitialized  = false;
	private $_useFootAssets      = false;

	private $_scriptsVars        = array();
	private $_mod                = '';
	private $_action             = '';
	private $_proVersion         = null;
	/**
	 * Object with result of executing non-ajax module request
	 */
	private $_res                = null;

	public function __construct() {
		$this->_res = toeCreateObjWpf('response', array());
	}

	public static function getInstance() {
		static $instance;
		if (!$instance) {
			$instance = new FrameWpf();
		}
		return $instance;
	}

	public static function _() {
		return self::getInstance();
	}

	public function parseRoute() {
		// Check plugin
		$pl = ReqWpf::getVar('pl');
		if (WPF_CODE == $pl) {
			$mod = ReqWpf::getMode();
			if ($mod) {
				$this->_mod = $mod;
			}
			$action = ReqWpf::getVar('action');
			if ($action) {
				$this->_action = $action;
			}
		}
	}

	public function setMod( $mod ) {
		$this->_mod = $mod;
	}

	public function getMod() {
		return $this->_mod;
	}

	public function setAction( $action ) {
		$this->_action = $action;
	}

	public function getAction() {
		return $this->_action;
	}

	private function _checkPromoModName( $activeModules ) {
		foreach ($activeModules as $i => $m) {
			if ('supsystic_promo' == $m['code']) { // Well, rename it ;)
				$activeModules[$i]['code']  = 'promo';
				$activeModules[$i]['label'] = 'promo';
				DbWpf::query("UPDATE `@__modules` SET code = 'promo', label = 'promo' WHERE code = 'supsystic_promo'");
			}
		}
		return $activeModules;
	}

	protected function _extractModules() {
		$activeModules = $this->getTable('modules')
				->innerJoin( $this->getTable('modules_type'), 'type_id' )
				->get($this->getTable('modules')->alias() . '.*, ' . $this->getTable('modules_type')->alias() . '.label as type_name');
		$activeModules = $this->_checkPromoModName($activeModules);
		if ($activeModules) {
			foreach ($activeModules as $m) {
				$code = $m['code'];
				$moduleLocationDir = WPF_MODULES_DIR;
				if (!empty($m['ex_plug_dir'])) {
					$moduleLocationDir = UtilsWpf::getExtModDir( $m['ex_plug_dir'] );
				}
				if (is_dir($moduleLocationDir . $code)) {
					$this->_allModules[$m['code']] = 1;
					if ((bool) $m['active']) {
						if (!class_exists($code . strFirstUpWpf(WPF_CODE))) {
							if (file_exists($moduleLocationDir . $code . DS . 'mod.php')) {
								require $moduleLocationDir . $code . DS . 'mod.php';
							}
						}

						$moduleClass = toeGetClassNameWpf($code);
						if (class_exists($moduleClass)) {
							$this->_modules[$code] = new $moduleClass($m);
							if (is_dir($moduleLocationDir . $code . DS . 'tables')) {
								$this->_extractTables($moduleLocationDir . $code . DS . 'tables' . DS);
							}
						}
					}
				}
			}
			if (isset($this->_modules['templates'])) {
				$m = $this->_modules['templates'];
				unset($this->_modules['templates']);
				$this->_modules['templates'] = $m;
			}
		}
	}

	protected function _initModules() {
		if (!empty($this->_modules)) {
			foreach ($this->_modules as $mod) {
				 $mod->init();
			}
		}
	}

	public function init() {
		ReqWpf::init();
		$this->_extractTables();
		$this->_extractModules();

		$this->_initModules();

		DispatcherWpf::doAction('afterModulesInit');

		ModInstallerWpf::checkActivationMessages();

		$this->_execModules();

		$addAssetsAction = $this->usePackAssets() && !is_admin() ? 'wp_footer' : 'init';

		add_action($addAssetsAction, array($this, 'addScripts'));
		add_action($addAssetsAction, array($this, 'addStyles'));
		global $langOK;
		register_activation_hook(WPF_DIR . DS . WPF_MAIN_FILE, array('UtilsWpf', 'activatePlugin')); //See classes/install.php file
		register_uninstall_hook(WPF_DIR . DS . WPF_MAIN_FILE, array('UtilsWpf', 'deletePlugin'));
		register_deactivation_hook(WPF_DIR . DS . WPF_MAIN_FILE, array( 'UtilsWpf', 'deactivatePlugin' ) );

		add_action('init', array($this, 'connectLang'));
		add_action('after_plugin_row_woofilter-pro/woofilter-pro.php', array($this, 'pluginRow'), 5, 3);
		add_filter('the_content', array('WoofiltersWpf', 'getProductsShortcode'), -99999);
	}

	public function pluginRow( $plugin_file, $plugin_data, $status ) {
		if ( !version_compare($plugin_data['Version'], WPF_PRO_REQUIRES, '>=') ) {
			$colspan = version_compare($GLOBALS['wp_version'], '5.5', '<') ? 3 : 4;
			$active = is_plugin_active($plugin_file) ? ' active' : '';
			?>
			<style>
				.plugins tr[data-slug="woo-product-filter-pro"] td,
				.plugins tr[data-slug="woo-product-filter-pro"] th {
					box-shadow:none;
				}
				<?php if ( isset($plugin_data['update']) && !empty($plugin_data['update']) ) { ?>
				.plugins tr.wpf-pro-plugin-tr td{
					box-shadow:none !important;
				}
				.plugins wpf-pro-plugin-tr .update-message{
					margin-bottom:0;
				}
				<?php } ?>
			</style>
			<tr class="plugin-update-tr wpf-pro-plugin-tr<?php echo esc_attr($active); ?>">
				<td colspan="<?php echo esc_attr($colspan); ?>" class="plugin-update colspanchange">
					<div class="update-message notice inline notice-error notice-alt">
						<p>
						<?php
							/* translators: 1: plugin name 2: plugin version */
							echo sprintf(esc_html__('Current version of Free (Base) plugin %1$s requires version of WBW Product Filter PRO plugin at least %2$s.', 'woo-product-filter'), esc_html__('Product Filter by WBW', 'woo-product-filter'), esc_html(WPF_PRO_REQUIRES));
						?>
						</p>
					</div>
				</td>
			</tr>
			<?php
		}
	}

	public function connectLang() {
		global $langOK;
		$langOK = load_plugin_textdomain('woo-product-filter', false, WPF_PLUG_NAME . '/languages/');
	}

	/**
	 * Check permissions for action in controller by $code and made corresponding action.
	 *
	 * @param string $code Code of controller that need to be checked
	 * @param string $action Action that need to be checked
	 * @return bool true if ok, else - should exit from application
	 */
	public function checkPermissions( $code, $action ) {
		if ($this->havePermissions($code, $action)) {
			return true;
		} else {
			exit(esc_html_e('You have no permissions to view this page', 'woo-product-filter'));
		}
	}

	/**
	 * Check permissions for action in controller by $code.
	 *
	 * @param string $code Code of controller that need to be checked
	 * @param string $action Action that need to be checked
	 * @return bool true if ok, else - false
	 */
	public function havePermissions( $code, $action ) {
		$res = true;
		$mod = $this->getModule($code);
		$action = strtolower($action);
		if ($mod) {
			$permissions = $mod->getController()->getPermissions();
			if (!empty($permissions)) { // Special permissions
				if (isset($permissions[WPF_METHODS]) && !empty($permissions[WPF_METHODS])) {
					foreach ($permissions[WPF_METHODS] as $method => $permissions) {   // Make case-insensitive
						$permissions[WPF_METHODS][strtolower($method)] = $permissions;
					}
					if (array_key_exists($action, $permissions[WPF_METHODS])) {        // Permission for this method exists
						$currentUserPosition = self::_()->getModule('user')->getCurrentUserPosition();
						if (
							(
								is_array($permissions[ WPF_METHODS ][ $action ] ) &&
								!in_array($currentUserPosition, $permissions[ WPF_METHODS ][ $action ])
							) ||
							(
								!is_array($permissions[ WPF_METHODS ][ $action ]) &&
								$permissions[WPF_METHODS][$action] != $currentUserPosition
							)
						) {
							$res = false;
						}
					}
				}
				if (isset($permissions[WPF_USERLEVELS]) && !empty($permissions[WPF_USERLEVELS])) {
					$currentUserPosition = self::_()->getModule('user')->getCurrentUserPosition();
					// For multi-sites network admin role is undefined, let's do this here
					if (is_multisite() && is_admin() && is_super_admin()) {
						$currentUserPosition = WPF_ADMIN;
					}
					foreach ($permissions[WPF_USERLEVELS] as $userlevel => $methods) {
						if (is_array($methods)) {
							$lowerMethods = array_map('strtolower', $methods);           // Make case-insensitive
							if (in_array($action, $lowerMethods)) {                      // Permission for this method exists
								if ($currentUserPosition != $userlevel) {
									$res = false;
								}
								break;
							}
						} else {
							$lowerMethod = strtolower($methods);             // Make case-insensitive
							if ($lowerMethod == $action) {                   // Permission for this method exists
								if ($currentUserPosition != $userlevel) {
									$res = false;
								}
								break;
							}
						}
					}
				}
			}
			if ($res) { // Additional check for nonces
				$noncedMethods = $mod->getController()->getNoncedMethods();
				if (!empty($noncedMethods)) {
					$noncedMethods = array_map('strtolower', $noncedMethods);
					if (in_array($action, $noncedMethods)) {
						$nonce = isset($_REQUEST['_wpnonce']) ? sanitize_text_field($_REQUEST['_wpnonce']) : ReqWpf::getVar('_wpnonce');
						if (!wp_verify_nonce( $nonce, $action )) {
							die();
						}
					}
				}
			}
		}
		return $res;
	}

	public function getRes() {
		return $this->_res;
	}

	public function execAfterWpInit() {
		$this->_doExec();
	}

	/**
	 * Check if method for module require some special permission. We can detect users permissions only after wp init action was done.
	 */
	protected function _execOnlyAfterWpInit() {
		$res = false;
		$mod = $this->getModule( $this->_mod );
		$action = strtolower( $this->_action );
		if ($mod) {
			$permissions = $mod->getController()->getPermissions();
			if (!empty($permissions)) {  // Special permissions
				if (isset($permissions[WPF_METHODS]) && !empty($permissions[WPF_METHODS])) {
					foreach ($permissions[WPF_METHODS] as $method => $permissions) {   // Make case-insensitive
						$permissions[WPF_METHODS][strtolower($method)] = $permissions;
					}
					if (array_key_exists($action, $permissions[WPF_METHODS])) {        // Permission for this method exists
						$res = true;
					}
				}
				if (isset($permissions[WPF_USERLEVELS]) && !empty($permissions[WPF_USERLEVELS])) {
					$res = true;
				}
			}
		}
		return $res;
	}

	protected function _execModules() {
		if ($this->_mod) {
			// If module exist and is active
			$mod = $this->getModule($this->_mod);
			if ($mod && !empty($this->_action)) {
				if ($this->_execOnlyAfterWpInit()) {
					add_action('init', array($this, 'execAfterWpInit'));
				} else {
					$this->_doExec();
				}
			}
		}
	}

	protected function _doExec() {
		$mod = $this->getModule($this->_mod);
		if ($mod && $this->checkPermissions($this->_mod, $this->_action)) {
			switch (ReqWpf::getVar('reqType')) {
				case 'ajax':
					add_action('wp_ajax_'        . $this->_action, array($mod->getController(), $this->_action));
					add_action('wp_ajax_nopriv_' . $this->_action, array($mod->getController(), $this->_action));
					break;
				default:
					$this->_res = $mod->exec($this->_action);
					break;
			}
		}
	}

	protected function _extractTables( $tablesDir = WPF_TABLES_DIR ) {
		$mDirHandle = opendir($tablesDir);
		while ( ( $file = readdir($mDirHandle) ) !== false ) {
			if ( is_file($tablesDir . $file) && ( '.' != $file ) && ( '..' != $file ) && strpos($file, '.php') ) {
				$this->_extractTable( str_replace('.php', '', $file), $tablesDir );
			}
		}
	}

	protected function _extractTable( $tableName, $tablesDir = WPF_TABLES_DIR ) {
		if (!class_exists('noClassNameHere')) {
			if (file_exists($tablesDir . $tableName . '.php')) {
				require $tablesDir . $tableName . '.php';
			}
		}
		$this->_tables[$tableName] = TableWpf::_($tableName);
	}

	/**
	 * Public alias for _extractTables method.
	 *
	 * @see _extractTables
	 */
	public function extractTables( $tablesDir ) {
		if (!empty($tablesDir)) {
			$this->_extractTables($tablesDir);
		}
	}

	public function exec() {
		//deprecated
	}

	public function getTables() {
		return $this->_tables;
	}

	/**
	 * Return table by name.
	 *
	 * @param string $tableName table name in database
	 * @return object table
	 * @example FrameWpf::_()->getTable('products')->getAll()
	 */
	public function getTable( $tableName ) {
		if (empty($this->_tables[$tableName])) {
			$this->_extractTable($tableName);
		}
		return $this->_tables[$tableName];
	}

	public function getModules( $filter = array() ) {
		$res = array();
		if (empty($filter)) {
			$res = $this->_modules;
		} else {
			foreach ($this->_modules as $code => $mod) {
				if (isset($filter['type'])) {
					if (is_numeric($filter['type']) && $filter['type'] == $mod->getTypeID()) {
						$res[$code] = $mod;
					} elseif ($filter['type'] == $mod->getType()) {
						$res[$code] = $mod;
					}
				}
			}
		}
		return $res;
	}

	public function getModule( $code ) {
		return ( isset($this->_modules[$code]) ? $this->_modules[$code] : null );
	}

	public function inPlugin() {
		return $this->_inPlugin;
	}

	public function usePackAssets() {
		if (!$this->_useFootAssets && $this->getModule('options') && $this->getModule('options')->get('foot_assets')) {
			$this->_useFootAssets = true;
		}
		return $this->_useFootAssets;
	}

	/**
	 * Push data to script array to use it all in addScripts method.
	 *
	 * @see wp_enqueue_script definition
	 */
	public function addScript( $handle, $src = '', $deps = array(), $ver = false, $in_footer = false, $vars = array() ) {
		$src = empty($src) ? $src : UriWpf::_($src);
		if (!$ver) {
			$ver = WPF_VERSION;
		}
		if ($this->_scriptsInitialized) {
			wp_enqueue_script($handle, $src, $deps, $ver, $in_footer);
		} else {
			$this->_scripts[] = array(
				'handle'    => $handle,
				'src'       => $src,
				'deps'      => $deps,
				'ver'       => $ver,
				'in_footer' => $in_footer,
				'vars'      => $vars,
			);
		}
	}

	/**
	 * Add all scripts from _scripts array to wordpress.
	 */
	public function addScripts() {
		if (!empty($this->_scripts)) {
			foreach ($this->_scripts as $s) {

				if ( ! function_exists( 'is_plugin_active' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}

				$enqueue = true;

				// if the oxygen plugin is activated then check if the script is already registered
				if ( is_plugin_active( 'oxygen/functions.php' ) && 'jquery-ui-autocomplete' === $s['handle'] ) {
					$wp_scripts = wp_scripts();
					if ( isset( $wp_scripts->registered[ $s['handle'] ] ) ) {
						$enqueue = false;
					}
				}

				if ( $enqueue ) {
					wp_enqueue_script( $s['handle'], $s['src'], $s['deps'], $s['ver'], $s['in_footer'] );
				}

				if ($s['vars'] || isset($this->_scriptsVars[$s['handle']])) {
					$vars = array();
					if ($s['vars']) {
						$vars = $s['vars'];
					}
					if ($this->_scriptsVars[$s['handle']]) {
						$vars = array_merge($vars, $this->_scriptsVars[$s['handle']]);
					}
					if ($vars) {
						foreach ($vars as $k => $v) {
							if ( is_array( $v ) ) {
								wp_localize_script( $s['handle'], $k, $v );
							}
						}
					}
				}
			}
		}
		$this->_scriptsInitialized = true;
	}

	public function addJSVar( $script, $name, $val ) {
		if ($this->_scriptsInitialized) {
			if ( is_array( $val ) ) {
				wp_localize_script( $script, $name, $val );
			} else {
				$code = "var {$name} = '{$val}';";
				wp_add_inline_script( $script, $code, 'before' );
			}
		} else {
			$this->_scriptsVars[$script][$name] = $val;
		}
	}

	public function addStyle( $handle, $src = false, $deps = array(), $ver = false, $media = 'all' ) {
		$src = empty($src) ? $src : UriWpf::_($src);
		if (!$ver) {
			$ver = WPF_VERSION;
		}
		if ($this->_stylesInitialized) {
			wp_enqueue_style($handle, $src, $deps, $ver, $media);
		} else {
			$this->_styles[] = array(
				'handle' => $handle,
				'src'    => $src,
				'deps'   => $deps,
				'ver'    => $ver,
				'media'  => $media,
			);
		}
	}

	public function addStyles() {
		if (!empty($this->_styles)) {
			foreach ($this->_styles as $s) {
				wp_enqueue_style($s['handle'], $s['src'], $s['deps'], $s['ver'], $s['media']);
			}
		}
		$this->_stylesInitialized = true;
	}

	//Very interesting thing going here.............
	public function loadPlugins() {
		require_once ABSPATH . 'wp-includes/pluggable.php';
	}

	public function loadWPSettings() {
		require_once ABSPATH . 'wp-settings.php';
	}

	public function loadLocale() {
		require_once ABSPATH . 'wp-includes/locale.php';
	}

	public function moduleActive( $code ) {
		return isset($this->_modules[$code]);
	}

	public function moduleExists( $code ) {
		if ($this->moduleActive($code)) {
			return true;
		}
		return isset($this->_allModules[$code]);
	}

	public function isTplEditor() {
		$tplEditor = ReqWpf::getVar('tplEditor');
		return (bool) $tplEditor;
	}

	/**
	 * This is custom method for each plugin and should be modified if you create copy from this instance.
	 */
	public function isAdminPlugOptsPage() {
		$page = ReqWpf::getVar('page');
		if (
			is_admin() &&
			!empty($page) &&
			is_string($page) &&
			strpos($page, self::_()->getModule('adminmenu')->getMainSlug()) !== false
		) {
			return true;
		}
		return false;
	}

	public function isAdminPlugPage() {
		if ($this->isAdminPlugOptsPage()) {
			return true;
		}
		return false;
	}

	public function licenseDeactivated() {
		return ( !$this->getModule('license') && $this->moduleExists('license') );
	}

	public function savePluginActivationErrors() {
		update_option(WPF_CODE . '_plugin_activation_errors', ob_get_contents());
	}

	public function getActivationErrors() {
		return get_option(WPF_CODE . '_plugin_activation_errors');
	}

	public function isPro() {
		return $this->moduleExists('license') && $this->getModule('license') && $this->getModule('access');
	}

	public function proVersionCompare( $requires, $compare = '>', $notPro = true ) {
		if ( is_null( $this->_proVersion ) ) {
			if ( $this->isPro() && function_exists( 'getProPlugFullPathWpf' ) ) {
				if ( ! function_exists( 'get_plugin_data' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php' ;
				}
				$plugin_data       = get_file_data( getProPlugFullPathWpf(), array( 'Version' => 'Version' ) );
				$this->_proVersion = $plugin_data['Version'];
			} else {
				$this->_proVersion = false;
			}
		}

		return ( ( $notPro && false === $this->_proVersion ) || version_compare( $this->_proVersion, $requires, $compare ) );
	}

	public function isWCLicense() {
		return $this->moduleExists('license') && $this->getModule('license') && isset($this->getModule('license')->isWooLicense) && $this->getModule('license')->isWooLicense;
	}

}
