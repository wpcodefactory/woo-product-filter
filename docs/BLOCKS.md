# Block registry (4.0.0)

The 4.0 storefront engine is built from blocks: category, attribute, price, stock, rating and so on. Since 4.0.0 a
new kind of block is added from one file with `WooBeWoo_PF_Blocks`, without touching the engine, the query, the
view, the builder or WP-CLI. The built-in blocks keep their own code; the registry only serves the `default:`
branches, so a filter made of built-in blocks renders and queries exactly as before.

Class: `modules/woofilters/class-woobewoo-pf-blocks.php`. Loaded by the woofilters module before any filter is
rendered, also under WP-CLI and REST.

## Registering

Register on the `woobewoo_pf_register_blocks` action. It fires once, the first time the registry is read after
`plugins_loaded`, so hook it from your plugin's main file or from a module `init`:

```php
add_action( 'woobewoo_pf_register_blocks', function () {
	WooBeWoo_PF_Blocks::register( 'wpfExample', array( /* see below */ ) );
} );
```

### `WooBeWoo_PF_Blocks::register( $id, $args )`

`$id` is the block id the builder saves (`wpfExample`: letters, digits, `_`; not a built-in id). Returns `false` for a
bad id, a built-in id or kind, or a reserved key. Arguments:

| key | type | what it does |
| --- | --- | --- |
| `kind` | string, required | `data-kind` on the fieldset and the facet kind. Letters, digits, `_`. Not one of the engine's kinds (`terms author price stock sale featured rating sort q text meta`). |
| `label` | string | Builder name and the storefront title when the block has no title of its own. Plain words. |
| `slug` | string | Model slug (`getAllFilters()`). Defaults to the kind. |
| `unique` | bool | One per filter (default `true`). |
| `content_type` | string | Model content type: `meta`, `taxonomy`, `user` or `''` (default `meta`). |
| `pro` | bool | The builder shows the card as "· Pro" and links it to Upgrade while Pro is absent; the type is disabled in `getAllFilters()`. |
| `card` | array | `icon` (inline SVG), `group` (`basics` or `product`), `spec` (short line under the name), `href` (link instead of Upgrade). |
| `displays` | array | Display types from `WooBeWoo_PF_Admin_Schema::displayTypes()` (`list chips squares swatches dropdown slider toggle range_list`). The first is the default. |
| `controls` | array | Builder controls (admin-schema control arrays). `scope`, `blocks` and `engine4Only` are set for you; `group` defaults to `basics`. |
| `defaults` | array | Block settings the builder (and `wp wpf filter create`) writes when the block is added. |
| `question` | string | The finder's default question. |
| `finder` | bool | Offer the block as a finder step (default `false`). Its answers come from `counts` and `labels` of the facet. |
| `single` | bool | One pick only (radios / single dropdown). |
| `key` | string or callable | The URL key: `wpf_<key>`. A callable receives the builder block (`array( id, settings, uniqId )`) and returns the key; `''` makes the block inactive. Default: the kind. |
| `match` | callable | `function ( $name, $def )`: claim a URL key that is not the static one (needed when `key` is a callable). |
| `parse` | callable | `function ( $raw, $query, $key, $def )`: store the URL value on the query. Default: a comma list into `$query->extra[ $key ]` (array of sanitized strings). |
| `param` | callable | `function ( $value, $def )`: the stored value back as a URL string. Default: `implode( ',', $value )`. |
| `sql` | callable | `function ( $b, $query, $engine, $def )`: `array( 'joins' => array(), 'tjoins' => array(), 'where' => array() )`, or `null` when nothing is picked. |
| `facets` | callable | `function ( $blocks, $engine, $skipKey, $def )`: `array( $blockKey => array( 'kind' => ..., 'counts' => array( value => n ), 'labels' => array( value => text ) ) )`. Called once per kind with every block of that kind that has no selection of its own (`$skipKey = ''`), and once per block that has one (`$skipKey` = its key, OR logic). |
| `index` | array | `array( 'meta_keys' => array( '_weight' ) )`: numeric meta keys merged into `woobewoo_pf_index_meta_keys` (priority 5), so the facet index stores them. |
| `render` | callable | `function ( $b, $display, $mobile, $view, $def )`: the inner markup of the block body. The view wraps it in `<div class="wpf4__options wpf4__options--<display> ..." data-wpf4-options>`. |
| `chip` | callable | `function ( $value, $b, $def )`: the chip text of one picked value. Default: the value (the script uses the control's label on later picks). |
| `cli` | string | The word `wp wpf filter create --blocks=` accepts for this block. |

### `WooBeWoo_PF_Blocks::register_source( $id, $args )`

A terms-like block whose values come from callbacks instead of a taxonomy. Takes every argument of `register()`
plus:

- `values`: `function ( $engine, $def )` returning `array( 'slug' => array( 'label' => 'Wool', 'count' => null ) )`
  (or `'slug' => 'Wool'`). `count` is used as the whole-catalogue count when it is an integer; `null` means "count
  it". `$engine` may be `null` when the values are read for a chip only.
- `ids`: `function ( $values, $engine, $def )` returning the ids of the published products that carry any of the
  given values (parent ids, not variations).

The registry then supplies `sql` (`l.root_id IN (...)`, AND logic intersects one id list per value), `facets` (one
`ids()` call per value, counted inside the narrowed set, cached through `$engine->cache_key()`), `render` (tick
boxes, chips, squares or a dropdown through `$view->render_options()`) and `chip` (the value's label).

### Lookups

`get( $id )`, `all()`, `kinds()`, `by_kind( $kind )`, `by_key( $urlKey )`, `is_registered( $id )`, `of( $normalizedBlock )`,
`displays( $id )`, `cli_words()`, `unregister( $id )`.

## What the engine gives you

- `$query` is a `WooBeWoo_PF_Query`. Your values live in `$query->extra[ $key ]`; they are part of `params()`,
  `hash()`, `is_empty()`, `has_filters()` and the canonical URL.
- `$engine` is a `WooBeWoo_PF_Engine`: `$engine->query`, `$engine->ctx`, `$engine->blocks`, `$engine->t` (index table
  names: `products`, `terms`, `meta`, `prices`), `$engine->set_ids( $skipKey )` (the narrowed product ids, `null` for
  the whole catalogue, `array()` for none), `$engine->leaf_rows( $skipKey )`, `$engine->from_where( $skipKey )`,
  `$engine->cache_key( $what, $extra )`, `WooBeWoo_PF_Engine::cache_get()` / `cache_set()`, `$engine->pid_column()`.
- In `sql`, the alias `l` is a row of the products index table: a parent product in product mode, a variation or
  simple product in leaf mode. Use `$engine->pid_column()` (`l.root_id`) for "the product id this row counts as".
  Prepare every value with `$wpdb->prepare()`; the engine runs the pieces unchanged.
- `$b` is the normalized block: `key`, `kind`, `logic` (`or` / `and`), `display`, `single`, `title`, `uniqId`, and
  `block` (the builder block with its `settings`).
- `$view` is a `WooBeWoo_PF_Frontend4_View`. Read-only: `$view->engine`, `$view->query`, `$view->settings`,
  `$view->facets`, `$view->filter`, `$view->ctx`. Helpers: `render_options( $b, $display, $name, $opts, $showCount, $mobile )`,
  `option( $display, $type, $name, $o, $showCount, $mobile )`, `fid( $name, $mobile )`, `icon( $name )`,
  `slider( $b, $key, $mobile )`, `range_list( $b, $mobile, $display )`, `search_box( $b, $mobile )`,
  `chip( $key, $value, $label )`. Controls must be named `wpf_<key>` and carry `data-wpf4-control` (plus
  `data-label` and `data-count`); the storefront script collects and recounts them by itself.
- The classic (3.x) engine ignores registered blocks: on a classic filter they render nothing.

## Example: a "Material" source block

Values come from a product meta key `_material` (any source works: an option, an API, a custom table).

```php
<?php
/**
 * Plugin Name: Material filter block
 */
add_action( 'woobewoo_pf_register_blocks', function () {
	if ( ! class_exists( 'WooBeWoo_PF_Blocks' ) ) {
		return;
	}
	WooBeWoo_PF_Blocks::register_source( 'wpfMaterial', array(
		'kind'         => 'material',
		'label'        => __( 'Material', 'my-plugin' ),
		'slug'         => 'material',
		'unique'       => true,
		'content_type' => 'meta',
		'card'         => array(
			'icon'  => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 4l10 6 10-6M4 10l10 6 10-6M4 16l10 6 10-6"/></svg>',
			'group' => 'product',
			'spec'  => __( 'Wool, cotton, leather…', 'my-plugin' ),
		),
		'displays'     => array( 'list', 'chips', 'dropdown' ),
		'controls'     => array(
			array( 'key' => 'f_show_count', 'type' => 'switch', 'label' => __( 'Show how many products each material has', 'my-plugin' ) ),
			array( 'key' => 'f_hide_empty', 'type' => 'switch', 'label' => __( 'Hide materials with no products', 'my-plugin' ) ),
		),
		'defaults'     => array( 'f_show_count' => '1', 'f_title' => __( 'Material', 'my-plugin' ) ),
		'question'     => __( 'Which material?', 'my-plugin' ),
		'finder'       => true,
		'key'          => 'material',
		'cli'          => 'material',
		'values'       => function ( $engine ) {
			global $wpdb;
			$rows = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_material' AND meta_value <> '' ORDER BY meta_value" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$out  = array();
			foreach ( (array) $rows as $name ) {
				$out[ sanitize_title( $name ) ] = array( 'label' => $name, 'count' => null );
			}
			return $out;
		},
		'ids'          => function ( $values, $engine ) {
			global $wpdb;
			// Slugs back to the stored names, then the published products that carry one of them.
			$names = array();
			foreach ( WooBeWoo_PF_Blocks::source_values( WooBeWoo_PF_Blocks::get( 'wpfMaterial' ), $engine ) as $slug => $v ) {
				if ( in_array( $slug, $values, true ) ) {
					$names[] = $v['label'];
				}
			}
			if ( ! $names ) {
				return array();
			}
			$in = implode( ',', array_fill( 0, count( $names ), '%s' ) );
			return $wpdb->get_col( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_material' WHERE p.post_type = 'product' AND p.post_status = 'publish' AND m.meta_value IN ($in)", $names ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		},
	) );
} );
```

The block now appears as a "Material" card in the builder, filters through `?wpf_material=wool,cotton`, recounts with
the other blocks, shows chips, works in the finder and can be created with `wp wpf filter create --blocks=category,material`.

## Example: a kind block with its own SQL, counts and markup

A "Weight" block: a slider over the numeric meta `_weight`, indexed by the facet index.

```php
add_action( 'woobewoo_pf_register_blocks', function () {
	WooBeWoo_PF_Blocks::register( 'wpfWeight', array(
		'kind'     => 'weight',
		'label'    => __( 'Weight', 'my-plugin' ),
		'displays' => array( 'slider' ),
		'index'    => array( 'meta_keys' => array( '_weight' ) ),
		'question' => __( 'How heavy may it be?', 'my-plugin' ),
		'key'      => 'weight',
		'cli'      => 'weight',
		// URL: wpf_weight=min-max.
		'parse'    => function ( $raw, $query, $key ) {
			$r = WooBeWoo_PF_Query::range( $raw );
			$query->extra[ $key ] = ( null === $r[0] && null === $r[1] ) ? null : $r;
		},
		'param'    => function ( $value ) {
			return is_array( $value ) ? WooBeWoo_PF_Query::format_range( $value ) : '';
		},
		'sql'      => function ( $b, $query, $engine ) {
			global $wpdb;
			$r = isset( $query->extra['weight'] ) ? $query->extra['weight'] : null;
			if ( ! is_array( $r ) ) {
				return null;
			}
			$parts = array();
			if ( null !== $r[0] ) {
				$parts[] = $wpdb->prepare( 'w.value_num >= %f', (float) $r[0] );
			}
			if ( null !== $r[1] ) {
				$parts[] = $wpdb->prepare( 'w.value_num <= %f', (float) $r[1] );
			}
			return array(
				'joins' => array( "INNER JOIN {$engine->t['meta']} w ON w.product_id = l.product_id AND w.meta_key = '_weight' AND " . implode( ' AND ', $parts ) ),
				'where' => array(),
			);
		},
		'facets'   => function ( $blocks, $engine, $skipKey ) {
			global $wpdb;
			$out = array();
			foreach ( $blocks as $k => $b ) {
				$lr  = $engine->leaf_rows( $skipKey );
				$row = $lr ? $wpdb->get_row( "SELECT MIN(w.value_num) AS mn, MAX(w.value_num) AS mx FROM {$lr['from']} INNER JOIN {$engine->t['meta']} w ON w.product_id = l.product_id AND w.meta_key = '_weight' WHERE {$lr['where']}", ARRAY_A ) : null; // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$out[ $k ] = array(
					'kind' => 'meta', // the storefront script moves slider bounds for kind price|meta
					'min'  => $row && null !== $row['mn'] ? (float) $row['mn'] : 0,
					'max'  => $row && null !== $row['mx'] ? (float) $row['mx'] : 0,
				);
			}
			return $out;
		},
		'render'   => function ( $b, $display, $mobile, $view ) {
			// The view's slider reads $view->facets[ key ] (min, max) and $view->query->meta[ key ]: copy the picked range there.
			$view->query->meta['weight'] = isset( $view->query->extra['weight'] ) && is_array( $view->query->extra['weight'] ) ? $view->query->extra['weight'] : null;
			return $view->slider( $b, 'weight', $mobile );
		},
		'chip'     => function ( $value ) {
			return is_array( $value ) ? __( 'Weight', 'my-plugin' ) . ' ' . WooBeWoo_PF_Frontend4::range_label( $value ) : (string) $value;
		},
	) );
} );
```

## Filters for add-ons

- `woobewoo_pf_author_options( $opts, $b, $view )`: the options of an Author / Vendors block before they are sorted
  and rendered (`value`, `label`, `count`, `checked`, `depth`). Pro swaps user names for vendor store names here.
- `woobewoo_pf_engine_extra_sql( $extra, $query, $engine, $skipKey, $mode )`: `array( 'joins' => array(), 'where' => array() )`
  merged into the narrowed set of every result and count (the Pro vehicle finder's "my garage"). The pieces are part
  of every engine cache key (hashed), so a change in them never serves another visitor's counts. `$mode` is `product`
  or `leaf` (see above for the `l` alias); the signature used in cache keys is taken with `''` and `product`.
- `woobewoo_pf_register_blocks`: where to register.
- `woobewoo_pf_index_meta_keys`: the registry merges `index.meta_keys` at priority 5.
