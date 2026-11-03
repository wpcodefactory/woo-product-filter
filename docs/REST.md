# REST API

Generated from the OpenAPI document served at `GET /wp-json/wpf/v1/openapi` (`php dev-tools/docs/generate-rest-docs.php`, a development tool in the source repository, not shipped). Base URL: `https://example.com/wp-json/wpf/v1`.

Facets, product filtering and short links for this WooCommerce shop. Read operations are public (rate limited); the same operations are WordPress Abilities (woobewoo/*) and are served over MCP at https://example.com/wp-json/mcp/mcp-adapter-default-server.

Public read routes are rate limited to 120 requests a minute per IP (short links: 20). Admin routes need the `manage_options` capability: send a cookie plus `X-WP-Nonce`, or use an application password (HTTP basic).

## GET /facets

List facets

Attributes, categories, tags, brands, price range and stock values with product counts, optionally narrowed by a category or any filter selection. Also available as the WordPress Ability "woobewoo/facets-list".

| Parameter | In | Type | Required |
| --- | --- | --- | --- |
| `category` | query | string|integer | no |
| `tag` | query | string|integer | no |
| `brand` | query | string|integer | no |
| `attributes` | query | object | no |
| `min_price` | query | number | no |
| `max_price` | query | number | no |
| `in_stock` | query | boolean | no |
| `on_sale` | query | boolean | no |
| `featured` | query | boolean | no |
| `min_rating` | query | integer | no |
| `search` | query | string | no |

- **200**: OK - fields: `products`, `currency`, `categories`, `tags`, `brands`, `attributes`, `price`, `stock`, `on_sale`, `featured`, `url`
- **429**: Rate limit: 120 requests a minute per IP for public routes.

```
curl "https://example.com/wp-json/wpf/v1/facets?category=shoes"
```

## GET /products

Filter products

Filter products by attributes, category, tag, brand, price range, stock, sale and rating. Variation aware: returns the matching variation and its stock when attributes are given. Also available as the WordPress Ability "woobewoo/products-filter".

| Parameter | In | Type | Required |
| --- | --- | --- | --- |
| `category` | query | string|integer | no |
| `tag` | query | string|integer | no |
| `brand` | query | string|integer | no |
| `attributes` | query | object | no |
| `min_price` | query | number | no |
| `max_price` | query | number | no |
| `in_stock` | query | boolean | no |
| `on_sale` | query | boolean | no |
| `featured` | query | boolean | no |
| `min_rating` | query | integer | no |
| `search` | query | string | no |
| `per_page` | query | integer | no |
| `page` | query | integer | no |
| `orderby` | query | string (default, price, price-desc, date, popularity, rating, title) | no |

- **200**: OK - fields: `total`, `page`, `per_page`, `pages`, `url`, `products`
- **429**: Rate limit: 120 requests a minute per IP for public routes.

```
curl "https://example.com/wp-json/wpf/v1/products?attributes[size][]=42&in_stock=true&per_page=5"
```

## POST /products

Filter products

Filter products by attributes, category, tag, brand, price range, stock, sale and rating. Variation aware: returns the matching variation and its stock when attributes are given. Also available as the WordPress Ability "woobewoo/products-filter".

**Body (application/json):**

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `category` | string|integer | no | Category id or slug. |
| `tag` | string|integer | no | Tag id or slug. |
| `brand` | string|integer | no | Brand id or slug (WooCommerce Brands or Perfect Brands). |
| `attributes` | object | no | taxonomy or attribute slug => list of term slugs or ids, e.g. {"color":["red"],"size":["42","43"]}. Several values of one attribute are OR, attributes are AND. |
| `min_price` | number | no |  |
| `max_price` | number | no |  |
| `in_stock` | boolean | no | Only products that can be bought now. |
| `on_sale` | boolean | no |  |
| `featured` | boolean | no |  |
| `min_rating` | integer | no |  |
| `search` | string | no |  |
| `per_page` | integer | no | Default 20. |
| `page` | integer | no | Default 1. |
| `orderby` | string (default, price, price-desc, date, popularity, rating, title) | no | Default "default". |

- **200**: OK - fields: `total`, `page`, `per_page`, `pages`, `url`, `products`
- **429**: Rate limit: 120 requests a minute per IP for public routes.

```
curl -X POST -H "Content-Type: application/json" -d '{"attributes":{"size":["42"],"color":["red"]},"max_price":120,"in_stock":true}' "https://example.com/wp-json/wpf/v1/products"
```

## POST /short-links

Create a short shareable link for a filtered page.

**Body (application/json):**

| Field | Type | Required | Notes |
| --- | --- | --- | --- |
| `url` | string | yes |  |
| `filter_id` | integer | no |  |

- **200**: Short link - fields: `slug`, `short_url`, `url`, `filter_id`, `params`, `hits`, `created`, `qr`, `qr_url`
- **429**: Rate limit: 20 a minute per IP.

```
curl -X POST -H "Content-Type: application/json" -d '{"url":"https://example.com/shop/size-42/color-red/","filter_id":1}' "https://example.com/wp-json/wpf/v1/short-links"
```

## GET /short-links/{slug}

Read a short link.

| Parameter | In | Type | Required |
| --- | --- | --- | --- |
| `slug` | path | string | yes |

- **200**: Short link - fields: `slug`, `short_url`, `url`, `filter_id`, `params`, `hits`, `created`, `qr`, `qr_url`
- **404**: No such link

```
curl "https://example.com/wp-json/wpf/v1/short-links/red-shoes-42"
```

## GET /short-links/{slug}/qr

QR code (SVG) of a short link.

| Parameter | In | Type | Required |
| --- | --- | --- | --- |
| `slug` | path | string | yes |

- **200**: SVG image

```
curl "https://example.com/wp-json/wpf/v1/short-links/red-shoes-42/qr"
```

## GET /openapi

This document.

- **200**: OpenAPI 3.1 document

```
curl "https://example.com/wp-json/wpf/v1/openapi"
```

## GET /readiness

AI shopping readiness score with a fix line per failed check (manage_options).

**Needs:** manage_options (cookie + X-WP-Nonce, or application password).

- **200**: Score - fields: `score`, `checks`, `computed`

```
curl "https://example.com/wp-json/wpf/v1/readiness"
```

## POST /llms

Create or refresh llms.txt (manage_options).

**Needs:** manage_options (cookie + X-WP-Nonce, or application password).

- **200**: Result

```
curl -X POST -H "Content-Type: application/json" -d '{}' "https://example.com/wp-json/wpf/v1/llms"
```

## WordPress Abilities and MCP

The read operations are also registered as WordPress Abilities `woobewoo/facets-list` and `woobewoo/products-filter` (WordPress 6.9+, `wp_register_ability`), and over MCP when the WordPress MCP Adapter plugin is active (`/wp-json/mcp/mcp-adapter-default-server`). Pro adds the write abilities `woobewoo/filter-create`, `woobewoo/filter-update` and `woobewoo/filter-audit` through the `woobewoo_pf_abilities_registry` filter; they then appear in this document too.
