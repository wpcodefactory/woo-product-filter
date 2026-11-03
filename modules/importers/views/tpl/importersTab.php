<?php
/**
 * Product Filter by WBW - Switch from another filter screen
 *
 * Cards per rival (state tag, count, sets as pills), a side by side preview panel and the import
 * report table. Styled with the shared admin skin classes (wbw-card, wbw-kicker, wbw-tag, wbw-btn).
 *
 * @version 4.0.0
 * @since   4.0.0
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$rivals   = $this->rivals;
$found    = array_filter( $rivals, function ( $r ) {
	return $r['installed'];
} );
$stateTag = function ( $r ) {
	if ( 'active' === $r['state'] ) {
		return '<span class="wbw-tag wbw-tag--pass">' . esc_html__( 'Active', 'woo-product-filter' ) . '</span>';
	}
	if ( 'inactive' === $r['state'] ) {
		return '<span class="wbw-tag wbw-tag--partial">' . esc_html__( 'Installed, not active', 'woo-product-filter' ) . '</span>';
	}
	return '<span class="wbw-tag">' . esc_html__( 'Not found', 'woo-product-filter' ) . '</span>';
};
?>
<div class="wbw-importers" id="wpfImporters">
	<section class="wbw-card wbw-importers__hero">
		<span class="wbw-kicker"><?php esc_html_e( 'Switch from another filter', 'woo-product-filter' ); ?></span>
		<h2><?php esc_html_e( 'Bring your filters over in one click', 'woo-product-filter' ); ?></h2>
		<p class="wbw-muted">
			<?php
			if ( $found ) {
				printf(
					/* translators: %d: count */
					esc_html( _n( 'We found %d other filter plugin with settings on this site. Pick a filter to switch: you check what comes over, then choose where ours replaces it. Every switch can be undone.', 'We found %d other filter plugins with settings on this site. Pick a filter to switch: you check what comes over, then choose where ours replaces it. Every switch can be undone.', count( $found ), 'woo-product-filter' ) ),
					(int) count( $found )
				);
			} else {
				esc_html_e( 'No other filter plugin left settings on this site. We look for YITH WooCommerce Ajax Product Filter, HUSKY (WOOF), BeRocket AJAX Product Filters, Filter Everything, annasta Product Filters and the WooCommerce filter widgets and blocks, active or not.', 'woo-product-filter' );
			}
			?>
		</p>
	</section>

	<div class="wbw-importers__cards">
		<?php foreach ( $rivals as $r ) { ?>
			<section class="wbw-card wbw-rival <?php echo $r['installed'] ? '' : 'wbw-rival--missing'; ?>" data-rival="<?php echo esc_attr( $r['slug'] ); ?>">
				<div class="wbw-rival__head">
					<div>
						<span class="wbw-kicker"><?php echo esc_html( $r['name'] ); ?></span>
						<div class="wbw-rival__count">
							<span class="wbw-big"><?php echo esc_html( number_format_i18n( $r['count'] ) ); ?></span>
							<span class="wbw-meta"><?php echo esc_html( _n( 'filter set found', 'filter sets found', $r['count'], 'woo-product-filter' ) ); ?></span>
						</div>
					</div>
					<?php echo $stateTag( $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above ?>
				</div>
				<?php if ( $r['installed'] && $r['count'] ) { ?>
					<ul class="wbw-rival__sets" role="list">
						<?php foreach ( $r['sets'] as $s ) { ?>
							<li class="wbw-rival__set" data-set="<?php echo esc_attr( $s['id'] ); ?>">
								<span class="wbw-chip"><?php echo esc_html( $s['title'] ); ?> <em><?php echo esc_html( sprintf( /* translators: %d: count */ _n( '%d block', '%d blocks', (int) $s['blocks'], 'woo-product-filter' ), (int) $s['blocks'] ) ); ?></em></span>
								<?php if ( ! empty( $s['note'] ) ) { ?><span class="wbw-meta"><?php echo esc_html( $s['note'] ); ?></span><?php } ?>
								<?php if ( ! empty( $s['imported'] ) ) { ?><span class="wbw-tag wbw-tag--publish"><?php echo esc_html( sprintf( /* translators: %d: filter id */ __( 'Imported as filter #%d', 'woo-product-filter' ), (int) $s['imported'] ) ); ?></span><?php } ?>
								<span class="wbw-rival__actions">
									<button type="button" class="wbw-btn wbw-btn--primary wbw-btn--small" data-act="start" data-rival="<?php echo esc_attr( $r['slug'] ); ?>" data-set="<?php echo esc_attr( $s['id'] ); ?>" data-name="<?php echo esc_attr( $s['title'] ); ?>"><?php echo empty( $s['imported'] ) ? esc_html__( 'Switch this filter', 'woo-product-filter' ) : esc_html__( 'Switch again', 'woo-product-filter' ); ?></button>
								</span>
							</li>
						<?php } ?>
					</ul>
					<?php if ( count( $r['sets'] ) > 1 ) { ?>
						<div class="wbw-actions">
							<button type="button" class="wbw-btn" data-act="import" data-rival="<?php echo esc_attr( $r['slug'] ); ?>" data-set="all"><?php esc_html_e( 'Import all as drafts', 'woo-product-filter' ); ?></button>
						</div>
					<?php } ?>
				<?php } elseif ( $r['installed'] ) { ?>
					<p class="wbw-muted"><?php echo 'active' === $r['state'] ? esc_html__( 'Active, but it has no filters set up yet: nothing to bring over.', 'woo-product-filter' ) : esc_html__( 'The plugin left settings but no filter set we can read.', 'woo-product-filter' ); ?></p>
				<?php } else { ?>
					<p class="wbw-muted"><?php esc_html_e( 'Nothing from this plugin on this site.', 'woo-product-filter' ); ?></p>
				<?php } ?>
			</section>
		<?php } ?>
	</div>

	<section class="wbw-card wbw-wizard" id="wpfSwitch" hidden aria-labelledby="wpfSwitchTitle">
		<div class="wbw-wizard__head">
			<div>
				<span class="wbw-kicker" id="wpfSwitchKicker"></span>
				<h2 id="wpfSwitchTitle"></h2>
			</div>
			<button type="button" class="wbw-btn wbw-btn--small" data-act="close"><?php esc_html_e( 'Close', 'woo-product-filter' ); ?></button>
		</div>
		<ol class="wbw-wizard__steps" role="list">
			<li data-step="1"><?php esc_html_e( 'Check what comes over', 'woo-product-filter' ); ?></li>
			<li data-step="2"><?php esc_html_e( 'Choose where it shows', 'woo-product-filter' ); ?></li>
			<li data-step="3"><?php esc_html_e( 'Done', 'woo-product-filter' ); ?></li>
		</ol>
		<div class="wbw-wizard__body" id="wpfSwitchBody"></div>
	</section>

	<section class="wbw-card wbw-preview" id="wpfImportersPreview" hidden>
		<div class="wbw-preview__head">
			<div>
				<span class="wbw-kicker"><?php esc_html_e( 'Side by side', 'woo-product-filter' ); ?></span>
				<h2 id="wpfImportersPreviewTitle"></h2>
			</div>
			<span class="wbw-tag wbw-tag--publish" id="wpfImportersPreviewBlocks"></span>
		</div>
		<div class="wbw-preview__frames">
			<div class="wbw-preview__col">
				<span class="wbw-meta"><?php esc_html_e( 'Their filter', 'woo-product-filter' ); ?></span>
				<div class="wbw-preview__frame" id="wpfImportersTheirs"></div>
			</div>
			<div class="wbw-preview__col">
				<span class="wbw-meta"><?php esc_html_e( 'Ours (draft on your real shop page)', 'woo-product-filter' ); ?></span>
				<div class="wbw-preview__frame" id="wpfImportersOurs"></div>
			</div>
		</div>
		<div id="wpfImportersPreviewReport"></div>
	</section>

	<section class="wbw-card wbw-report" id="wpfImportersReport" hidden>
		<span class="wbw-kicker"><?php esc_html_e( 'Import report', 'woo-product-filter' ); ?></span>
		<h2><?php esc_html_e( 'What came over, and what did not', 'woo-product-filter' ); ?></h2>
		<div id="wpfImportersReportBody"></div>
	</section>
	<?php if ( ! empty( $this->switches ) ) { ?>
	<section class="wbw-card wbw-history" id="wpfSwitchHistory">
		<span class="wbw-kicker"><?php esc_html_e( 'Recent switches', 'woo-product-filter' ); ?></span>
		<table class="wbw-table">
			<thead><tr><th><?php esc_html_e( 'When', 'woo-product-filter' ); ?></th><th><?php esc_html_e( 'From', 'woo-product-filter' ); ?></th><th><?php esc_html_e( 'Our filter', 'woo-product-filter' ); ?></th><th><?php esc_html_e( 'Places changed', 'woo-product-filter' ); ?></th><th></th></tr></thead>
			<tbody>
			<?php foreach ( $this->switches as $sw ) { ?>
				<tr>
					<td><?php echo esc_html( $sw['when'] ); ?></td>
					<td><?php echo esc_html( $sw['rival'] ); ?></td>
					<td><a href="<?php echo esc_url( $sw['edit'] ); ?>">#<?php echo (int) $sw['filter']; ?></a></td>
					<td><?php echo (int) $sw['places']; ?></td>
					<td><?php if ( $sw['undone'] ) { ?><span class="wbw-tag"><?php esc_html_e( 'Undone', 'woo-product-filter' ); ?></span><?php } else { ?><button type="button" class="wbw-btn wbw-btn--small" data-act="undo" data-switch="<?php echo esc_attr( $sw['id'] ); ?>"><?php esc_html_e( 'Undo', 'woo-product-filter' ); ?></button><?php } ?></td>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</section>
	<?php } ?>
	<p class="wbw-meta wbw-importers__live" id="wpfImportersLive" aria-live="polite"></p>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
