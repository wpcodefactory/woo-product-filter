<?php
/**
 * Product Filter by WBW - Woofilters HTML
 *
 * @version 3.1.8
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

HtmlWpf::echoEscapedHtml(DispatcherWpf::applyFilters('filtersHtml', $this->html));
