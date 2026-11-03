# WP-CLI

Product Filter for WooCommerce by WBW 4.0 registers the `wp wpf` command (modules/cli). All commands run as the site; they need the plugin and WooCommerce active.

## Filters

```
wp wpf filter list [--format=table|json|csv|yaml]
wp wpf filter get <id> [--format=table|json]
wp wpf filter create --title=<title> --blocks=<blocks> [--set=<pairs>]
wp wpf filter update <id> [--set=<pairs>] [--title=<title>] [--blocks=<blocks>]
wp wpf filter delete <id>
wp wpf filter export <id> [--file=<path>]
wp wpf filter import --file=<path>
```

`--blocks` is a comma list or a JSON array. Comma words: `category`, `tags`, `brand`, `price`, `price_range`, `stock`, `onsale`, `featured`, `rating`, `sortby`, `search` (Pro), `author`; anything else is treated as an attribute slug (`size`, `color`, `pa_material`).

JSON form, one object per block:

```json
[
  { "type": "attribute", "taxonomy": "pa_size", "title": "Size", "display": "checkbox" },
  { "type": "category", "display": "list", "settings": { "f_show_hierarchical": "1" } },
  { "type": "price" }
]
```

`display` is one of `checkbox`, `radio`, `dropdown`, `mul_dropdown`, `slider` (Pro adds `buttons`, `colors`, `text`). `settings` takes any `f_*` block setting.

`--set` is a comma list of `key=value` main settings, for example `display_on_page=shop,enable_ajax=1,show_filtering_button=1`. `display_on_page=none` keeps a filter as a draft.

Export writes `{ "plugin", "version", "exported", "filters": [ { "title", "settings" } ] }`; import reads the same file (several filters allowed) and creates new rows through the model (no SQL from the file is executed; only known setting shapes are kept).

Examples:

```
wp wpf filter create --title="Shop" --blocks=category,size,color,price,stock,onsale,rating,sortby
wp wpf filter update 3 --set display_on_page=shop,enable_ajax=1
wp wpf filter export 3 --file=shop-filter.json
wp wpf filter import --file=shop-filter.json
```

## Index, cache, links, diagnostics

```
wp wpf index status        # table: running, last_run, indexed, total, percent, fresh, engine.* , version
wp wpf index rebuild       # rebuilds the 4.0 engine index and the 3.x meta index, bumps the counts cache
wp wpf cache clear         # removes the counts and rate limit transients, bumps the cache salt and the index version
wp wpf links flush         # re-registers the pretty URL rules and flushes rewrite rules
wp wpf diagnostics         # JSON, same data as the Help screen download (emails and keys removed)
```

## Exit codes

Commands end with `WP_CLI::success` (exit 0) or `WP_CLI::error` (exit 1) with a plain message, so they are safe in deploy scripts:

```
wp wpf index rebuild && wp wpf cache clear
```
