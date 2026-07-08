# woo-product-filter — Claude Context

**Product Filter for WooCommerce by WBW** (woobewoo.com). A WooCommerce plugin that adds frontend product filtering (price, taxonomy, attributes, meta, stock, etc.) via shortcodes, Elementor widget, and Gutenberg block. Current version: 3.1.4.

---

## Architecture

This plugin uses a **custom MVC framework** — NOT standard WordPress plugin patterns. Do not assume WP conventions apply here.

- **Singleton entry point**: `FrameWpf::_()` in `classes/frame.php`
- **All classes** are suffixed `Wpf` (e.g. `FrameWpf`, `ModelWpf`, `DbWpf`, `WoofiltersWpf`)
- **Module system**: each feature is a self-contained module in `modules/{name}/`
  - `mod.php` — module class extending `ModuleWpf` (init, hooks)
  - `controller.php` — extends `ControllerWpf` (handles actions/AJAX)
  - `models/` — extend `ModelWpf` (DB queries, business logic)
  - `views/` — extend `ViewWpf`; templates in `views/tpl/`
- Bootstrap order: `config.php` → `functions.php` → class imports → `InstallerWpf::update()` → `FrameWpf::_()->init()` → `exec()`

---

## Directory Map

```
woo-product-filter/
├── woo-product-filter.php   # Plugin bootstrap
├── config.php               # ALL constants defined here
├── functions.php            # Utility functions (importClassWpf, etc.)
├── classes/                 # Core framework
│   ├── frame.php            # FrameWpf — global init, routing, asset loading
│   ├── db.php               # DbWpf — wpdb wrapper
│   ├── model.php            # ModelWpf — base model + query builder
│   ├── view.php             # ViewWpf — template renderer
│   ├── controller.php       # ControllerWpf — base controller
│   ├── module.php           # ModuleWpf — base module
│   ├── installer.php        # DB schema creation/upgrade
│   └── tables/              # Table class definitions
├── modules/
│   ├── woofilters/          # CORE: filter logic, shortcodes, query hooks
│   ├── woofilters_widget/   # Elementor widget, Gutenberg block, WP widget
│   ├── options/             # Plugin settings
│   ├── templates/           # Frontend design/themes
│   ├── meta/                # Product metadata indexing
│   ├── pages/               # Admin CRUD pages
│   ├── adminmenu/           # Admin menu
│   ├── admin_nav/           # Admin navigation
│   ├── overview/            # Dashboard
│   ├── user/                # User management
│   ├── mail/                # Email
│   └── promo/               # Promotions
├── js/                      # Static JS (jQuery + vanilla, NO build step)
├── css/                     # Static CSS (NO build step)
└── languages/               # .po/.mo files; text domain: woo-product-filter
```

---

## Key Constants (`config.php`)

| Constant | Value |
|---|---|
| `WPF_CODE` | `'wpf'` |
| `WPF_DB_PREF` | `'wpf_'` |
| `WPF_VERSION` | `'3.1.4'` |
| `WPF_PLUG_NAME` | `'woo-product-filter'` |
| `WPF_DIR` | absolute path to plugin root |
| `WPF_MODULES_DIR` | `WPF_DIR . 'modules/'` |
| `WPF_CLASSES_DIR` | `WPF_DIR . 'classes/'` |
| `WPF_SHORTCODE` | `'wpf-filters'` |
| `WPF_SHORTCODE_PRODUCTS` | `'wpf-products'` |
| `WPF_SHORTCODE_SELECTED_FILTERS` | `'wpf-selected-filters'` |
| `WPF_FREE_VERSION` | `false` (this is the Pro build) |
| `WPF_TEST_MODE` | `true` |

---

## Database

**Custom tables** (all prefixed `wpf_` after WP prefix):

| Table | Purpose |
|---|---|
| `wpf_modules` | Module registry (id, code, active, type_id) |
| `wpf_filters` | Filter configs (id, title, `setting_data` JSON) |
| `wpf_meta_keys` | Indexable product meta field definitions |
| `wpf_meta_data` | Indexed product metadata values |
| `wpf_meta_values` | Unique value cache per meta field |
| `wpf_modules_type` | Module type definitions |
| `wpf_usage_stat` | Usage statistics |

**DB access via `DbWpf`** (never use `$wpdb` directly in plugin code):
- `DbWpf::get($query)` — SELECT queries
- `DbWpf::query($query)` — INSERT/UPDATE/DELETE
- SQL placeholders: `#__` = WP table prefix, `^__` = wpf prefix, `@__` = WP+wpf prefix

**Settings** stored in WP options table as single serialized array, key: `wpf_opts_data`.

---

## Core Filter Flow

1. Filter configurations saved in `wpf_filters.setting_data` (JSON)
2. Frontend sends filter params as `?wpf_<filter_id>=<value>` (pipe `|` for multiple values)
3. SEO-friendly slug format: `/wbw/key/value/key2/value2/` (v3.1.4+)
4. Hook: `woocommerce_product_query` (priority 999) → `WoofiltersWpf::loadProductsFilter($query)`
5. Modifies `WP_Query` with `tax_query`, `meta_query`, `s` etc.
6. Product metadata pre-indexed in `wpf_meta_data`/`wpf_meta_values` for performance

---

## Common Code Patterns

**Access any module:**
```php
FrameWpf::_()->getModule('woofilters')
FrameWpf::_()->getModule('options')->get('some_option_code')
```

**Save/read settings:**
```php
// Read
$val = FrameWpf::_()->getModule('options')->getModel()->get('option_key');
// Write
FrameWpf::_()->getModule('options')->getModel()->save('option_key', $value);
```

**AJAX request routing:**
- Params: `reqType=ajax`, `pl=wpf`, `mode=<module_name>`, `action=<method_name>`
- Public (unauthenticated) actions: `filtersFrontend`, `getTaxonomyTerms`
- Nonce action: `wpf-save-nonce`, nonce param: `wpfNonce`

**Adding a WordPress hook inside a module:**
```php
// In mod.php init() method:
add_action('wp_enqueue_scripts', array($this, 'myMethod'));
// OR via DispatcherWpf (auto-prefixes with wpf_):
DispatcherWpf::addAction('afterModulesInit', array($this, 'myMethod'));
```

---

## Frontend JavaScript

- Stack: **jQuery + vanilla JS**, no build step, no modern framework (React/Vue/etc.)
- Key global object: `WPF_DATA` — PHP config passed via `wp_localize_script()`
- Core files: `js/core.js` (AJAX form helper), `js/common.js` (utilities)
- AJAX form pattern: `jQuery('#form').sendFormWpf({ onSuccess: fn, data: {} })`

---

## i18n

- Text domain: `woo-product-filter`
- Loaded in `FrameWpf::connectLang()` → `init` hook
- Use `__('string', 'woo-product-filter')` and `esc_html__()`, `esc_attr__()`
- WPML support: `wpf_translate_string()` in `functions.php`

---

## Frequently Modified Files

- `config.php` — adding constants
- `modules/woofilters/mod.php` — filter init, shortcode hooks, query hooks
- `modules/woofilters/controller.php` — filter AJAX handlers
- `modules/options/models/options.php` — settings CRUD
- `classes/frame.php` — global routing, asset registration
- `classes/installer.php` — DB schema changes (increment `WPF_DB_VERSION`)

# Plugin update process
Tutorial on the update process of a WPFactory plugin. Every time a plugin gets updated there are some points to be considered.

## 1. Main file Version header field
The [**Version**](https://developer.wordpress.org/plugins/plugin-basics/header-requirements/#header-fields) header field from the main plugin file should be changed to any greater number than the official version number and `-dev` should be added to it.

Let's suppose the official version of a Plugin A is `1.0.0`. While making changes to that plugin, the **Version** should be changed to `1.0.1-dev`. Until a new release is published, that should be the plugin **Version** number.

> [!NOTE]
> When the new version is ready to be released, the `-dev` should be removed and new version should be `1.0.1`.

## 2. Readme.txt Stable tag header field
The [**Stable tag**](https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/#readme-header-information) header field from the `readme.txt` file should match the official version.

Let's suppose the official version of a Plugin A is `1.0.0`. While making changes to that plugin, the **Stable tag** should remain as `1.0.0`. Until a new release is published, that should be the **Stable tag** number.

> [!NOTE]
> When the new version is ready to be released, it should be changed to match the main file version header field, which is `1.0.1` in this example.

## 3. The plugin version variable
Variables exists on `config.php`. While editing a plugin, this version variable should be changed to any greater number than the official version number along with `-dev-YYYYMMDD-HHHH`.

Let's suppose the official version of a Plugin A is `1.0.0` and the current date time is 2024-07-18 20:10. While making changes to that plugin, the version variable should be changed to `1.0.1-dev-20240718-2010`. In theory, every time the plugin gets updated, this version should reflect the current datetime, but that's ok if it doesn't happen.

> [!NOTE]
> When the new version is ready to be released, it should be changed to match the main file version header field, which is `1.0.1` in this example.

## 5. PHPdoc @version tag
The [**@version**](https://docs.phpdoc.org/guide/references/phpdoc/tags/version.html) phpdoc tag should match the plugin version on every element changed, including Functions and class methods, Classes, Class members (including properties and constants), Requires and includes, Hooks (actions and filters), Inline comments, File headers and Constants.

Let's suppose the official version of a Plugin A is `1.0.0`. While making changes to that plugin, the **@version** should be changed to `1.0.1` only in the parts of the code that were changed along with the class header. Example:

### From:
```diff

<?php
/**
 * Plugin A - Class Test.
 *
 * @version 1.0.0
 * @since   1.0.0
 * @author  WPFactory
 */

class Test {

	/**
	 * Method A.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function method_a() {
		echo 'Some text';
	}

	/**
	 * Method B.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function method_b() {
		echo 'Some text';
	}

}
```

### To:
```diff

<?php
/**
 * Plugin A - Class Test.
 *
-* @version 1.0.0
+* @version 1.0.1
 * @since   1.0.0
 * @author  WPFactory
 */

class Test {

	/**
	 * Method A
-	 * @version 1.0.0
+	 * @version 1.0.1
	 * @since   1.0.0
	 */
	function method_a() {
-		echo 'Some text';
+		echo 'A different text';
	}

	/**
	 * Method B.
	 *
	 * @version 1.0.0
	 * @since   1.0.0
	 */
	function method_b() {
		echo 'Some text';
	}

}
```
