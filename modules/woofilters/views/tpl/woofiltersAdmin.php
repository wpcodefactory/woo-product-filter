<?php
/**
 * Product Filter by WBW - Filters home (4.0, canvas FilterList)
 *
 * Header with search, "Import or export" and New filter; the Import or export card (drop zone,
 * export pills, pick mode with a tick box on every card); the first week card (ink) when due; one card
 * per filter with a Live / Draft tag, where it shows, block chips, the edited / versions /
 * products line and the actions Edit, Duplicate, History, Delete; the History drawer.
 *
 * @version 4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$wbwPages = array(
	'shop'        => esc_html__( 'the Shop page', 'woo-product-filter' ),
	'category'    => esc_html__( 'category pages', 'woo-product-filter' ),
	'tag'         => esc_html__( 'tag pages', 'woo-product-filter' ),
	'product'     => esc_html__( 'the product page', 'woo-product-filter' ),
	'both'        => esc_html__( 'Shop and category pages', 'woo-product-filter' ),
	'all_pages'   => esc_html__( 'every page', 'woo-product-filter' ),
	'specific'    => esc_html__( 'some pages', 'woo-product-filter' ),
	'custom_cats' => esc_html__( 'some categories', 'woo-product-filter' ),
	'brand'       => esc_html__( 'brand pages', 'woo-product-filter' ),
	'custom_pwb'  => esc_html__( 'some brand pages', 'woo-product-filter' ),
);
$wbwPlaces = array(
	'above'   => esc_html__( 'Above products', 'woo-product-filter' ),
	'sidebar' => esc_html__( 'Sidebar', 'woo-product-filter' ),
	'drawer'  => esc_html__( 'Drawer on every screen', 'woo-product-filter' ),
	''        => esc_html__( 'Shortcode or block', 'woo-product-filter' ),
);
$wbwCount  = count( $this->filtersList );
$wbwUser   = wp_get_current_user();
?>
<?php
/**
 * Import or export handlers. Free owns the card, the JSON import (every filter in the file is
 * saved through `woobewoo_pf_save`, so nothing of yours is overwritten) and, since 4.0.0, the JSON
 * export (`woobewoo_pf_export_filters`, the same wpf-filters/1 file Pro writes). Pro registers its own
 * export and import endpoints by printing a descriptor inside this action (`div.wpf-ie-handler`
 * with the JSON in data-handlers); the card's JS reads every one it finds, last one wins per key.
 * See modules/templates/css/README-components.md.
 *
 * @since 4.0.0
 */
ob_start();
do_action(
	'woobewoo_pf_filters_import_export',
	array(
		'count'    => $wbwCount,
		'ajax_url' => admin_url( 'admin-ajax.php' ),
	)
);
$wbwHandlers  = trim( (string) ob_get_clean() );
$wbwHasExport = false !== strpos( $wbwHandlers, '&quot;export&quot;' ) || false !== strpos( $wbwHandlers, '"export"' );
if ( ! $wbwHasExport ) {
	// 4.0.0: Free exports too (same wpf-filters/1 file). Printed before the Pro descriptors, so when Pro is
	// active its own export handler still wins (the card's JS keeps the last handler it finds per key).
	$wbwFreeExport = array(
		'export' => array(
			'mod'      => 'woofilters',
			'action'   => 'woobewoo_pf_export_filters',
			'idsKey'   => 'listIds',
			'dataKey'  => 'tables',
			'fileName' => 'wpf-filters-export.json',
		),
	);
	$wbwHandlers   = '<div class="wpf-ie-handler" data-handlers="' . esc_attr( wp_json_encode( $wbwFreeExport ) ) . '"></div>' . $wbwHandlers;
}
?>
<div class="wbw-filters__bar">
	<div>
		<h1 class="wbw-h1 wbw-h1--page"><?php esc_html_e( 'Filters', 'woo-product-filter' ); ?></h1>
		<p class="wbw-small" style="margin-top:6px"><?php echo esc_html( sprintf( /* translators: %s: number */ _n( '%s filter. Every save is kept, so you can always go back.', '%s filters. Every save is kept, so you can always go back.', $wbwCount, 'woo-product-filter' ), number_format_i18n( $wbwCount ) ) ); ?></p>
	</div>
	<div class="wbw-actions">
		<label class="wbw-sr" for="wpfFilterSearch"><?php esc_html_e( 'Search filters', 'woo-product-filter' ); ?></label>
		<input id="wpfFilterSearch" class="wbw-input wbw-input--card" type="search" placeholder="<?php esc_attr_e( 'Search filters', 'woo-product-filter' ); ?>" style="width:220px">
		<button type="button" class="wbw-btn" id="wpfImportExportToggle" aria-expanded="false" aria-controls="wpfImportExport"><?php esc_html_e( 'Import or export', 'woo-product-filter' ); ?></button>
		<button type="button" class="wbw-btn wbw-btn--primary" id="wpfNewFilter"><?php esc_html_e( 'New filter', 'woo-product-filter' ); ?></button>
	</div>
</div>

<section class="wbw-card wbw-ie" id="wpfImportExport" hidden aria-label="<?php esc_attr_e( 'Import or export filters', 'woo-product-filter' ); ?>" data-count="<?php echo esc_attr( $wbwCount ); ?>">
	<div class="wbw-ie__col">
		<span class="wbw-kicker"><?php esc_html_e( 'Import', 'woo-product-filter' ); ?></span>
		<div class="wbw-drop" id="wpfImportDrop">
			<svg class="wbw-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5"/><path d="M4 17v3h16v-3"/></svg>
			<p class="wbw-drop__title"><?php esc_html_e( 'Drop a .json file here', 'woo-product-filter' ); ?></p>
			<button type="button" class="wbw-btn wbw-btn--chip" id="wpfImportChoose"><?php esc_html_e( 'Choose a file', 'woo-product-filter' ); ?></button>
			<input class="wbw-drop__file" id="wpfImportFile" type="file" accept=".json,application/json" tabindex="-1" aria-hidden="true">
			<p class="wbw-drop__note"><?php esc_html_e( 'New filters are added. Nothing of yours is overwritten.', 'woo-product-filter' ); ?></p>
		</div>
		<p class="wbw-ie__status" id="wpfImportStatus" role="status" aria-live="polite"></p>
	</div>
	<div class="wbw-ie__col wbw-ie__col--export">
		<span class="wbw-kicker"><?php esc_html_e( 'Export', 'woo-product-filter' ); ?></span>
		<p class="wbw-ie__text"><?php esc_html_e( 'A .json file with the settings of your filters. Use it to move filters to another store or to keep a copy.', 'woo-product-filter' ); ?></p>
		<div class="wbw-actions">
			<button type="button" class="wbw-btn wbw-btn--ink" id="wpfExportAll" <?php disabled( 0 === $wbwCount ); ?>><?php echo esc_html( sprintf( /* translators: %s: number */ __( 'Export all (%s)', 'woo-product-filter' ), number_format_i18n( $wbwCount ) ) ); ?></button>
			<button type="button" class="wbw-btn" id="wpfExportPick" aria-pressed="false" <?php disabled( 0 === $wbwCount ); ?>><?php esc_html_e( 'Pick filters to export', 'woo-product-filter' ); ?></button>
			<button type="button" class="wbw-btn wbw-btn--ink" id="wpfExportSelected" hidden disabled aria-live="polite"><?php esc_html_e( 'Export 0 selected', 'woo-product-filter' ); ?></button>
			<button type="button" class="wbw-btn wbw-btn--chip" id="wpfExportPickStop" hidden><?php esc_html_e( 'Stop picking', 'woo-product-filter' ); ?></button>
		</div>
		<p class="wbw-ie__meta"><?php esc_html_e( 'Picking puts a tick box on every filter card; the button then says "Export 2 selected".', 'woo-product-filter' ); ?></p>
		<p class="wbw-ie__status" id="wpfExportStatus" role="status" aria-live="polite"></p>
	</div>
	<button type="button" class="wbw-btn wbw-btn--round wbw-ie__close" id="wpfImportExportClose" aria-label="<?php esc_attr_e( 'Close import or export', 'woo-product-filter' ); ?>">×</button>
	<?php echo wp_kses( $wbwHandlers, array( 'div' => array( 'class' => true, 'data-handlers' => true ) ) ); ?>
</section>

<?php echo $this->firstWeekCard; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in setupFirstWeekCard.php ?>

<?php if ( empty( $this->filtersList ) ) { ?>
	<section class="wbw-card wbw-card--ink" aria-label="<?php esc_attr_e( 'No filters yet', 'woo-product-filter' ); ?>">
		<span class="wbw-kicker"><?php esc_html_e( 'No filters yet', 'woo-product-filter' ); ?></span>
		<h2 class="wbw-h2" style="font-size:22px"><?php esc_html_e( 'One click. Your shop gets its filter.', 'woo-product-filter' ); ?></h2>
		<p class="wbw-small"><?php esc_html_e( 'We look at your products, suggest the right filters for your kind of store, and show them on your real shop page before anything goes live.', 'woo-product-filter' ); ?></p>
		<div class="wbw-actions"><a class="wbw-btn wbw-btn--primary" href="<?php echo esc_url( $this->setupUrl ); ?>"><?php esc_html_e( 'Start the smart setup', 'woo-product-filter' ); ?></a><button type="button" class="wbw-btn wbw-btn--graphite" id="wpfNewFilter2"><?php esc_html_e( 'Build one by hand', 'woo-product-filter' ); ?></button></div>
	</section>
<?php } ?>

<div class="wbw-grid3" id="wpfFilterCards">
	<?php foreach ( $this->filtersList as $row ) { ?>
		<?php
		$fid      = (int) $row['id'];
		$settings = woobewoo_pf_decode_settings( $row['setting_data'] );
		$s        = isset( $settings['settings'] ) && is_array( $settings['settings'] ) ? $settings['settings'] : array();
		$blocks   = $this->versionsModel->blocksSummary( $row['setting_data'] );
		$isDraft  = empty( $blocks ) || ! empty( $s['wbw_draft'] );
		$page     = isset( $s['display_on_page'] ) && isset( $wbwPages[ $s['display_on_page'] ] ) ? $wbwPages[ $s['display_on_page'] ] : $wbwPages['both'];
		$place    = isset( $s['wbw_placement'] ) && isset( $wbwPlaces[ $s['wbw_placement'] ] ) ? $wbwPlaces[ $s['wbw_placement'] ] : $wbwPlaces[''];
		$drawer   = ! empty( $s['mobile_drawer'] ) ? esc_html__( 'Drawer on phones', 'woo-product-filter' ) : esc_html__( 'Same on phones', 'woo-product-filter' );
		$finder   = ! empty( $s['finder']['enabled'] );
		$where    = $isDraft && empty( $s['wbw_placement'] ) && empty( $blocks ) ? esc_html__( 'nowhere yet', 'woo-product-filter' ) : $page;
		$isPaused = ! $isDraft && ! empty( $s['wbw_paused_by'] ) && isset( $s['wbw_placement'] ) && 'shortcode' === $s['wbw_placement'];
		if ( ! $isDraft && isset( $s['wbw_placement'] ) && 'shortcode' === $s['wbw_placement'] ) {
			$where = esc_html__( 'only where its shortcode is pasted', 'woo-product-filter' );
		}
		$stats    = isset( $this->versionStats[ $fid ] ) ? $this->versionStats[ $fid ] : array( 'count' => 0, 'last' => '', 'user' => 0 );
		$title    = '' !== trim( (string) $row['title'] ) ? $row['title'] : sprintf( /* translators: %d: filter id */ esc_html__( 'Filter %d', 'woo-product-filter' ), $fid );
		$edited   = '';
		if ( ! empty( $stats['last'] ) ) {
			$ts     = strtotime( $stats['last'] . ' UTC' );
			$dayDiff = (int) floor( ( time() - $ts ) / DAY_IN_SECONDS );
			if ( 0 === $dayDiff ) {
				$edited = esc_html__( 'Edited today', 'woo-product-filter' );
			} elseif ( 1 === $dayDiff ) {
				$edited = esc_html__( 'Edited yesterday', 'woo-product-filter' );
			} else {
				/* translators: %s: time ago */
				$edited = sprintf( esc_html__( 'Edited %s ago', 'woo-product-filter' ), human_time_diff( $ts, time() ) );
			}
			if ( ! empty( $stats['user'] ) && (int) $stats['user'] === (int) $wbwUser->ID ) {
				$edited .= ' ' . esc_html__( 'by you', 'woo-product-filter' );
			}
		}
		?>
		<article class="wbw-card wbw-fcard" data-id="<?php echo esc_attr( $fid ); ?>" data-title="<?php echo esc_attr( mb_strtolower( $title ) ); ?>">
			<label class="wbw-fcard__pick" for="wpfPick<?php echo esc_attr( $fid ); ?>"><input type="checkbox" id="wpfPick<?php echo esc_attr( $fid ); ?>" class="wpfPickFilter" value="<?php echo esc_attr( $fid ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: filter name */ __( 'Export %s', 'woo-product-filter' ), $title ) ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg></label>
			<div class="wbw-card__head wbw-card__head--top">
				<h2 class="wbw-h2"><?php WooBeWoo_PF_Html::echoEscapedHtml( WooBeWoo_PF_Dispatcher::applyFilters( 'prepareFilterListTitle', esc_html( $title ), $row ) ); ?></h2>
				<?php if ( $isDraft ) { ?>
					<span class="wbw-tag"><?php esc_html_e( 'Draft', 'woo-product-filter' ); ?></span>
				<?php } elseif ( $isPaused ) { ?>
					<span class="wbw-tag wbw-tag--partial" title="<?php esc_attr_e( 'Paused by Smart setup so the shop shows one filter. Undo from History.', 'woo-product-filter' ); ?>"><?php esc_html_e( 'Paused', 'woo-product-filter' ); ?></span>
				<?php } else { ?>
					<span class="wbw-tag wbw-tag--pass"><?php esc_html_e( 'Live', 'woo-product-filter' ); ?></span>
				<?php } ?>
			</div>
			<p class="wbw-small">
				<?php esc_html_e( 'Shows on:', 'woo-product-filter' ); ?> <?php echo esc_html( $where ); ?>
				<?php if ( $finder ) { ?> · <?php esc_html_e( 'Step by step', 'woo-product-filter' ); ?> · <?php esc_html_e( 'One question at a time', 'woo-product-filter' ); ?><?php } else { ?> · <?php echo esc_html( $place ); ?> · <?php echo esc_html( $drawer ); ?><?php } ?>
			</p>
			<div class="wbw-chips">
				<?php if ( empty( $blocks ) ) { ?>
					<span class="wbw-chip wbw-chip--empty"><?php esc_html_e( 'No blocks yet', 'woo-product-filter' ); ?></span>
				<?php } else { ?>
					<?php foreach ( array_slice( $blocks, 0, 8 ) as $b ) { ?><span class="wbw-chip"><?php echo esc_html( $b ); ?></span><?php } ?>
					<?php if ( count( $blocks ) > 8 ) { ?><span class="wbw-chip wbw-chip--empty">+<?php echo esc_html( count( $blocks ) - 8 ); ?></span><?php } ?>
				<?php } ?>
			</div>
			<p class="wbw-meta">
				<?php
				$bits = array();
				if ( $edited ) {
					$bits[] = $edited;
				}
				/* translators: %s: number */
				$bits[] = sprintf( _n( '%s version', '%s versions', max( 1, (int) $stats['count'] ), 'woo-product-filter' ), number_format_i18n( max( 1, (int) $stats['count'] ) ) );
				if ( ! $isDraft ) {
					/* translators: %s: number */
					$bits[] = sprintf( __( '%s products', 'woo-product-filter' ), number_format_i18n( $this->productCount ) );
				}
				echo esc_html( implode( ' · ', $bits ) );
				?>
			</p>
			<div class="wbw-fcard__actions">
				<a class="wbw-btn wbw-btn--small wbw-btn--ink" href="<?php echo esc_url( $this->getModule()->getEditLink( $fid ) ); ?>"><?php esc_html_e( 'Edit', 'woo-product-filter' ); ?></a>
				<button type="button" class="wbw-btn wbw-btn--small wpfDuplicateFilter" data-filter-id="<?php echo esc_attr( $fid ); ?>" data-filter-title="<?php echo esc_attr( $title ); ?>"><?php esc_html_e( 'Duplicate', 'woo-product-filter' ); ?></button>
				<button type="button" class="wbw-btn wbw-btn--small wpfHistoryOpen" data-filter-id="<?php echo esc_attr( $fid ); ?>" data-filter-title="<?php echo esc_attr( $title ); ?>"><?php esc_html_e( 'History', 'woo-product-filter' ); ?></button>
				<button type="button" class="wbw-btn wbw-btn--small wbw-btn--danger wpfDeleteFilter" data-filter-id="<?php echo esc_attr( $fid ); ?>" data-filter-title="<?php echo esc_attr( $title ); ?>"><?php esc_html_e( 'Delete', 'woo-product-filter' ); ?></button>
			</div>
		</article>
	<?php } ?>
</div>
<p id="wpfFilterCardsNoMatch" class="wbw-muted" hidden><?php esc_html_e( 'No filter matches your search.', 'woo-product-filter' ); ?></p>

<?php // History drawer: rendered as a card below the grid, exactly as drawn. Filled by admin.history.js. ?>
<section class="wbw-card wbw-history" id="wpfHistory" hidden aria-label="<?php esc_attr_e( 'History', 'woo-product-filter' ); ?>">
	<div class="wbw-card__head">
		<div>
			<span class="wbw-kicker" id="wpfHistoryKicker"><?php esc_html_e( 'History', 'woo-product-filter' ); ?></span>
			<p class="wbw-small" style="margin-top:4px"><?php esc_html_e( 'Every save is a version. Restoring makes a new version first, so nothing is ever lost.', 'woo-product-filter' ); ?></p>
		</div>
		<button type="button" class="wbw-btn wbw-btn--round" id="wpfHistoryClose" aria-label="<?php esc_attr_e( 'Close history', 'woo-product-filter' ); ?>">×</button>
	</div>
	<div class="wbw-list" id="wpfHistoryList" aria-live="polite"></div>
</section>

<?php // New filter / duplicate dialog. ?>
<div class="wbw-dialog" id="wpfNameDialog" hidden role="dialog" aria-modal="true" aria-labelledby="wpfNameDialogTitle">
	<div class="wbw-dialog__backdrop"></div>
	<form class="wbw-dialog__panel" id="wpfNameForm">
		<span class="wbw-kicker" id="wpfNameDialogKicker"><?php esc_html_e( 'New filter', 'woo-product-filter' ); ?></span>
		<h2 class="wbw-h2" id="wpfNameDialogTitle"><?php esc_html_e( 'What should it be called?', 'woo-product-filter' ); ?></h2>
		<p class="wbw-small"><?php esc_html_e( 'Shoppers never see this name. You add blocks in the builder next.', 'woo-product-filter' ); ?></p>
		<label class="wbw-sr" for="wpfNameInput"><?php esc_html_e( 'Filter name', 'woo-product-filter' ); ?></label>
		<input id="wpfNameInput" class="wbw-input" type="text" value="" placeholder="<?php esc_attr_e( 'Shop filter', 'woo-product-filter' ); ?>" required>
		<input type="hidden" id="wpfNameDuplicateId" value="">
		<div class="wbw-actions"><button type="submit" class="wbw-btn wbw-btn--primary" id="wpfNameSubmit"><?php esc_html_e( 'Create and open the builder', 'woo-product-filter' ); ?></button><button type="button" class="wbw-btn" id="wpfNameCancel"><?php esc_html_e( 'Cancel', 'woo-product-filter' ); ?></button><span class="wbw-live" id="wpfNameMsg" aria-live="polite"></span></div>
	</form>
</div>
<?php
// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
