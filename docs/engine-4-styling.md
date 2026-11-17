# Styling a 4.0 filter

The 4.0 storefront engine renders its own markup. Custom CSS written for the classic 3.x markup
(`.wpfMainWrapper`, `.wpfFilterWrapper`, `.wpfFilterButton` ...) does not match it. This file lists
what is supported to style: the custom properties and the class names. Both are stable within 4.x.

## Where custom CSS goes

Pro: the builder's **Look > Custom CSS for this filter** (saved by users with `unfiltered_html`), printed
inside `#wpf4-<id>-<n> { ... }` next to the filter. Free: your theme's Additional CSS, scoped to `.wpf4`.
Custom JavaScript (Pro) runs as `(function($, filter){ ... })(jQuery, jQuery('#wpf4-<id>-<n>'))`;
`filter` is the wrapper element. Events: `wpf:ready`, `wpf:request`, `wpf:results`, `wpf:error`,
`wpf:layout`, `wpf:addon-swapped` on `document`, with `detail.filterId`.

## Custom properties (set on the wrapper `.wpf4`)

| Property | What it colours / sizes | Default |
|---|---|---|
| `--wpf4-accent` | buttons, picked states, slider fill, switch on | the builder's accent colour |
| `--wpf4-on-accent` | text on the accent | picked for contrast |
| `--wpf4-ink`, `--wpf4-ink-2`, `--wpf4-ink-3` | text, secondary text, muted text | theme text colour |
| `--wpf4-paper`, `--wpf4-card` | filter background, block cards | theme background |
| `--wpf4-hairline` | borders | 1px tint of ink |
| `--wpf4-tint`, `--wpf4-chip`, `--wpf4-chip-hover` | soft fills, chips | tints of the accent |
| `--wpf4-snap` | transition time | 120ms (0 with reduced motion) |
| `--wpf4-gap` | spacing unit | 8px |
| `--wpf4-side` | sidebar width | the builder's "Filter column width" |
| `--wpf4-opt-size`, `--wpf4-swatch-size` | square / swatch size | 40px / 28px |
| `--wpf4-knob`, `--wpf4-slide` | slider handle size, drawer slide distance | 20px / 100% |

Example:

```css
.wpf4 { --wpf4-accent: #c0392b; --wpf4-opt-size: 44px; }
```

## Class names

Wrapper: `.wpf4` plus a look modifier `.wpf4--clean | --chips | --swatches | --compact`, `.wpf4--auto`
(auto update on), `.wpf4--drawer`, `.wpf4--drawer-always`, `.wpf4--phone` (under the breakpoint),
`.is-loading` while a request runs. The product list gets `.wpf4-loading` while results load.

| Part | Class |
|---|---|
| form | `.wpf4__form` |
| heading row, title, Clear all | `.wpf4__head`, `.wpf4__title`, `.wpf4__clear` |
| chips of picked values | `.wpf4__chips`, `.wpf4__chip`, `.wpf4__chipOpt` |
| block, its legend, body | `.wpf4__block`, `.wpf4__legend`, `.wpf4__legendText`, `.wpf4__fold`, `.wpf4__body` |
| list of choices | `.wpf4__list`, `.wpf4__options--chips`, `--squares`, `--swatches`, `--stars`, `--slider`, `--desktop`, `--mobile` |
| one choice | `.wpf4__opt` (+ `.is-checked`, `.is-empty`, `.wpf4__opt--child`, `--folded`, `--list`), `.wpf4__optText`, `.wpf4__count` |
| square / swatch / switch | `.wpf4__square`, `.wpf4__swatch`, `.wpf4__swatchColor`, `.wpf4__switch`, `.wpf4__toggle` |
| dropdown | `.wpf4__dd`, `.wpf4__ddSummary`, `.wpf4__ddList`, `.wpf4__select` |
| price / number slider | `.wpf4__slider`, `.wpf4__sliderTrack`, `.wpf4__sliderFill`, `.wpf4__sliderValues`, `.wpf4__num`, `.wpf4__range` |
| stars | `.wpf4__stars`, `.wpf4__starRow`, `.wpf4__star`, `.wpf4__starIcon` |
| search in a list | `.wpf4__search`, `.wpf4__searchInput` |
| Show more pill | `.wpf4__more` |
| Apply button (auto update off) | `.wpf4__apply` |
| live region | `.wpf4__live` |
| drawer | `.wpf4-drawerBtn`, `.wpf4-drawer`, `.wpf4-drawer__head`, `__body`, `__actions`, `__show`, `__clear`, `__close`, `.wpf4-scrim` |
| finder | `.wpf4__finderBtn`, `.wpf4-finder`, `.wpf4-finder__question`, `__options`, `__opt`, `__next`, `__back`, `__skip` |
| results | `.wpf4-products--empty`, `.wpf4-empty`, `.wpf4-loadMore`, `.wpf4-loadMore__btn`, `.wpf4-pagination` |
| layout (sidebar placement) | `.wpf4-layout`, `.wpf4-layout__side`, `.wpf4-layout__main`, `.wpf4-layout--stack` |

Everything else (`.wpf4__sr`, `.wpf4__hint`, `.wpf4__tip`, `.wpf4-sentinel`) is internal and may change.

## Moving classic CSS

| Classic selector | 4.0 selector |
|---|---|
| `.wpfMainWrapper` | `.wpf4` |
| `.wpfFilterWrapper` | `.wpf4__block` |
| `.wpfFilterTitle` | `.wpf4__legend` |
| `.wpfFilterContent` | `.wpf4__body` |
| `.wpfCheckbox`, `li.wpfFilterVerScroll` | `.wpf4__opt` |
| `.wpfFilterButton`, `.wpfClearButton` | `.wpf4__apply`, `.wpf4__clear` |
| `.wpfSelectedParams` | `.wpf4__chips` |
| `.wpfPrice .irs` | `.wpf4__slider` |
| `.wpfLoaderLayout` | `.wpf4-loading` (on the product list) |
