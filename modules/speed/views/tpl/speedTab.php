<?php
/**
 * Product Filter by WBW - Speed and SEO + Help screen (4.0, canvas Help)
 *
 * One screen, two columns of cards: Index health, Speed, Found by Google, Calm bots, AI shopping
 * readiness (score out of 100 from the Free readiness model, 4.0.0), Help, Support (ink), Accessibility,
 * Advanced settings. The Help rail item opens the same screen
 * scrolled to the Help card.
 *
 * @version 4.0.0
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$h   = $this->health;
$v   = $this->values;
$row = function ( $key, $label, $help, $value ) {
	echo '<div class="wbw-row"><div class="wbw-row__text"><p class="wbw-row__title">' . esc_html( $label ) . '</p><p class="wbw-row__help">' . esc_html( $help ) . '</p></div><button type="button" class="wbw-toggle" role="switch" aria-checked="' . ( '1' === (string) $value ? 'true' : 'false' ) . '" aria-label="' . esc_attr( $label ) . '" data-opt="' . esc_attr( $key ) . '"></button></div>';
};
$r   = $this->readiness; // null when the abilities module is off
$segOn = (int) round( $h['percent'] / 100 * 13 );
?>
<div>
	<h1 class="wbw-h1 wbw-h1--page"><?php echo 'help' === $this->screen ? esc_html__( 'Help', 'woo-product-filter' ) : esc_html__( 'Speed and SEO', 'woo-product-filter' ); ?></h1>
	<p class="wbw-small" style="margin-top:6px"><?php echo 'help' === $this->screen ? esc_html__( 'Check your store, download diagnostics, open a ticket. Speed and SEO switches are on this page too.', 'woo-product-filter' ) : esc_html__( 'Set for you by the smart setup. Every switch says what it does.', 'woo-product-filter' ); ?></p>
</div>

<div class="wbw-grid2" id="wpfSpeed">
	<section class="wbw-card" id="wpfIndexCard" aria-label="<?php esc_attr_e( 'Index health', 'woo-product-filter' ); ?>" data-running="<?php echo $h['running'] ? '1' : '0'; ?>">
		<div class="wbw-card__head"><h2 class="wbw-h2"><?php esc_html_e( 'Index health', 'woo-product-filter' ); ?></h2><span class="wbw-tag <?php echo $h['running'] ? 'wbw-tag--partial' : ( $h['percent'] >= 95 ? 'wbw-tag--pass' : 'wbw-tag--partial' ); ?>" id="wpfIndexState"><?php echo $h['running'] ? esc_html__( 'Rebuilding', 'woo-product-filter' ) : ( $h['percent'] >= 95 ? esc_html__( 'Fresh', 'woo-product-filter' ) : esc_html__( 'Needs a rebuild', 'woo-product-filter' ) ); ?></span></div>
		<p class="wbw-small"><?php esc_html_e( 'The index is a list of every product\'s categories, sizes, prices and stock, kept ready so filters answer in under 50 ms.', 'woo-product-filter' ); ?></p>
		<div class="wbw-facts wbw-facts--meta">
			<div><p class="wbw-big wbw-big--sm" id="wpfIndexIndexed"><?php echo esc_html( number_format_i18n( $h['indexed'] ) ); ?></p><p><?php esc_html_e( 'products indexed', 'woo-product-filter' ); ?></p></div>
			<div><p class="wbw-big wbw-big--sm" id="wpfIndexLast"><?php echo $h['last_run'] ? esc_html( $this->shortAgo( $h['last_run'] ) ) : esc_html__( 'never', 'woo-product-filter' ); ?></p><p><?php esc_html_e( 'since last update', 'woo-product-filter' ); ?></p></div>
			<div><p class="wbw-big wbw-big--sm" id="wpfIndexQuery"><?php echo isset( $h['last_query_ms'] ) && $h['last_query_ms'] ? esc_html( $h['last_query_ms'] . ' ms' ) : '-'; ?></p><p><?php esc_html_e( 'last count query', 'woo-product-filter' ); ?></p></div>
		</div>
		<div class="wbw-seg" id="wpfIndexMeter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $h['percent']; ?>" aria-label="<?php esc_attr_e( 'Rebuild progress', 'woo-product-filter' ); ?>"><?php for ( $i = 0; $i < 13; $i++ ) { echo '<span class="' . ( $i < $segOn ? 'is-on' : '' ) . '"></span>'; } ?></div>
		<p class="wbw-muted"><?php esc_html_e( 'Updates itself when a product, price or stock changes. Big imports run in the background.', 'woo-product-filter' ); ?></p>
		<div class="wbw-actions"><button type="button" class="wbw-btn" id="wpfIndexRebuild" <?php disabled( $h['running'] ); ?>><?php esc_html_e( 'Rebuild the index', 'woo-product-filter' ); ?></button><button type="button" class="wbw-btn" id="wpfClearCache"><?php esc_html_e( 'Clear saved counts', 'woo-product-filter' ); ?></button><span class="wbw-live" id="wpfIndexMsg" aria-live="polite"></span></div>
	</section>

	<section class="wbw-card" aria-label="<?php esc_attr_e( 'Speed', 'woo-product-filter' ); ?>">
		<h2 class="wbw-h2"><?php esc_html_e( 'Speed', 'woo-product-filter' ); ?></h2>
		<div class="wbw-rows wbw-rows--flat">
			<?php $row( 'wbw_cache_counts', __( 'Keep counts in cache', 'woo-product-filter' ), __( 'Counts are reused until a product changes. Filters on the 4.0 engine always keep their counts this way; this switch is for filters on the classic engine.', 'woo-product-filter' ), $v['wbw_cache_counts'] ); ?>
			<?php $row( 'wbw_ajax_results', __( 'Update results without reloading', 'woo-product-filter' ), __( 'The back button still works.', 'woo-product-filter' ), $v['wbw_ajax_results'] ); ?>
			<?php $row( 'wbw_bot_cache', __( 'Give bots a saved copy', 'woo-product-filter' ), __( 'Known crawlers get a cached page, not a fresh query.', 'woo-product-filter' ), $v['wbw_bot_cache'] ); ?>
		</div>
		<div class="wbw-banner wbw-banner--note"><?php echo esc_html( $this->benchmarkLine ); ?> <?php if ( $this->benchmarkUrl ) { ?><a href="<?php echo esc_url( $this->benchmarkUrl ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'See how we measured', 'woo-product-filter' ); ?></a><?php } ?></div>
	</section>

	<section class="wbw-card" aria-label="<?php esc_attr_e( 'Found by Google', 'woo-product-filter' ); ?>">
		<h2 class="wbw-h2"><?php esc_html_e( 'Found by Google', 'woo-product-filter' ); ?></h2>
		<div class="wbw-rows wbw-rows--flat">
			<?php $row( 'wbw_clean_links', __( 'Clean links', 'woo-product-filter' ), __( '/shop/shoes/size-42/ instead of ?filter_size=42. Works on shop, category, tag and brand pages.', 'woo-product-filter' ), $v['wbw_clean_links'] ); ?>
			<?php $row( 'canonical_links', __( 'Tell Google which page is the real one', 'woo-product-filter' ), __( 'Filtered pages point to the plain page (canonical) and ask not to be listed (noindex), unless Pro approves them as answer pages.', 'woo-product-filter' ), $v['canonical_links'] ); ?>
			<?php $row( 'wbw_short_links', __( 'Short links to share', 'woo-product-filter' ), __( '/shop/f/red-shoes-42 and a QR code for any combination.', 'woo-product-filter' ), $v['wbw_short_links'] ); ?>
		</div>
	</section>

	<section class="wbw-card" aria-label="<?php esc_attr_e( 'Calm bots', 'woo-product-filter' ); ?>">
		<div class="wbw-card__head"><h2 class="wbw-h2"><?php esc_html_e( 'Calm bots', 'woo-product-filter' ); ?></h2><span class="wbw-tag wbw-tag--pass"><?php echo esc_html( sprintf( /* translators: %s: number */ _n( '%s request slowed today', '%s requests slowed today', $this->slowedToday, 'woo-product-filter' ), number_format_i18n( $this->slowedToday ) ) ); ?></span></div>
		<div class="wbw-rows wbw-rows--flat">
			<?php $row( 'wbw_bot_limit', __( 'Speed limit for crawlers', 'woo-product-filter' ), sprintf( /* translators: %d: pages per minute */ __( 'A crawler may load %d filtered pages a minute. More than that waits. Your host stays calm.', 'woo-product-filter' ), (int) $v['wbw_bot_limit_per_min'] ), $v['wbw_bot_limit'] ); ?>
			<?php $row( 'wbw_allow_ai_shoppers', __( 'Allow AI shoppers', 'woo-product-filter' ), __( 'ChatGPT, Claude, Perplexity and Google\'s AI may read every filtered page: robots.txt says "Allow: /" for them and the crawler speed limit does not slow them. Off, they wait in line like any other crawler.', 'woo-product-filter' ), $v['wbw_allow_ai_shoppers'] ); ?>
			<?php $row( 'wbw_robots_rules', __( 'Write the rules into robots.txt', 'woo-product-filter' ), __( 'Disallow: /*?wpf_* and friends. Preview below.', 'woo-product-filter' ), $v['wbw_robots_rules'] ); ?>
		</div>
		<pre class="wbw-pre" id="wpfRobotsPreview"><?php echo esc_html( $this->robots ); ?></pre>
	</section>

	<section class="wbw-card" id="wpfReadinessCard" aria-label="<?php esc_attr_e( 'AI shopping readiness', 'woo-product-filter' ); ?>">
		<div class="wbw-card__head"><h2 class="wbw-h2"><?php esc_html_e( 'AI shopping readiness', 'woo-product-filter' ); ?></h2><span class="wbw-tag <?php echo esc_attr( $this->readinessTone( $r ? $r['score'] : null ) ); ?>" id="wpfReadinessTag"><?php echo $r ? esc_html( sprintf( /* translators: %d: score */ __( '%d of 100', 'woo-product-filter' ), (int) $r['score'] ) ) : esc_html__( 'Not checked yet', 'woo-product-filter' ); ?></span></div>
		<p class="wbw-small"><?php esc_html_e( 'How easy it is for AI shoppers (ChatGPT, Claude, Perplexity) to read your shop and use the filter. Each line below says what passes and what to fix.', 'woo-product-filter' ); ?></p>
		<div class="wbw-seg" id="wpfReadinessMeter" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $r ? (int) $r['score'] : 0; ?>" aria-label="<?php esc_attr_e( 'AI shopping readiness', 'woo-product-filter' ); ?>"><?php for ( $i = 0; $i < 13; $i++ ) { echo '<span class="' . ( $r && $i < (int) round( $r['score'] / 100 * 13 ) ? 'is-on' : '' ) . '"></span>'; } ?></div>
		<div class="wbw-list" id="wpfReadinessRows" aria-live="polite">
			<?php foreach ( ( $r ? $r['checks'] : array() ) as $c ) { ?>
				<div class="wbw-list__row"><span class="wbw-small"><?php echo esc_html( $c['label'] ); ?><?php if ( 'pass' !== $c['status'] && ! empty( $c['fix'] ) ) { ?> · <?php echo esc_html( $c['fix'] ); ?><?php } ?></span><span class="wbw-tag <?php echo 'pass' === $c['status'] ? 'wbw-tag--pass' : 'wbw-tag--partial'; ?>"><?php echo 'pass' === $c['status'] ? esc_html__( 'Passes', 'woo-product-filter' ) : esc_html__( 'Fix', 'woo-product-filter' ); ?></span></div>
			<?php } ?>
		</div>
		<div class="wbw-actions"><button type="button" class="wbw-btn" id="wpfReadinessRun"><?php esc_html_e( 'Check again', 'woo-product-filter' ); ?></button><button type="button" class="wbw-btn" id="wpfReadinessLlms" <?php echo $r && ! $this->readinessNeeds( $r, 'llms' ) ? 'hidden' : ''; ?>><?php esc_html_e( 'Create llms.txt', 'woo-product-filter' ); ?></button><span class="wbw-live" id="wpfReadinessMsg" aria-live="polite"></span></div>
	</section>

	<section class="wbw-card" id="help" aria-label="<?php esc_attr_e( 'Help', 'woo-product-filter' ); ?>">
		<h2 class="wbw-h2"><?php esc_html_e( 'Help', 'woo-product-filter' ); ?></h2>
		<div class="wbw-rows wbw-rows--flat">
			<div class="wbw-row"><div class="wbw-row__text"><p class="wbw-row__title"><?php esc_html_e( 'Check my store works with the filter', 'woo-product-filter' ); ?></p><p class="wbw-row__help"><?php esc_html_e( 'Finds your product grid and proves the filter can hook into it.', 'woo-product-filter' ); ?></p></div><button type="button" class="wbw-btn" id="wpfSelfTestRun"><?php esc_html_e( 'Run the check', 'woo-product-filter' ); ?></button></div>
			<div id="wpfSelfTestResult" aria-live="polite"><?php if ( $this->selfTestBanner ) { ?><div class="wbw-banner wbw-banner--<?php echo esc_attr( $this->selfTestBanner['tone'] ); ?>"><?php echo esc_html( $this->selfTestBanner['text'] ); ?></div><?php } ?></div>
			<div class="wbw-row"><div class="wbw-row__text"><p class="wbw-row__title"><?php esc_html_e( 'Download a diagnostics file', 'woo-product-filter' ); ?></p><p class="wbw-row__help"><?php esc_html_e( 'Theme, builders, plugins, filters, index health. No emails or keys. Attach it to a ticket so the first answer is the fix.', 'woo-product-filter' ); ?></p></div><a class="wbw-btn" href="<?php echo esc_url( $this->downloadUrl ); ?>"><?php esc_html_e( 'Download', 'woo-product-filter' ); ?></a></div>
		</div>
	</section>

	<section class="wbw-card wbw-card--ink" aria-label="<?php esc_attr_e( 'Support', 'woo-product-filter' ); ?>">
		<h2 class="wbw-h2"><?php esc_html_e( 'Support', 'woo-product-filter' ); ?></h2>
		<p class="wbw-small"><?php esc_html_e( 'Free: the wordpress.org forum, answered within a few days. Pro: first reply within one business day. That promise is public.', 'woo-product-filter' ); ?></p>
		<div class="wbw-actions">
			<a class="wbw-btn wbw-btn--primary" href="https://woobewoo.com/documentation/" target="_blank" rel="noopener"><?php esc_html_e( 'Read the docs', 'woo-product-filter' ); ?></a>
			<button type="button" class="wbw-btn wbw-btn--graphite" id="wpfTicketOpen"><?php esc_html_e( 'Open a ticket', 'woo-product-filter' ); ?></button>
		</div>
		<form id="wpfTicketForm" class="wbw-rows" hidden>
			<div class="wbw-field"><label for="wpfTicketSubject"><?php esc_html_e( 'Subject', 'woo-product-filter' ); ?></label><input id="wpfTicketSubject" class="wbw-input" type="text" required></div>
			<div class="wbw-field"><label for="wpfTicketMessage"><?php esc_html_e( 'What happens, and what did you expect?', 'woo-product-filter' ); ?></label><textarea id="wpfTicketMessage" class="wbw-input" required></textarea></div>
			<div class="wbw-field"><label for="wpfTicketEmail"><?php esc_html_e( 'Your email for the reply', 'woo-product-filter' ); ?></label><input id="wpfTicketEmail" class="wbw-input" type="email" value="<?php echo esc_attr( $this->userEmail ); ?>" required></div>
			<div class="wbw-field"><label for="wpfTicketTo"><?php esc_html_e( 'Send to', 'woo-product-filter' ); ?></label><input id="wpfTicketTo" class="wbw-input" type="email" value="<?php echo esc_attr( $this->supportEmail ); ?>" required></div>
			<div class="wbw-row" style="border:0"><div class="wbw-row__text"><p class="wbw-row__title"><?php esc_html_e( 'Attach diagnostics', 'woo-product-filter' ); ?></p><p class="wbw-small"><?php esc_html_e( 'The same file as the download above. No emails or keys inside.', 'woo-product-filter' ); ?></p></div><button type="button" class="wbw-toggle" role="switch" aria-checked="true" id="wpfTicketAttach" aria-label="<?php esc_attr_e( 'Attach diagnostics', 'woo-product-filter' ); ?>"></button></div>
			<div class="wbw-actions"><button type="submit" class="wbw-btn wbw-btn--primary" id="wpfTicketSend"><?php esc_html_e( 'Send the ticket', 'woo-product-filter' ); ?></button><span class="wbw-small" id="wpfTicketMsg" aria-live="polite"></span></div>
		</form>
		<?php if ( ! empty( $this->tickets ) ) { ?>
			<p class="wbw-small"><?php echo esc_html( sprintf( /* translators: 1: number, 2: time ago */ _n( '%1$s ticket sent, last one %2$s ago.', '%1$s tickets sent, last one %2$s ago.', count( $this->tickets ), 'woo-product-filter' ), number_format_i18n( count( $this->tickets ) ), human_time_diff( (int) end( $this->tickets )['time'], time() ) ) ); ?></p>
		<?php } ?>
	</section>

	<section class="wbw-card" aria-label="<?php esc_attr_e( 'Accessibility', 'woo-product-filter' ); ?>">
		<div class="wbw-card__head"><h2 class="wbw-h2"><?php esc_html_e( 'Works with screen readers', 'woo-product-filter' ); ?></h2><span class="wbw-tag wbw-tag--pass" id="wpfA11yTag"><?php esc_html_e( 'Checked per filter', 'woo-product-filter' ); ?></span></div>
		<p class="wbw-small"><?php esc_html_e( 'Each filter is checked for real buttons, labels, 44px targets, focus rings and a live region for the result count.', 'woo-product-filter' ); ?></p>
		<div class="wbw-list" id="wpfA11yRows">
			<?php if ( empty( $this->filtersList ) ) { ?><p class="wbw-muted"><?php esc_html_e( 'No filters yet.', 'woo-product-filter' ); ?></p><?php } ?>
			<?php foreach ( $this->filtersList as $f ) { ?>
				<div class="wbw-list__row" data-filter-id="<?php echo esc_attr( $f['id'] ); ?>"><div><span class="wbw-label"><?php echo esc_html( $f['title'] ); ?></span> <code class="wbw-small"><?php echo esc_html( '[' . WPF_SHORTCODE . ' id=' . (int) $f['id'] . ']' ); ?></code></div><div class="wbw-actions"><span class="wbw-tag" data-a11y-tag><?php esc_html_e( 'Not checked yet', 'woo-product-filter' ); ?></span><button type="button" class="wbw-btn wbw-btn--xs" data-copy="<?php echo esc_attr( '[' . WPF_SHORTCODE . ' id=' . (int) $f['id'] . ']' ); ?>"><?php esc_html_e( 'Copy', 'woo-product-filter' ); ?></button><button type="button" class="wbw-btn wbw-btn--xs" data-a11y-run="<?php echo esc_attr( $f['id'] ); ?>"><?php esc_html_e( 'Check', 'woo-product-filter' ); ?></button></div><div class="wbw-list" data-a11y-detail style="flex-basis:100%"></div></div>
			<?php } ?>
		</div>
	</section>

	<details class="wbw-details" id="advanced" <?php echo 'settings' === $this->screen ? 'open' : ''; ?>>
		<summary><?php echo esc_html( sprintf( /* translators: %d: number */ __( 'Advanced settings · %d switches from version 3', 'woo-product-filter' ), count( $this->legacyOptions ) ) ); ?></summary>
		<p class="wbw-muted"><?php esc_html_e( 'Everything from the old Settings screen, in plain words. Saved as you change them.', 'woo-product-filter' ); ?></p>
		<div class="wbw-rows wbw-rows--flat" id="wpfLegacyOptions">
			<?php foreach ( $this->legacyOptions as $key => $opt ) { ?>
				<div class="wbw-row">
					<div class="wbw-row__text"><p class="wbw-row__title"><?php echo esc_html( $opt['label'] ); ?></p><?php if ( ! empty( $opt['desc'] ) ) { ?><p class="wbw-row__help"><?php echo esc_html( $opt['desc'] ); ?></p><?php } ?></div>
					<?php if ( 'switch' === $opt['type'] ) { ?>
						<button type="button" class="wbw-toggle" role="switch" aria-checked="<?php echo '1' === (string) $opt['value'] ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( $opt['label'] ); ?>" data-legacy="<?php echo esc_attr( $key ); ?>"></button>
					<?php } elseif ( 'select' === $opt['type'] ) { ?>
						<select class="wbw-input wbw-input--sm" data-legacy="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( $opt['label'] ); ?>"><?php foreach ( $opt['options'] as $ok => $ol ) { ?><option value="<?php echo esc_attr( $ok ); ?>" <?php selected( (string) $ok, (string) $opt['value'] ); ?>><?php echo esc_html( $ol ); ?></option><?php } ?></select>
					<?php } elseif ( 'button' === $opt['type'] ) { ?>
						<button type="button" class="wbw-btn wbw-btn--small" data-legacy-action="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $opt['button'] ); ?></button>
					<?php } else { ?>
						<input class="wbw-input wbw-input--sm" type="text" value="<?php echo esc_attr( $opt['value'] ); ?>" data-legacy="<?php echo esc_attr( $key ); ?>" aria-label="<?php echo esc_attr( $opt['label'] ); ?>" style="width:160px">
					<?php } ?>
				</div>
			<?php } ?>
		</div>
		<p class="wbw-live" id="wpfLegacyMsg" aria-live="polite"></p>
	</details>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
