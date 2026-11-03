# 4.0 admin components (wbw-admin.css)

Every plugin screen (Free and Pro) is built from these classes so the screens look identical. The stylesheet is `modules/templates/css/wbw-admin.css`, handle `woobewoo-pf-wbw-admin`, loaded on plugin admin pages only. Tokens are CSS custom properties prefixed `--wbw-` (paper, card, chip, ink, ink-2, ink-3, tool, tool-tint, tool-text, pass, partial, fail, hairline, radii, motion). Font: IBM Plex Sans Arabic from `fonts/` (400 and 700). No borders on cards or controls, no shadows, no gradients, no icon fonts.

Wrap your screen in nothing: the page shell (`modules/options/views/tpl/optionsAdminPage.php`) already prints `.wbw.wbw-admin > .wbw-page > .wbw-rail + main.wbw-main`. A tab with `'full_width' => true` (the builder) gets `.wbw-page` without the rail.

## Frame (three regions)
Every screen has the same rhythm: paper page, the rail on paper with a 1px hairline on its right edge (`box-shadow: inset -1px 0 0 hairline`) and 24px of air after it, the main column with 32px of padding on paper, white cards with 16px gaps (24px between the two big columns).
- `.wbw-rail` 200px, paper, hairline on its right edge, 24px margin before the main column. Under 700px it becomes a full width row with the hairline underneath.
- `.wbw-main` main column, padding 24px 32px 32px (16px on phones), vertical gap 24px.
- `.wbw-two` two column frame (`> div` flex 1 1 380px, min-width 0, gap 24px): the question card on the left, the preview card on the right. Setup steps use it.
- `.wbw-card--q` the question card (white, radius 22, padding 32, gap 20). Boxes inside it sit on paper so they read as inside the card: use `.wbw-inset` (paper, radius 16, padding 20px 24px; inputs, picture buttons and select chips inside it turn white) or the screen's own rule (`.wbw-setup__pane > .wbw-card` in admin.setup.css does the same for the wizard's stat box, checklist and speed rows).
- `.wbw-grid2`, `.wbw-grid3` card grids (16px gap, one column under 900px).
- Builder (`modules/woofilters/css/admin.wbw.css`, canvas Builder.dc.html): `.wbw-top` white top bar, padding 16px 32px, hairline below, `position: sticky` under the WordPress admin bar (top 32px; 46px under 782px; 0 under 600px, where WordPress lets the admin bar scroll away). `.wbw-cols` three columns on paper, gap 24, padding 24px 32px 32px, no wrapping above 782px: `.wbw-left` `flex: 0 0 300px` (240px under 1100px, where 300 + 340 + 340 does not fit beside the WordPress menu), `.wbw-mid` `flex: 1 1 340px` with one white card per section (radius 22, padding 20px 24px, 24px apart, hairline between rows; a wide control drops under its words in a narrow column), `.wbw-right` `flex: 1 1 340px`, its `.wbw-preview` sticky 24px under the top bar (the JS writes the bar's height into `--wbw-top-h`; static under 782px, below the settings).

## Builder blocks and Add a block
- `.wbw-blocks` the "In this filter" list, 8px between cards. `.wbw-block` white card, radius 14, padding 12px 14px: `.wbw-grip` (ink-3), `.wbw-block__text` > `.wbw-block__title` (15px bold) + `.wbw-block__spec` (12px ink-3: `<kind> · <N values> · <display> · <phone>`, e.g. "Attribute pa_size · 12 values · Squares · Open on phone"), `.wbw-block__state` ("Editing", tool-text) or `.wbw-block__off`. `.is-editing` = the 2px inset ink ring (the only shadow on the screen). `.wbw-block--whole` is "Whole filter" (gear icon), followed by `.wbw-hr` (1px hairline, 12px of air above and below).
- Value counts come from `wp_count_terms()` per taxonomy, the price range and the number of authors, cached 10 minutes in the transient `wpf_builder_counts` (`woofiltersEditAdmin.php`, printed as JSON in `#wpfBuilderCounts`); above 500 the line says "500+ values".
- `.wbw-addblock` (20px under the last block): kicker, `.wbw-find` (36px pill search, white, filters every card as you type), `.wbw-group` per group with a `.wbw-kicker--group` (Basics, Product data, "Attributes · N"), `.wbw-pics` 3 column grid with 8px gaps, `.wbw-chip-btn` for "Show all N" / "Show fewer" (the first 6 attributes show). `.wbw-pic` card: radius 14, padding 10px 6px, 24px icon, `.wbw-pic__name` 11px bold, `.wbw-pic__spec` 11px ink-3 ("12 values", "range", "yes or no", "6 options", "text"). `.is-added` (opacity .55, second line "Added", does nothing), `.wbw-pic--pro` ("· Pro", dimmed, links to Upgrade when Pro is not active).
- Extension points (unchanged): the `woobewoo_pf_builder_block_cards` filter (array of `id`, `label`, `icon`, `pro`, optional `group` = `basics` or `product` and `spec`) and the `woobewoo_pf_builder_block_cards_html` action. Markup printed by the action is kept hidden; every element with `data-block` (optional `data-preset` JSON, `data-group`, `data-spec`) is drawn as a card in its group, and a click on the drawn card clicks the original element, so a listener on it (Pro's presets) still runs.

## Filters home: Import or export
Header: search, "Import or export" (secondary pill, `aria-expanded`), "New filter" (primary). The pill opens `section.wbw-ie` under the header (white, radius 22, padding 24, two columns): Import (`.wbw-drop` paper drop zone, radius 14, padding 24: icon, "Drop a .json file here", "Choose a file" chip, the note, then `.wbw-ie__status` for the progress and result lines) and Export ("Export all (N)" ink pill and the "Pick filters to export" chip; picking shows `.wbw-fcard__pick` tick boxes on every filter card and the chip becomes the ink pill "Export N selected" with a "Stop picking" chip). The round close button, Escape and the header pill close it (pick mode ends too) and give the focus back to the header pill. `admin.woofilters.list.js` runs it.
- Free owns the card and the JSON import: without a handler it reads the file (`format` starting with `wpf-filters/`), keeps each block's `uniqId` when well formed (finder steps, questions and phone looks are keyed by it) and saves every filter as a new row through `woobewoo_pf_save`. Nothing existing is overwritten.
- Export, and an import endpoint of your own, register through the action `woobewoo_pf_filters_import_export` (args: `array( 'count' => int, 'ajax_url' => string )`). Print one hidden descriptor inside it; the card reads every one it finds, the last one wins per key:
  ```php
  add_action( 'woobewoo_pf_filters_import_export', function () {
  	$handlers = array(
  		'export' => array( 'mod' => 'woofilterpro', 'action' => 'woobewoo_pf_export_group', 'idsKey' => 'listIds', 'dataKey' => 'tables', 'fileName' => 'wpf-filters-export.json' ),
  		'import' => array( 'mod' => 'woofilterpro', 'action' => 'importGroup', 'fileKey' => 'import_file' ),
  	);
  	echo '<div class="wpf-ie-handler" data-handlers="' . esc_attr( wp_json_encode( $handlers ) ) . '"></div>';
  } );
  ```
  Export posts `idsKey[]` (the filter ids) with the plugin nonce to `admin-ajax.php` and saves `data[dataKey]` (the JSON text) as `fileName`. Import posts the file as `fileKey` and reads `data.result.imported` / `data.result.skipped` ("3 filters added"), or shows the first error as is. Without an export handler the Export column says that exporting comes with Pro. Pro's own descriptor is `woofilterpro/views/tpl/partAdminButtonsPro.php`.

## Type
`.wbw-kicker` (11px bold uppercase, +16% tracking, ink-3), `.wbw-h1` (36px, `--xl` 40px, `--page` 32px), `.wbw-h2` (20px), `.wbw-lede` (17px ink-2), `.wbw-text` (15px ink-2), `.wbw-small` (14px ink-2), `.wbw-muted` (13px ink-2), `.wbw-meta` (13px ink-3), `.wbw-big` (36px light; `--sm` 32px), `.wbw-label` (15px bold), `.wbw-blue` (12px bold blue text), `.wbw-link` (14px bold ink-2; `--ink`).

## Cards
`.wbw-card` (white, radius 22, padding 24, column flex, gap 12). Variants: `--tight` (16px 20px), `--pane` (20px, gap 14), `--ink` (dark card, white text, blue kicker), `--paper` (paper inset box, radius 14). `.wbw-card__head` title row with a tag on the right. `.wbw-facts` three big numbers (`--meta` for the 12px bold ink-3 captions as on Help). `.wbw-hairline-top` the only border: 1px hairline above a tag row.

## Buttons (pills, 46px, 15px bold)
`.wbw-btn` secondary (chip), `.wbw-btn--primary` (blue, ink text), `.wbw-btn--ink` (ink, white text), `.wbw-btn--graphite` (second dark surface on ink cards), `.wbw-btn--danger` (red text, no fill), sizes `--small` (32px, 13px), `--xs` (30px, 12px), `--round` (44px circle). Works on `<a>` and `<button>`. `.wbw-actions` is the row (gap 8, wraps; `--pad` adds 8px top).

## Tags and chips
`.wbw-tag` 24px pill, 12px bold. Tones: `--pass`, `--partial`, `--fail`, `--publish` (blue tint), default neutral chip. `.wbw-chip` 26px block chip (`--tint`, `--empty`), `.wbw-chips` row. `.wbw-tags` row (gap 8).

## Switches and rows
`.wbw-toggle` is a 52x30 `<button role="switch" aria-checked>`; on = blue track, knob moves in 280ms snap. `.wbw-row` = text left (`.wbw-row__text` with `.wbw-row__title` 15px bold and `.wbw-row__help` 13px ink-2) and the control right, 12px padding, hairline between rows (`:last-child` none). `.wbw-row--card` makes a white card row (wizard Phone step). `.wbw-rows` stacks rows with 8px gap (`--flat` for hairline lists inside a card). `.wbw-list` / `.wbw-list__row` are the History and table style rows.

## Inputs
`.wbw-input` 44px pill, paper background (`--card` white, `--sm` 40px, `--title` 20px bold name input, `--short` 110px). Selects get a chevron; textareas get radius 14. Labels: use `.wbw-field` (label above) or `.wbw-row` (label left). `.wbw-sr` hides a label visually.

## Segmented control, stepper, meter
- `.wbw-seg-ctl` the Desktop / Phone toggle (`button[aria-pressed]`).
- `.wbw-stepper` the setup stepper: `nav.wbw-stepper` (flex, space-between, gap 16, wraps) holds `div.wbw-steps` (the pills, row-gap 8, wraps on phones) on the left and `a.wbw-steps__skip` on the right. Between every two pills sits a connector `<span class="wbw-join" aria-hidden="true">` (14px wide, 2px high, hairline); connectors before the current step get class `done` and turn ink, so the pills read as a sequence. Pills are `.wbw-step` (32px, 12px bold, radius 999): done = chip with a 14px tick icon and the label (no number), current = `aria-current="step"`, ink with white text, next = chip with its number. The "Step 3 of 7" kicker stays in the question card. The wizard (`setupWizard.php`) prints the markup; `admin.setup.js` `paintSteps()` moves the current pill, the ticks and the `done` connectors.
- `.wbw-steps` + `.wbw-step` on their own (gap 6, no connectors) remain for a plain pill row.
- `.wbw-seg` segmented meter: 13 `<span>` children, `.is-on` blue, last lit segment taller (as drawn); `--pass` turns lit segments green.

## Picture cards
`.wbw-cards` 2 column grid (`--4` four columns, `--3` three columns with 6px gap). `.wbw-card-btn` big picture card (`role="radio"`, `aria-checked="true"` draws the 3px inset ring; `.wbw-card-btn__pic`, `__title`, `__text`). `.wbw-pic-btn` small picture radio (paper, radius 14; `--card` white for "Add a block"; `--pro` dimmed). Mini drawings: `.wbw-mini-chip`, `.wbw-mini-sq`, `.wbw-mini-dd`, `.wbw-mini-dot`, `.wbw-mini-bar`.

## Checklist, code, banners
`.wbw-check` (28px green circle + 15px bold text; `--todo` grey number). `.wbw-code` (mono pill with a Copy button). `.wbw-pre` (mono block, paper). `.wbw-banner` result banners `--pass`, `--partial`, `--fail`, `--note`.

## Preview pane
`.wbw-preview` (card) with `.wbw-preview__bar`, `.wbw-preview__frame` (paper inset, iframe scaled to a 1024px desktop by `modules/templates/js/wbw-preview.js`, `data-height` sets the visible height), `.is-phone` wraps the iframe in `.wbw-preview__phone` (300x560 ink bezel) and scales a 390px wide page, `.wbw-preview__note`, `.wbw-preview__loading`.

## Tables, dialogs, details
`.wbw-table` (kicker style headers, hairline rows). `.wbw-dialog` (backdrop + `.wbw-dialog__panel` card). `.wbw-details` (white card with a plain `<summary>` and chevron) for "Advanced".

## Rail
`.wbw-rail` (200px) with `.wbw-rail__brand`, `.wbw-rail__item` (44px pill, `aria-current="page"` = white) and `.wbw-rail__group` (the "Pro" heading). Register a Pro screen through the `mainAdminTabs` filter with `rail_group => 'pro'` and `icon_svg` (24 grid, 1.8 stroke, currentColor); the rail prints it under the Pro heading in registration order. `rail_group => 'setup'` puts an item right after Setup.

## Icons
Inline SVG, `class="wbw-icon"` (24px, 1.8 stroke, round caps, currentColor); `--sm` 18px. No Font Awesome, no dashicons, no emoji.

## Motion and a11y
Snap 280ms `cubic-bezier(.2,1.4,.4,1)`, slide 360ms `cubic-bezier(.2,.9,.1,1)`; `prefers-reduced-motion` turns transitions off. Focus ring 3px solid ink, 3px offset on every focusable element. Targets are at least 44px (pills) or 30px (compact segmented buttons inside a 44px group).
