# JavaScript events

All events are `CustomEvent`s dispatched on `document`. Listen with `document.addEventListener( name, handler )`; the payload is in `event.detail`.

## 4.0 storefront engine

| Event | When | `detail` |
| --- | --- | --- |
| `wpf:change` | A shopper changed a control (checkbox, chip, slider, dropdown, search box) and the filter state changed. Fired before the request. | `{ filterId, key, values, query }` - `query` is the canonical param object (`{ size: ['42','43'], price: '50-120', ... }`). |
| `wpf:request` | The AJAX request for new results starts. | `{ filterId, url, query }` - `url` is the pretty (or query string) URL that will be shown in the address bar. |
| `wpf:results` | New results were written into the product loop and the counts were refreshed. Use it to re-run your own scripts on the new product cards. | `{ count, filterId, query, html }` - `count` is the number of matching products. |
| `wpf:error` | The request failed (network or server error). The previous results stay on the page. | `{ filterId, status, message }` |

The platform modules listen to `wpf:results` (and the 3.x `wpfAjaxSuccess`) to keep the address bar pretty when "Clean links" is on and to refresh the Share button state.

## 3.x compatibility events (still fired by the classic frontend)

| Event | When | `detail` |
| --- | --- | --- |
| `wpfAjaxSuccess` | After a successful AJAX update of the product loop. | `{ response }` |
| `wpf-synchro` (jQuery) | Triggered on inputs of synchronised filters when their state is copied. | - |

## Hooks you can use from JavaScript

- `window.wpfLinksApi.prettyUrl( base, params )` - build a pretty URL in the browser (same rules as `woobewoo_pf_pretty_url()` in PHP).
- `window.wpfLinksApi.canonicalFromSearch( location.search )` - canonical params from a query string (legacy or canonical names).
- `window.wpfLinksApi.currentPretty()` - the pretty URL of the current page state, or `null`.

## Example

```js
document.addEventListener( 'wpf:results', function ( e ) {
	console.log( e.detail.count + ' products for filter #' + e.detail.filterId );
} );
```

## Added in 4.0

| Event | Detail | When |
|---|---|---|
| `wpf4:conditions` | `{ filterId, filter, visible, hidden, shown, cleared }` | After the block visibility rules ran (dependent and conditional blocks). |
| `wpf:redirect` | `{ filterId, params, url, reason }` | Right before a filter with "Send shoppers to another page" leaves for the results page. |

`window.wpf4.conditions.evaluate( filter )` re-runs the rules by hand.
