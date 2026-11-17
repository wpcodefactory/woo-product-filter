# Benchmark results - measured on this 4 core machine

Real numbers from the benchmark script, which is a development tool and not shipped with the plugin: it lives in the source repository at `dev-tools/benchmarks/run.php` (`wp eval-file …/dev-tools/benchmarks/run.php [filter_id] [nolegacy]`), run on 2026-10-24 on the development container: **Linux x86_64, 4 cores, 16 GB, PHP 8.3.6, MariaDB 10.11.14 (512 MB buffer pool, shared by the three test databases), WordPress 7.1.3, WooCommerce 11.2.1**, Storefront, index layout version 5. Nothing else ran while a site was measured. These are not laboratory figures: another server with more cores or a bigger buffer pool will do better, a cheap shared host worse.

Catalogues: generated variable products (3–6 variations each; pa_size 36–46, pa_color 12 values, 6 categories, 6 brands, 5 tags, ~20 % on sale, ratings, stock quantities), one filter with 13 blocks (category, size, colour, brand, tag, price slider, stock, sale, featured, rating, sort, search, seller). Every number is the **median of 10 runs**. "Cold" means the object cache and every transient are off (`WooBeWoo_PF_Engine::$nocache`) and the request computes the ids, the count and **every block's counts** (including the recounts of the selected blocks) from the index tables; "cached" is the same request served from the transient/object cache; "full page" is the Storefront shop page fetched over HTTP, with the index version bumped before every run so the result cache is cold and the counts come through stale-while-revalidate (served from the last known counts, recomputed after the response) - the behaviour a shopper gets after a product save.

## 10,000 products (62,297 index rows)

| Measure | Engine 4, cold | Engine 4, cached | Engine 4, full page over HTTP | Legacy 3.x, full page over HTTP |
|---|---:|---:|---:|---:|
| Counts of every block, empty query | 143.7 ms | – | – | – |
| Request: one attribute (size) - 6,224 found | 154.8 ms | 12.8 ms | 216.9 ms | 584.1 ms |
| Request: size + colour + in stock - 998 found | 283.2 ms | 15.0 ms | 221.6 ms | 521.8 ms |
| Request: category + price range + on sale + sort - 943 found | 190.1 ms | 10.5 ms | 221.6 ms | 255.7 ms |

The legacy figures are the 3.x engine on the same site with its meta index built (7.1 s to build), same filter, same requests, same HTTP path. Legacy was only measured at 10,000: its index build and its per-request work do not fit the time budget at 50,000 and above.

## 50,000 products (312,114 index rows)

| Measure | Engine 4, cold | Engine 4, cached |
|---|---:|---:|
| Counts of every block, empty query | 454.9 ms | – |
| Request: one attribute (size) - 31,141 found | 624.0 ms | 9.8 ms |
| Request: size + colour + in stock - 4,896 found | 1188.9 ms | 11.2 ms |
| Request: category + price range + on sale + sort - 4,724 found | 923.8 ms | 10.9 ms |

## 100,000 products (624,717 index rows)

| Measure | Engine 4, cold | Engine 4, cached |
|---|---:|---:|
| Counts of every block, empty query | 1440.1 ms | – |
| Request: one attribute (size) - 62,412 found | 1459.1 ms | 17.8 ms |
| Request: size + colour + in stock - 9,842 found | 2246.5 ms | 14.5 ms |
| Request: category + price range + on sale + sort - 9,441 found | 1821.9 ms | 10.3 ms |

## Before and after the query-plan pass (same machine, same data)

| Catalogue | Measure | Layout 4 engine | Layout 5 engine (now) |
|---|---|---:|---:|
| 10,000 | counts, empty query | 218 ms | 144 ms |
| 10,000 | size / size+colour+stock / cat+price+sale+sort | 320 / 481 / 410 ms | 155 / 283 / 190 ms |
| 50,000 | counts, empty query | 976 ms | 455 ms |
| 50,000 | the three requests | 1619 / 1843 / 2323 ms | 624 / 1189 / 924 ms |
| 100,000 | counts, empty query | 1961 ms | 1440 ms |
| 100,000 | the three requests | 3167 / 5595 / 5100 ms | 1459 / 2247 / 1822 ms |

What changed: the narrowed product id set is computed once per request and memoised by its SQL (a recount that skips an unselected block reuses it); the most selective term join drives the query (`STRAIGHT_JOIN` over the covering key `tax_term_hv`), product rows are then read by primary key; all unselected taxonomies are counted in one `GROUP BY` over `product_id IN (set)`; stock, sale, featured, rating and price bounds come from one pass over the product rows (`price` = lowest and `price_max` = highest variation price on the parent row); variation rows are only read when two variation level conditions remain, through `root_id IN (product level superset)` when that superset is at most 20,000 ids; wide pages walk the sort index (`FORCE INDEX`) instead of sorting the id list; whole catalogue counts are cached per index version.

## Reading the numbers

- Up to 10,000 products a cold request with every count is **150–280 ms** on this machine and the full page is faster than the legacy engine by 1.2–2.7×; cached requests are ~10 ms at every size.
- At 100,000 products a cold request is **1.5–2.2 s** on this machine. The cost is CPU work inside MariaDB (buffer pool miss rate 0.05 %): a single attribute matches 62,412 products and every count then aggregates 60,000–370,000 rows; the "size + colour + in stock" case needs the variation rows for the colour and size recounts (76,000 and 16,000 products at parent level). Per-query profile of the slowest request at 100,000: size+colour+stock set 172 + 205 ms, size recount 88 + 125 + 79 ms, colour recount 113 + 473 + 318 ms, price bounds 202 ms, stock recount 172 + 204 ms, the rest under 100 ms.
- The coordinator's target of "a few hundred ms at 100,000" is met for cached requests (10–18 ms) and for the full page through stale-while-revalidate, not for a fully cold request: that needs either precomputed per-term counts for common selections or a materialised attribute-combination table, both left for a later release (see the report).
- `wpf_benchmark_results` on each site holds the JSON of its last run (products, rows, counts_ms, request_ms, warm_ms, legacy_ms, found, runs, date, machine).
