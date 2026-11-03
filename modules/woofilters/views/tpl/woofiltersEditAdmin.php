<?php
/**
 * Product Filter by WBW - Builder (4.0, canvas Builder)
 *
 * Full width frame: a sticky top bar (back, name, Live / saved tag, History, Preview on shop,
 * Save) under the WordPress admin bar, then three columns: blocks (each a white card with a
 * spec line, drag to reorder; "Add a block" with a search field and the groups Basics,
 * Product data, Attributes), plain words settings (one white card per section: Basics / Look /
 * Phone / Where it shows / Finder / Advanced), the live preview of the real shop (sticky).
 * admin.builder.js renders the columns from the schema (admin-schema.php).
 *
 * The value counts for the spec lines (terms per taxonomy, the price range, the authors) are
 * computed here and cached in a transient for 10 minutes; the JS reads them from the JSON
 * script below.
 *
 * @version 4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$wbwTitle  = isset( $this->filter['title'] ) ? $this->filter['title'] : '';
$wbwCounts = get_transient( 'wpf_builder_counts' );
if ( ! is_array( $wbwCounts ) || ! isset( $wbwCounts['tax'] ) ) {
	global $wpdb;
	$wbwCounts = array(
		'tax'     => array(),
		'price'   => '',
		'authors' => 0,
	);
	$wbwTaxes  = array( 'product_cat', 'product_tag', 'product_brand', 'pwb-brand' );
	if ( function_exists( 'wc_get_attribute_taxonomies' ) ) {
		foreach ( wc_get_attribute_taxonomies() as $wbwAttr ) {
			if ( isset( $wbwAttr->attribute_name ) ) {
				$wbwTaxes[] = wc_attribute_taxonomy_name( $wbwAttr->attribute_name );
			}
		}
	}
	foreach ( array_unique( $wbwTaxes ) as $wbwTax ) {
		if ( ! taxonomy_exists( $wbwTax ) ) {
			continue;
		}
		$wbwN = wp_count_terms(
			array(
				'taxonomy'   => $wbwTax,
				'hide_empty' => false,
			)
		);
		$wbwCounts['tax'][ $wbwTax ] = is_wp_error( $wbwN ) ? 0 : (int) $wbwN;
	}
	$wbwRange = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in the transient below.
		$wpdb->prepare(
			"SELECT MIN( CAST( pm.meta_value AS DECIMAL( 20, 4 ) ) ) AS mn, MAX( CAST( pm.meta_value AS DECIMAL( 20, 4 ) ) ) AS mx
			 FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s AND pm.meta_value <> '' AND p.post_status = %s AND p.post_type IN ( 'product', 'product_variation' )",
			'_price',
			'publish'
		)
	);
	if ( $wbwRange && null !== $wbwRange->mn && function_exists( 'wc_price' ) ) {
		$wbwFmt = function ( $v ) {
			return trim( html_entity_decode( wp_strip_all_tags( wc_price( (float) $v, array( 'decimals' => 0 ) ) ), ENT_QUOTES, 'UTF-8' ) );
		};
		/* translators: 1: lowest price 2: highest price */
		$wbwCounts['price'] = sprintf( __( '%1$s to %2$s', 'woo-product-filter' ), $wbwFmt( $wbwRange->mn ), $wbwFmt( $wbwRange->mx ) );
	}
	$wbwCounts['authors'] = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- cached in the transient below.
		$wpdb->prepare( "SELECT COUNT( DISTINCT post_author ) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s", 'product', 'publish' )
	);
	set_transient( 'wpf_builder_counts', $wbwCounts, 10 * MINUTE_IN_SECONDS );
}
?>
<div class="wbw-top" id="wpfBuilderTop">
	<div class="wbw-top__left">
		<a class="wbw-top__back" href="<?php echo esc_url( $this->link ); ?>" aria-label="<?php esc_attr_e( 'Back to filters', 'woo-product-filter' ); ?>">←</a>
		<label class="wbw-sr" for="wpfFilterName"><?php esc_html_e( 'Filter name', 'woo-product-filter' ); ?></label>
		<input id="wpfFilterName" class="wbw-input wbw-input--sm wbw-input--title" type="text" value="<?php echo esc_attr( $wbwTitle ); ?>">
		<span class="wbw-tag wbw-tag--pass" id="wpfBuilderState"><?php echo esc_html( $this->stateText ); ?></span>
	</div>
	<div class="wbw-actions">
		<button type="button" class="wbw-btn wpfHistoryOpen" data-filter-id="<?php echo esc_attr( $this->filter['id'] ); ?>" data-filter-title="<?php echo esc_attr( $wbwTitle ); ?>"><?php echo esc_html( sprintf( /* translators: %d: number of versions */ __( 'History (%d)', 'woo-product-filter' ), (int) $this->versionCount ) ); ?></button>
		<a class="wbw-btn" id="wpfBuilderPreviewLink" data-wbw-preview-open title="<?php esc_attr_e( 'Opens your shop in a new tab. It updates by itself as you change settings.', 'woo-product-filter' ); ?>" href="<?php echo esc_url( $this->shopUrl ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview on shop', 'woo-product-filter' ); ?></a>
		<button type="button" class="wbw-btn wbw-btn--primary" id="wpfBuilderSave"><?php esc_html_e( 'Save', 'woo-product-filter' ); ?></button>
	</div>
</div>

<section class="wbw-engine" id="wpfEngineStripe" aria-labelledby="wpfEngineTitle" tabindex="-1">
	<div class="wbw-engine__head">
		<span class="wbw-engine__icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/></svg></span>
		<div class="wbw-engine__text">
			<h2 class="wbw-engine__title" id="wpfEngineTitle"><?php esc_html_e( 'Filter engine', 'woo-product-filter' ); ?></h2>
			<p class="wbw-engine__lead"><?php esc_html_e( 'The engine is the motor that runs this filter on your shop. Settings that belong to the other engine are greyed out.', 'woo-product-filter' ); ?></p>
		</div>
		<div class="wbw-engine__pick" role="radiogroup" aria-labelledby="wpfEngineTitle">
			<button type="button" role="radio" aria-checked="false" data-engine="4"><strong><?php esc_html_e( 'New 4.0', 'woo-product-filter' ); ?></strong><span><?php esc_html_e( 'Recommended', 'woo-product-filter' ); ?></span></button>
			<button type="button" role="radio" aria-checked="false" data-engine="legacy"><strong><?php esc_html_e( 'Classic 3.x', 'woo-product-filter' ); ?></strong><span><?php esc_html_e( 'The old filter', 'woo-product-filter' ); ?></span></button>
		</div>
	</div>
	<details class="wbw-engine__more">
		<summary><?php esc_html_e( 'Which one should I pick?', 'woo-product-filter' ); ?></summary>
		<ul>
			<li><strong><?php esc_html_e( 'New 4.0:', 'woo-product-filter' ); ?></strong> <?php esc_html_e( 'fast, made for phones, and every new feature lands here. Pick this if you are not sure.', 'woo-product-filter' ); ?></li>
			<li><strong><?php esc_html_e( 'Classic 3.x:', 'woo-product-filter' ); ?></strong> <?php esc_html_e( 'the filter exactly as it worked before 4.0. Pick it only if you changed the old filter\'s look with your own CSS code, or you need one of the greyed-out settings.', 'woo-product-filter' ); ?></li>
			<li><strong><?php esc_html_e( 'Switching is safe:', 'woo-product-filter' ); ?></strong> <?php esc_html_e( 'no setting is deleted and you can switch back any time. Shoppers see the change only after you press Save.', 'woo-product-filter' ); ?></li>
		</ul>
	</details>
</section>

<div class="wbw-cols" id="wpfBuilder">
	<aside class="wbw-left" id="wpfBuilderBlocks" aria-label="<?php esc_attr_e( 'Blocks in this filter', 'woo-product-filter' ); ?>">
		<span class="wbw-kicker"><?php esc_html_e( 'In this filter · drag to reorder', 'woo-product-filter' ); ?></span>
		<div class="wbw-blocks" id="wpfBlockList" role="list"></div>
		<div class="wbw-addblock" id="wpfAddBlock">
			<span class="wbw-kicker"><?php esc_html_e( 'Add a block', 'woo-product-filter' ); ?></span>
			<label class="wbw-sr" for="wpfBlockFind"><?php esc_html_e( 'Find a block or attribute', 'woo-product-filter' ); ?></label>
			<input id="wpfBlockFind" class="wbw-find" type="search" autocomplete="off" placeholder="<?php esc_attr_e( 'Find a block or attribute', 'woo-product-filter' ); ?>">
			<div id="wpfBlockCards"></div>
			<p class="wbw-addblock__none" id="wpfBlockCardsNone" hidden><?php esc_html_e( 'Nothing matches. Try another word.', 'woo-product-filter' ); ?></p>
		</div>
		<div id="wpfBlockCardsExtra" hidden><?php do_action( 'woobewoo_pf_builder_block_cards_html', $this->settings ); ?></div>
	</aside>

	<section class="wbw-mid" id="wpfBuilderMid" aria-label="<?php esc_attr_e( 'Settings', 'woo-product-filter' ); ?>"></section>

	<section class="wbw-right" aria-label="<?php esc_attr_e( 'Live preview', 'woo-product-filter' ); ?>">
		<div class="wbw-preview" id="wpfBuilderPreview">
			<div class="wbw-preview__bar">
				<span class="wbw-kicker"><?php esc_html_e( 'Live preview · your shop', 'woo-product-filter' ); ?></span>
				<div class="wbw-seg-ctl" role="group" aria-label="<?php esc_attr_e( 'Preview size', 'woo-product-filter' ); ?>">
					<button type="button" data-device="desktop" aria-pressed="true"><?php esc_html_e( 'Desktop', 'woo-product-filter' ); ?></button>
					<button type="button" data-device="phone" aria-pressed="false"><?php esc_html_e( 'Phone', 'woo-product-filter' ); ?></button>
				</div>
			</div>
			<div class="wbw-preview__frame" id="wpfBuilderFrameWrap" data-height="480">
				<iframe id="wpfBuilderFrame" title="<?php esc_attr_e( 'Your shop page', 'woo-product-filter' ); ?>" src="about:blank"></iframe>
				<div class="wbw-preview__loading" id="wpfBuilderFrameLoading"><?php esc_html_e( 'Updating the preview…', 'woo-product-filter' ); ?></div>
			</div>
			<p class="wbw-preview__note" id="wpfBuilderCount" aria-live="polite"><?php esc_html_e( 'Updated as you change settings.', 'woo-product-filter' ); ?></p>
		</div>
	</section>
</div>
<?php
// Filter level parts other modules add (Pro: A/B test, vehicle finder). The classic Options tab printed them; the 4.0
// builder has no such tab, so they sit under the builder. Nothing is printed when no module adds a part.
ob_start();
/**
 * Filter level parts under the 4.0 builder (Pro: A/B test, vehicle finder). Only parts that work with the 4.0 Save
 * belong here: they act through their own buttons, not through the classic Options form.
 *
 * @since 4.0.0
 * @param array $settings The filter settings.
 * @param int   $filterId The filter id.
 */
do_action( 'woobewoo_pf_builder_filter_parts', isset( $this->settings['settings'] ) && is_array( $this->settings['settings'] ) ? $this->settings['settings'] : (array) $this->settings, (int) $this->filter['id'] );
$wbwExtraParts = trim( (string) ob_get_clean() );
if ( '' !== $wbwExtraParts ) {
	?>
	<section class="wbw-card wbw-builder-extras" id="wpfBuilderExtras" aria-label="<?php esc_attr_e( 'More for this filter', 'woo-product-filter' ); ?>" style="margin:24px 0 0">
		<span class="wbw-kicker"><?php esc_html_e( 'More for this filter', 'woo-product-filter' ); ?></span>
		<?php echo $wbwExtraParts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the parts escape their own output (Pro templates). ?>
	</section>
	<?php
}
?>
<script type="application/json" id="wpfBuilderCounts"><?php echo wp_json_encode( $wbwCounts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ); ?></script>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
