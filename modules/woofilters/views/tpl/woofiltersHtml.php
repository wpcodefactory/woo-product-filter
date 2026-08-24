<?php
/**
 * Product Filter by WBW - Woofilters HTML
 *
 * @version 3.3.2
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

HtmlWpf::echoEscapedHtml( WooBeWoo_PF_Dispatcher::applyFilters( 'filtersHtml', $this->html ) );
