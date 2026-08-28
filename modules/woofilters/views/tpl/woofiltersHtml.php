<?php
/**
 * Product Filter by WBW - Woofilters HTML
 *
 * @version 3.4.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

WooBeWoo_PF_Html::echoEscapedHtml( WooBeWoo_PF_Dispatcher::applyFilters( 'filtersHtml', $this->html ) );
