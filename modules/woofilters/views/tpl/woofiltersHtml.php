<?php
/**
 * Product Filter by WBW - Woofilters HTML
 *
 * @version 3.2.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

echo wp_kses( DispatcherWpf::applyFilters( 'filtersHtml', $this->html ), HtmlWpf::getAllowedHtmlTags() );
