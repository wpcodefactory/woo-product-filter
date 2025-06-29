<?php
class TemplatesWpf extends ModuleWpf {
	protected $_styles = array();
	private $_cdnUrl = '';


	public function __construct( $d ) {
		parent::__construct($d);
		$this->getCdnUrl();	// Init CDN URL
	}
	public function getCdnUrl() {
		if (empty($this->_cdnUrl)) {
			if ((int) FrameWpf::_()->getModule('options')->get('use_local_cdn')) {
				$uploadsDir = wp_upload_dir( null, false );
				$this->_cdnUrl = $uploadsDir['baseurl'] . '/' . WPF_CODE . '/';
				if (UriWpf::isHttps()) {
					$this->_cdnUrl = str_replace('http://', 'https://', $this->_cdnUrl);
				}
				DispatcherWpf::addFilter('externalCdnUrl', array($this, 'modifyExternalToLocalCdn'));
			} else {
				$this->_cdnUrl = ( UriWpf::isHttps() ? 'https' : 'http' ) . '://woobewoo-14700.kxcdn.com/';
			}
		}
		return $this->_cdnUrl;
	}
	public function modifyExternalToLocalCdn( $url ) {
		$url = str_replace(
			array('https://maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css'),
			array(FrameWpf::_()->getModule('templates')->getModPath() . 'css'),
			$url);
		return $url;
	}
	public function init() {
		if (is_admin()) {
			$isAdminPlugOptsPage = FrameWpf::_()->isAdminPlugOptsPage();
			if ($isAdminPlugOptsPage) {
				$this->loadCoreJs();
				$this->loadAdminCoreJs();
				$this->loadCoreCss();
				$this->loadChosenSelects();
				FrameWpf::_()->addScript('adminOptionsWpf', WPF_JS_PATH . 'admin.options.js', array(), false, true);
				add_action('admin_enqueue_scripts', array($this, 'loadMediaScripts'));
				add_action('init', array($this, 'connectAdditionalAdminAssets'));
			}
			// Some common styles - that need to be on all admin pages - be careful with them
			FrameWpf::_()->addStyle('woobewoo-for-all-admin-' . WPF_CODE, WPF_CSS_PATH . 'woobewoo-for-all-admin.css');
		}
		parent::init();
	}
	public function connectAdditionalAdminAssets() {
		if (is_rtl()) {
			FrameWpf::_()->addStyle('styleWpf-rtl', WPF_CSS_PATH . 'style-rtl.css');
		}
	}
	public function loadMediaScripts() {
		if (function_exists('wp_enqueue_media')) {
			wp_enqueue_media();
		}
	}
	public function loadAdminCoreJs() {
		FrameWpf::_()->addScript('jquery-ui-dialog');
		FrameWpf::_()->addScript('jquery-ui-slider');
		FrameWpf::_()->addScript('icheck', WPF_JS_PATH . 'icheck.min.js', array('wp-i18n', 'jquery-ui-widget', 'iris'));
		FrameWpf::_()->addScript('wp-color-picker');
	}
	public function loadCoreJs() {
		FrameWpf::_()->addScript('jquery');

		FrameWpf::_()->addScript('commonWpf', WPF_JS_PATH . 'common.js', array('jquery'));
		FrameWpf::_()->addScript('coreWpf', WPF_JS_PATH . 'core.js', array('jquery'));

		$ajaxurl = admin_url('admin-ajax.php');
		$jsData = array(
			'siteUrl'					=> WPF_SITE_URL,
			'imgPath'					=> WPF_IMG_PATH,
			'cssPath'					=> WPF_CSS_PATH,
			'loader'					=> WPF_LOADER_IMG,
			'close'						=> WPF_IMG_PATH . 'cross.gif',
			'ajaxurl'					=> $ajaxurl,
			'options'					=> FrameWpf::_()->getModule('options')->getAllowedPublicOptions(),
			'WPF_CODE'					=> WPF_CODE,
			'jsPath'					=> WPF_JS_PATH,
		);
		if (is_admin()) {
			$jsData['isPro'] = FrameWpf::_()->getModule('promo')->isPro();
			$jsData['mainLink'] = FrameWpf::_()->getModule('promo')->getMainLink();
			$jsData['isWCLicense'] = FrameWpf::_()->isWCLicense();
		}
		$jsData = DispatcherWpf::applyFilters('jsInitVariables', $jsData);
		FrameWpf::_()->addJSVar('coreWpf', 'WPF_DATA', $jsData);
		$this->loadTooltipster();
	}
	public function loadTooltipster() {
		FrameWpf::_()->addScript('tooltipster', FrameWpf::_()->getModule('templates')->getModPath() . 'lib/tooltipster/jquery.tooltipster.min.js');
		FrameWpf::_()->addStyle('tooltipster', FrameWpf::_()->getModule('templates')->getModPath() . 'lib/tooltipster/tooltipster.css');
	}
	public function loadSlimscroll() {
		FrameWpf::_()->addScript('jquery.slimscroll', WPF_JS_PATH . 'slimscroll.min.js');
	}
	public function loadCodemirror() {
		$modPath = FrameWpf::_()->getModule('templates')->getModPath();
		FrameWpf::_()->addStyle('wpfCodemirror', $modPath . 'lib/codemirror/codemirror.css');
		FrameWpf::_()->addStyle('codemirror-addon-hint', $modPath . 'lib/codemirror/addon/hint/show-hint.css');
		FrameWpf::_()->addScript('wpfCodemirror', $modPath . 'lib/codemirror/codemirror.js');
		FrameWpf::_()->addScript('codemirror-addon-show-hint', $modPath . 'lib/codemirror/addon/hint/show-hint.js');
		FrameWpf::_()->addScript('codemirror-addon-xml-hint', $modPath . 'lib/codemirror/addon/hint/xml-hint.js');
		FrameWpf::_()->addScript('codemirror-addon-html-hint', $modPath . 'lib/codemirror/addon/hint/html-hint.js');
		FrameWpf::_()->addScript('codemirror-mode-xml', $modPath . 'lib/codemirror/mode/xml/xml.js');
		FrameWpf::_()->addScript('codemirror-mode-javascript', $modPath . 'lib/codemirror/mode/javascript/javascript.js');
		FrameWpf::_()->addScript('codemirror-mode-css', $modPath . 'lib/codemirror/mode/css/css.js');
		FrameWpf::_()->addScript('codemirror-mode-htmlmixed', $modPath . 'lib/codemirror/mode/htmlmixed/htmlmixed.js');
	}
	public function loadCoreCss( $isElementorEditor = false ) {
		$this->_styles = array(
			'styleWpf'			=> array('path' => WPF_CSS_PATH . 'style.css', 'for' => 'admin'),
			'woobewoo-uiWpf'	=> array('path' => WPF_CSS_PATH . 'woobewoo-ui' . ( FrameWpf::_()->isWCLicense() ? '-wc' : '' ) . '.css', 'for' => 'admin'),
			'dashicons'			=> array('for' => 'admin'),
			'bootstrap-alerts'	=> array('path' => WPF_CSS_PATH . 'bootstrap-alerts.css', 'for' => 'admin'),
			'icheck'			=> array('path' => WPF_CSS_PATH . 'jquery.icheck.css', 'for' => 'admin'),
			'wp-color-picker'	=> array('for' => 'admin'),
		);
		foreach ($this->_styles as $s => $sInfo) {
			if ($isElementorEditor) {
				$sInfo['for'] = '';
			}
			if (!empty($sInfo['path'])) {
				FrameWpf::_()->addStyle($s, $sInfo['path']);
			} else {
				FrameWpf::_()->addStyle($s);
			}
		}
		$this->loadFontAwesome();
	}
	public function loadJqueryUi( $slider = true ) {
		FrameWpf::_()->addStyle('jquery-ui', WPF_CSS_PATH . 'jquery-ui.min.css');
		FrameWpf::_()->addStyle('jquery-ui.structure', WPF_CSS_PATH . 'jquery-ui.structure.min.css');
		FrameWpf::_()->addStyle('jquery-ui.theme', WPF_CSS_PATH . 'jquery-ui.theme.min.css');
		if ($slider) {
			FrameWpf::_()->addStyle('jquery-slider', WPF_CSS_PATH . 'jquery-slider.css');
		}
	}
	public function loadJqGrid() {
		static $loaded = false;
		if (!$loaded) {
			$this->loadJqueryUi();
			FrameWpf::_()->addScript('jq-grid', FrameWpf::_()->getModule('templates')->getModPath() . 'lib/jqgrid/jquery.jqGrid.min.js');
			FrameWpf::_()->addStyle('jq-grid', FrameWpf::_()->getModule('templates')->getModPath() . 'lib/jqgrid/ui.jqgrid.css');
			$langToLoad = UtilsWpf::getLangCode2Letter();
			$availableLocales = array('ar', 'bg', 'bg1251', 'cat', 'cn', 'cs', 'da', 'de', 'dk', 'el', 'en', 'es', 'fa', 'fi', 'fr', 'gl', 'he', 'hr', 'hr1250', 'hu', 'id', 'is', 'it', 'ja', 'kr', 'lt', 'mne', 'nl', 'no', 'pl', 'pt', 'pt', 'ro', 'ru', 'sk', 'sr', 'sr', 'sv', 'th', 'tr', 'tw', 'ua', 'vi');
			if (!in_array($langToLoad, $availableLocales)) {
				$langToLoad = 'en';
			}
			FrameWpf::_()->addScript('jq-grid-lang', FrameWpf::_()->getModule('templates')->getModPath() . 'lib/jqgrid/i18n/grid.locale-' . $langToLoad . '.js');
			$loaded = true;
		}
	}
	public function loadFontAwesome() {
		//FrameWpf::_()->addStyle('font-awesomeWpf', DispatcherWpf::applyFilters('externalCdnUrl', 'https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css'));
		FrameWpf::_()->addStyle('font-awesomeWpf', FrameWpf::_()->getModule('templates')->getModPath() . 'css/font-awesome.min.css');
	}
	public function loadChosenSelects() {
		FrameWpf::_()->addStyle('jquery.chosen', FrameWpf::_()->getModule('templates')->getModPath() . 'lib/chosen/chosen.min.css');
		FrameWpf::_()->addScript('jquery.chosen', FrameWpf::_()->getModule('templates')->getModPath() . 'lib/chosen/chosen.jquery.min.js');
	}
	public function loadDatePicker() {
		FrameWpf::_()->addScript('jquery-ui-datepicker');
	}
	public function loadJqplot() {
		static $loaded = false;
		if (!$loaded) {
			$jqplotDir = FrameWpf::_()->getModule('templates')->getModPath() . 'lib/jqplot/';

			FrameWpf::_()->addStyle('jquery.jqplot', $jqplotDir . 'jquery.jqplot.min.css');

			FrameWpf::_()->addScript('jplot', $jqplotDir . 'jquery.jqplot.min.js');
			FrameWpf::_()->addScript('jqplot.canvasAxisLabelRenderer', $jqplotDir . 'jqplot.canvasAxisLabelRenderer.min.js');
			FrameWpf::_()->addScript('jqplot.canvasTextRenderer', $jqplotDir . 'jqplot.canvasTextRenderer.min.js');
			FrameWpf::_()->addScript('jqplot.dateAxisRenderer', $jqplotDir . 'jqplot.dateAxisRenderer.min.js');
			FrameWpf::_()->addScript('jqplot.canvasAxisTickRenderer', $jqplotDir . 'jqplot.canvasAxisTickRenderer.min.js');
			FrameWpf::_()->addScript('jqplot.highlighter', $jqplotDir . 'jqplot.highlighter.min.js');
			FrameWpf::_()->addScript('jqplot.cursor', $jqplotDir . 'jqplot.cursor.min.js');
			FrameWpf::_()->addScript('jqplot.barRenderer', $jqplotDir . 'jqplot.barRenderer.min.js');
			FrameWpf::_()->addScript('jqplot.categoryAxisRenderer', $jqplotDir . 'jqplot.categoryAxisRenderer.min.js');
			FrameWpf::_()->addScript('jqplot.pointLabels', $jqplotDir . 'jqplot.pointLabels.min.js');
			FrameWpf::_()->addScript('jqplot.pieRenderer', $jqplotDir . 'jqplot.pieRenderer.min.js');
			$loaded = true;
		}
	}
	public function loadSortable() {
		static $loaded = false;
		if (!$loaded) {
			FrameWpf::_()->addScript('jquery-ui-core');
			FrameWpf::_()->addScript('jquery-ui-widget');
			FrameWpf::_()->addScript('jquery-ui-mouse');

			FrameWpf::_()->addScript('jquery-ui-draggable');
			FrameWpf::_()->addScript('jquery-ui-sortable');
			$loaded = true;
		}
	}
	public function loadMagicAnims() {
		static $loaded = false;
		if (!$loaded) {
			FrameWpf::_()->addStyle('magic.anim', FrameWpf::_()->getModule('templates')->getModPath() . 'css/magic.min.css');
			$loaded = true;
		}
	}
	public function loadCssAnims() {
		static $loaded = false;
		if (!$loaded) {
			FrameWpf::_()->addStyle('animate.styles', 'https://cdnjs.cloudflare.com/ajax/libs/animate.css/3.4.0/animate.min.css');
			$loaded = true;
		}
	}
	public function loadBootstrapSimple() {
		static $loaded = false;
		if (!$loaded) {
			FrameWpf::_()->addStyle('bootstrap-simple', WPF_CSS_PATH . 'bootstrap-simple.css');
			$loaded = true;
		}
	}
	public function loadBootstrap() {
		static $loaded = false;
		if (!$loaded) {
			FrameWpf::_()->addStyle('bootstrap.min', WPF_CSS_PATH . 'bootstrap.min.css');
			$loaded = true;
		}
	}
	public function loadGoogleFont( $font ) {
		static $loaded = array();
		if (!isset($loaded[ $font ])) {
			FrameWpf::_()->addStyle('google.font.' . str_replace(array(' '), '-', $font), 'https://fonts.googleapis.com/css?family=' . urlencode($font));
			$loaded[ $font ] = 1;
		}
	}
	public function loadBxSlider() {
		static $loaded = false;
		if (!$loaded) {
			FrameWpf::_()->addStyle('bx-slider', WPF_JS_PATH . 'bx-slider/jquery.bxslider.css');
			FrameWpf::_()->addScript('bx-slider', WPF_JS_PATH . 'bx-slider/jquery.bxslider.min.js');
			$loaded = true;
		}
	}
}
