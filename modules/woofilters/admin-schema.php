<?php
/**
 * Product Filter by WBW - Builder schema (4.0)
 *
 * Maps every plain words control of the builder to the real setting keys and value
 * transforms. Groups: Basics / Look / Phone / Where it shows / Step by step finder /
 * Advanced. Advanced renders EVERY remaining setting key (main keys from
 * getDefaultSettings() plus the legacy option definitions, and the block keys) with plain
 * labels, so nothing from 3.x is lost.
 *
 * Control shape:
 *   id      unique id
 *   label   plain words label
 *   help    one plain sentence (optional)
 *   type    switch | text | number | select | pictures | multi | color | hidden
 *   scope   main | block
 *   key     setting key (main) or block setting key (f_...)
 *   on/off  values written for a switch (default '1' / '')
 *   options value => label (select, pictures, multi)
 *   blocks  block ids the control applies to (block scope); empty = every block
 *   writes  extra keys written with the same value, or callable( value, settings ) => array
 *
 * Filter `woobewoo_pf_admin_schema` to add or change controls (Pro adds its own).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Admin_Schema {

	/**
	 * Display types per block family (4.0 engine contract) with the legacy keys they map to.
	 */
	public static function displayTypes() {
		return array(
			'list'       => array( 'label' => __( 'Tick boxes', 'woo-product-filter' ), 'legacy' => 'list', 'style' => '' ),
			'chips'      => array( 'label' => __( 'Chips', 'woo-product-filter' ), 'legacy' => 'list', 'style' => 'chips' ),
			'squares'    => array( 'label' => __( 'Squares', 'woo-product-filter' ), 'legacy' => 'list', 'style' => 'chips' ),
			'swatches'   => array( 'label' => __( 'Swatches', 'woo-product-filter' ), 'legacy' => 'list', 'style' => 'swatches' ),
			'dropdown'   => array( 'label' => __( 'Dropdown', 'woo-product-filter' ), 'legacy' => 'dropdown', 'style' => '' ),
			'slider'     => array( 'label' => __( 'Slider', 'woo-product-filter' ), 'legacy' => 'list', 'style' => '' ),
			'toggle'     => array( 'label' => __( 'Toggle', 'woo-product-filter' ), 'legacy' => 'list', 'style' => '' ),
			'range_list' => array( 'label' => __( 'Range list', 'woo-product-filter' ), 'legacy' => 'list', 'style' => '' ),
		);
	}

	/**
	 * Which display types each block family offers (picture cards in the Look group).
	 */
	public static function displayTypesFor( $blockId ) {
		switch ( $blockId ) {
			case 'wpfPrice':
				return array( 'slider', 'range_list', 'dropdown' );
			case 'wpfPriceRange':
				return array( 'range_list', 'dropdown' );
			case 'wpfOnSale':
			case 'wpfFeatured':
				return array( 'toggle', 'list' );
			case 'wpfInStock':
				return array( 'toggle', 'list', 'dropdown' );
			case 'wpfSortBy':
				return array( 'dropdown', 'list' );
			case 'wpfRating':
				return array( 'list', 'dropdown' );
			case 'wpfAttribute':
				return array( 'list', 'chips', 'squares', 'swatches', 'dropdown' );
			default:
				// A registered block (WooBeWoo_PF_Blocks) lists its own display types, the first one being its default.
				$displays = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::displays( $blockId ) : null;
				return $displays ? $displays : array( 'list', 'chips', 'dropdown' );
		}
	}

	/**
	 * Block families and their plain names, with the "Add a block" cards (Free).
	 */
	public static function blockCards() {
		$cards = array(
			'wpfCategory'    => array( 'label' => __( 'Category', 'woo-product-filter' ), 'icon' => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="2" y="2" width="10" height="7" rx="2"/><rect x="16" y="2" width="10" height="7" rx="2"/><rect x="2" y="11" width="10" height="7" rx="2"/><rect x="16" y="11" width="10" height="7" rx="2"/></svg>' ),
			'wpfBrand'       => array( 'label' => __( 'Brand', 'woo-product-filter' ), 'icon' => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 10h22M6 5h16M6 15h16"/></svg>' ),
			'wpfTags'        => array( 'label' => __( 'Tag', 'woo-product-filter' ), 'icon' => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 4h9l11 11-6 3L4 10z"/></svg>' ),
			'wpfInStock'     => array( 'label' => __( 'In stock', 'woo-product-filter' ), 'icon' => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="14" cy="10" r="7"/><path d="M10 10l3 3 5-6"/></svg>' ),
			'wpfRating'      => array( 'label' => __( 'Rating', 'woo-product-filter' ), 'icon' => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M14 2l2.5 5.5 6 .6-4.5 4 1.3 5.9L14 15l-5.3 3 1.3-5.9-4.5-4 6-.6z"/></svg>' ),
			'wpfSortBy'      => array( 'label' => __( 'Sort by', 'woo-product-filter' ), 'icon' => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 5h20M4 10h14M4 15h8"/></svg>' ),
			'wpfPrice'       => array( 'label' => __( 'Price', 'woo-product-filter' ), 'icon' => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M3 10h22"/><circle cx="10" cy="10" r="3" fill="currentColor"/><circle cx="19" cy="10" r="3" fill="currentColor"/></svg>' ),
			'wpfOnSale'      => array( 'label' => __( 'On sale', 'woo-product-filter' ), 'icon' => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M5 15L23 5"/><circle cx="8" cy="6" r="2.5"/><circle cx="20" cy="14" r="2.5"/></svg>' ),
			'wpfFeatured'    => array( 'label' => __( 'Featured', 'woo-product-filter' ), 'icon' => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M14 3v14M8 7l6-4 6 4"/><path d="M5 17h18"/></svg>' ),
			'wpfAuthor'      => array( 'label' => __( 'Author', 'woo-product-filter' ), 'icon' => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="14" cy="7" r="4"/><path d="M5 18c1.5-4 5-6 9-6s7.5 2 9 6"/></svg>' ),
			'wpfCustomField' => array( 'label' => __( 'Custom field', 'woo-product-filter' ), 'icon' => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="4" width="22" height="12" rx="3"/><path d="M8 10h12"/></svg>' ),
			'wpfAttribute'   => array( 'label' => __( 'Attribute', 'woo-product-filter' ), 'icon' => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="3" width="6" height="6" rx="1.5"/><rect x="11" y="3" width="6" height="6" rx="1.5"/><rect x="19" y="3" width="6" height="6" rx="1.5"/><rect x="3" y="11" width="6" height="6" rx="1.5"/><rect x="11" y="11" width="6" height="6" rx="1.5"/></svg>' ),
		);
		// Registered blocks (WooBeWoo_PF_Blocks) get a card too; the registry's `woobewoo_pf_builder_block_cards`
		// callback marks a Pro block "· Pro" (linked to Upgrade) when Pro is absent.
		if ( class_exists( 'WooBeWoo_PF_Blocks' ) ) {
			foreach ( WooBeWoo_PF_Blocks::cards() as $id => $card ) {
				if ( ! isset( $cards[ $id ] ) ) {
					$cards[ $id ] = $card;
				}
			}
		}
		return $cards;
	}

	/**
	 * The controls of the plain words groups. Values are read and written through `key`.
	 */
	public static function controls() {
		$yesNoOpen = array(
			'yes_open'  => __( 'Yes, open', 'woo-product-filter' ),
			'yes_close' => __( 'Yes, folded', 'woo-product-filter' ),
			'no'        => __( 'No', 'woo-product-filter' ),
		);
		$controls  = array(
			/* ---------------------------------------------------------- whole filter: basics */
			array( 'id' => 'enable_ajax', 'group' => 'basics', 'scope' => 'main', 'key' => 'enable_ajax', 'type' => 'switch', 'label' => __( 'Update results without reloading', 'woo-product-filter' ), 'help' => __( 'The back button still works.', 'woo-product-filter' ) ),
			array( 'id' => 'auto_update_filter', 'group' => 'basics', 'scope' => 'main', 'key' => 'auto_update_filter', 'type' => 'switch', 'label' => __( 'Apply as soon as shoppers pick', 'woo-product-filter' ), 'help' => __( 'Off: shoppers press a "Filter" button first.', 'woo-product-filter' ), 'writes' => array( 'show_filtering_button' => array( '1' => '0', '' => '1' ) ) ),
			array( 'id' => 'filter_recount', 'group' => 'basics', 'scope' => 'main', 'key' => 'filter_recount', 'type' => 'switch', 'label' => __( 'Update the counts as shoppers pick', 'woo-product-filter' ), 'help' => __( 'Choices that would lead to zero products are hidden or greyed out.', 'woo-product-filter' ) ),
			array( 'id' => 'show_clean_button', 'group' => 'basics', 'scope' => 'main', 'key' => 'show_clean_button', 'type' => 'switch', 'label' => __( 'Add a "Clear all" button', 'woo-product-filter' ), 'help' => __( 'One tap removes every pick.', 'woo-product-filter' ) ),
			array( 'id' => 'remember_filters', 'engine4Only' => true, 'group' => 'basics', 'scope' => 'main', 'key' => 'remember_filters', 'type' => 'switch', 'label' => __( 'Remember the last filters', 'woo-product-filter' ), 'help' => __( 'Next visit: "You filtered by size 42 last time. Use it again?" Saved on the shopper\'s device only.', 'woo-product-filter' ) ),
			array( 'id' => 'variations_as_products', 'group' => 'basics', 'scope' => 'main', 'key' => 'variations_as_products', 'type' => 'switch', 'label' => __( 'Show each variation as its own product', 'woo-product-filter' ), 'help' => __( 'A red 42 and a blue 42 become two cards in the grid.', 'woo-product-filter' ) ),
			array( 'id' => 'open_matching_variation', 'engine4Only' => true, 'group' => 'basics', 'scope' => 'main', 'key' => 'open_matching_variation', 'type' => 'switch', 'label' => __( 'Open the matching variation', 'woo-product-filter' ), 'help' => __( 'Shoppers who picked size 42 land on the product with 42 already chosen.', 'woo-product-filter' ) ),
			array( 'id' => 'sizes_left_badge', 'engine4Only' => true, 'group' => 'basics', 'scope' => 'main', 'key' => 'sizes_left_badge', 'type' => 'switch', 'label' => __( 'Show "only 2 left" on low stock', 'woo-product-filter' ), 'help' => __( 'A small badge on choices that are nearly sold out.', 'woo-product-filter' ) ),
			array( 'id' => 'sizes_left_threshold', 'engine4Only' => true, 'group' => 'basics', 'scope' => 'main', 'key' => 'sizes_left_threshold', 'type' => 'number', 'label' => __( 'Low stock means fewer than', 'woo-product-filter' ), 'help' => __( 'Items left before the badge shows.', 'woo-product-filter' ), 'default' => '3' ),
			array( 'id' => 'pagination_mode', 'engine4Only' => true, 'group' => 'basics', 'scope' => 'main', 'key' => 'pagination_mode', 'type' => 'select', 'label' => __( 'More products load by', 'woo-product-filter' ), 'options' => array( 'pages' => __( 'Page links', 'woo-product-filter' ), 'load_more' => __( 'A "Load more" button', 'woo-product-filter' ), 'infinite' => __( 'Scrolling', 'woo-product-filter' ) ), 'default' => 'pages' ),

			/* ---------------------------------------------------------- whole filter: look */
			array( 'id' => 'legacy_css_check', 'engine4Only' => true, 'group' => 'look', 'scope' => 'main', 'key' => 'legacy_css_check', 'type' => 'switch', 'label' => __( 'Custom CSS / JavaScript still to check on 4.0', 'woo-product-filter' ), 'help' => __( 'Set when this filter moved from the classic engine with custom code written for the old markup. Check the code against docs/engine-4-styling.md, then turn this off.', 'woo-product-filter' ) ),
			array( 'id' => 'wbw_style', 'group' => 'look', 'scope' => 'main', 'key' => 'wbw_style', 'type' => 'pictures', 'label' => __( 'Look', 'woo-product-filter' ), 'options' => array( 'clean' => __( 'Clean', 'woo-product-filter' ), 'chips' => __( 'Chips', 'woo-product-filter' ), 'swatches' => __( 'Swatches', 'woo-product-filter' ), 'compact' => __( 'Compact', 'woo-product-filter' ) ), 'default' => 'clean' ),
			array( 'id' => 'wbw_accent', 'group' => 'look', 'scope' => 'main', 'key' => 'wbw_accent', 'type' => 'color', 'label' => __( 'Accent colour', 'woo-product-filter' ), 'help' => __( 'Picked chips, the slider and the results button. Empty: the colour of your theme.', 'woo-product-filter' ) ),
			array( 'id' => 'filter_width', 'group' => 'look', 'scope' => 'main', 'key' => 'filter_width', 'type' => 'number', 'label' => __( 'Width of the whole filter', 'woo-product-filter' ), 'help' => __( 'Empty: as wide as the space it sits in.', 'woo-product-filter' ), 'unit_key' => 'filter_width_in' ),
			array( 'id' => 'side_width', 'engine4Only' => true, 'group' => 'look', 'scope' => 'main', 'key' => 'side_width', 'type' => 'number', 'label' => __( 'Width of the filter column', 'woo-product-filter' ), 'help' => __( 'In pixels, when the filter sits beside the products. Empty: the width of your theme\'s sidebar.', 'woo-product-filter' ) ),
			array( 'id' => 'disable_plugin_styles', 'group' => 'look', 'scope' => 'main', 'key' => 'disable_plugin_styles', 'type' => 'switch', 'label' => __( 'Use the theme look instead of ours', 'woo-product-filter' ), 'help' => __( 'The filter borrows fonts and colours from your theme.', 'woo-product-filter' ) ),

			/* ---------------------------------------------------------- whole filter: phone */
			array( 'id' => 'mobile_drawer', 'group' => 'phone', 'scope' => 'main', 'key' => 'mobile_drawer', 'type' => 'switch', 'label' => __( 'Open as a drawer on phones', 'woo-product-filter' ), 'help' => __( 'Under 768px wide. Desktop keeps the sidebar.', 'woo-product-filter' ) ),
			array( 'id' => 'mobile_drawer_text', 'group' => 'phone', 'scope' => 'main', 'key' => 'mobile_drawer_text', 'type' => 'text', 'label' => __( 'The button says', 'woo-product-filter' ), 'default' => __( 'Filters', 'woo-product-filter' ) ),
			array( 'id' => 'mobile_drawer_results', 'group' => 'phone', 'scope' => 'main', 'key' => 'mobile_drawer_results', 'type' => 'switch', 'label' => __( 'Show the count on the button', 'woo-product-filter' ), 'help' => __( '"Filters (3)" when three things are picked, and a "Show N results" button in the drawer.', 'woo-product-filter' ) ),
			array( 'id' => 'mobile_drawer_chips', 'group' => 'phone', 'scope' => 'main', 'key' => 'mobile_drawer_chips', 'type' => 'switch', 'label' => __( 'Show picked things as chips at the top', 'woo-product-filter' ), 'help' => __( 'Each chip has an x to remove it.', 'woo-product-filter' ) ),
			array( 'id' => 'mobile_drawer_always', 'group' => 'phone', 'scope' => 'main', 'key' => 'mobile_drawer_always', 'type' => 'switch', 'label' => __( 'Use the drawer on every screen size', 'woo-product-filter' ), 'help' => __( 'Desktop gets the "Filters" button too.', 'woo-product-filter' ) ),
			array( 'id' => 'mobile_overrides_enabled', 'engine4Only' => true, 'group' => 'phone', 'scope' => 'main', 'key' => 'mobile_overrides[enabled]', 'type' => 'switch', 'label' => __( 'Different look on phones', 'woo-product-filter' ), 'help' => __( 'Off: phones use the same look as desktop. On: pick a look per block below.', 'woo-product-filter' ) ),
			array( 'id' => 'mobile_overrides_layout', 'engine4Only' => true, 'group' => 'phone', 'scope' => 'main', 'key' => 'mobile_overrides[layout]', 'type' => 'select', 'label' => __( 'Blocks on phones', 'woo-product-filter' ), 'options' => array( 'stacked' => __( 'Stacked, one under the other', 'woo-product-filter' ), 'accordion' => __( 'Folded, one open at a time', 'woo-product-filter' ) ), 'default' => 'accordion', 'show_when' => 'mobile_overrides[enabled]' ),

			/* ---------------------------------------------------------- whole filter: where it shows */
			array( 'id' => 'display_on_page', 'group' => 'where', 'scope' => 'main', 'key' => 'display_on_page', 'type' => 'select', 'label' => __( 'Show on', 'woo-product-filter' ), 'options' => array( 'both' => __( 'Shop and category pages', 'woo-product-filter' ), 'shop' => __( 'The Shop page only', 'woo-product-filter' ), 'category' => __( 'Category pages only', 'woo-product-filter' ), 'tag' => __( 'Tag pages', 'woo-product-filter' ), 'product' => __( 'Single product page', 'woo-product-filter' ), 'all_pages' => __( 'Every page (Pro)', 'woo-product-filter' ), 'specific' => __( 'Only some pages (Pro)', 'woo-product-filter' ), 'custom_cats' => __( 'Only some categories (Pro)', 'woo-product-filter' ) ), 'default' => 'both' ),
			array( 'id' => 'wbw_placement', 'group' => 'where', 'scope' => 'main', 'key' => 'wbw_placement', 'type' => 'pictures', 'label' => __( 'Where on the page', 'woo-product-filter' ), 'options' => array( 'sidebar' => __( 'Sidebar', 'woo-product-filter' ), 'above' => __( 'Above products', 'woo-product-filter' ), 'drawer' => __( 'Drawer only', 'woo-product-filter' ), '' => __( 'Shortcode or block', 'woo-product-filter' ) ), 'default' => '' ),
			array( 'id' => 'top_bar', 'engine4Only' => true, 'group' => 'where', 'scope' => 'main', 'key' => 'top_bar', 'type' => 'select', 'label' => __( 'Show as a bar of dropdown buttons', 'woo-product-filter' ), 'help' => __( 'One row: "All filters" opens every filter in a pop-up, and each filter has its own dropdown button. Phones keep the normal layout or the drawer.', 'woo-product-filter' ), 'options' => array( '' => __( 'When the filter sits above the products', 'woo-product-filter' ), '1' => __( 'Always', 'woo-product-filter' ), '0' => __( 'Never', 'woo-product-filter' ) ), 'default' => '' ),
			array( 'id' => 'product_container_selector', 'group' => 'where', 'scope' => 'main', 'key' => 'product_container_selector', 'type' => 'text', 'label' => __( 'Where your product list lives', 'woo-product-filter' ), 'help' => __( 'A CSS selector. Empty: we find it (ul.products on most themes).', 'woo-product-filter' ), 'mono' => true ),
			array( 'id' => 'display_on_page_shortcode', 'group' => 'where', 'scope' => 'main', 'key' => 'display_on_page_shortcode', 'type' => 'switch', 'label' => __( 'Use the same page rule for the shortcode', 'woo-product-filter' ), 'help' => __( 'Off: the shortcode shows wherever you paste it.', 'woo-product-filter' ) ),
			array( 'id' => 'display_for', 'group' => 'where', 'scope' => 'main', 'key' => 'display_for', 'type' => 'select', 'label' => __( 'Which devices', 'woo-product-filter' ), 'options' => array( 'both' => __( 'Phones and desktop', 'woo-product-filter' ), 'mobile' => __( 'Phones only', 'woo-product-filter' ), 'desktop' => __( 'Desktop only', 'woo-product-filter' ) ), 'default' => 'both' ),
			array( 'id' => 'hide_without_products', 'group' => 'where', 'scope' => 'main', 'key' => 'hide_without_products', 'type' => 'switch', 'label' => __( 'Hide the filter when a page has no products', 'woo-product-filter' ) ),

			/* ---------------------------------------------------------- whole filter: step by step finder */
			array( 'id' => 'finder_enabled', 'engine4Only' => true, 'group' => 'finder', 'scope' => 'main', 'key' => 'finder[enabled]', 'type' => 'switch', 'label' => __( 'Ask one question at a time', 'woo-product-filter' ), 'help' => __( 'On phones the filter becomes a short quiz: "What sport?", "What size?", then the results.', 'woo-product-filter' ) ),
			array( 'id' => 'finder_show_counts', 'engine4Only' => true, 'group' => 'finder', 'scope' => 'main', 'key' => 'finder[show_counts]', 'type' => 'switch', 'label' => __( 'Show how many products each answer has', 'woo-product-filter' ), 'show_when' => 'finder[enabled]' ),
			array( 'id' => 'finder_steps', 'group' => 'finder', 'scope' => 'main', 'key' => 'finder[steps]', 'type' => 'finder_steps', 'label' => __( 'Order of questions', 'woo-product-filter' ), 'help' => __( 'Drag to reorder. The question text is what shoppers read.', 'woo-product-filter' ), 'show_when' => 'finder[enabled]' ),

			/* ---------------------------------------------------------- per block: basics */
			array( 'id' => 'f_title', 'group' => 'basics', 'scope' => 'block', 'key' => 'f_title', 'type' => 'text', 'label' => __( 'Title shoppers see', 'woo-product-filter' ) ),
			array( 'id' => 'pick_many', 'group' => 'basics', 'scope' => 'block', 'key' => 'logic', 'type' => 'switch', 'label' => __( 'Shoppers can pick more than one', 'woo-product-filter' ), 'help' => __( 'Size 42 or 43 shows both.', 'woo-product-filter' ), 'on' => 'or', 'off' => 'single', 'blocks' => array( 'wpfAttribute', 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand', 'wpfAuthor', 'wpfCustomField', 'wpfInStock', 'wpfRating' ) ),
			array( 'id' => 'f_show_count', 'group' => 'basics', 'scope' => 'block', 'key' => 'f_show_count', 'type' => 'switch', 'label' => __( 'Show how many products each choice has', 'woo-product-filter' ), 'help' => __( '"42 (58)". Counts come from the index, so this is fast.', 'woo-product-filter' ), 'blocks' => array( 'wpfAttribute', 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand', 'wpfAuthor', 'wpfCustomField', 'wpfRating', 'wpfInStock' ) ),
			array( 'id' => 'f_hide_empty', 'group' => 'basics', 'scope' => 'block', 'key' => 'f_hide_empty', 'type' => 'switch', 'label' => __( 'Hide choices with no products', 'woo-product-filter' ), 'help' => __( 'Sold out sizes disappear instead of leading nowhere.', 'woo-product-filter' ), 'blocks' => array( 'wpfAttribute', 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand', 'wpfAuthor', 'wpfCustomField' ) ),
			array( 'id' => 'f_list', 'group' => 'basics', 'scope' => 'block', 'key' => 'f_list', 'type' => 'select', 'label' => __( 'Which attribute', 'woo-product-filter' ), 'options' => 'attributes', 'blocks' => array( 'wpfAttribute' ) ),
			array( 'id' => 'f_options_sort', 'group' => 'basics', 'scope' => 'block', 'key' => 'f_options[]', 'type' => 'multi', 'label' => __( 'Ways to sort', 'woo-product-filter' ), 'options' => 'sortby', 'blocks' => array( 'wpfSortBy' ) ),
			array( 'id' => 'f_options_stock', 'group' => 'basics', 'scope' => 'block', 'key' => 'f_options[]', 'type' => 'multi', 'label' => __( 'Stock states to offer', 'woo-product-filter' ), 'options' => 'instock', 'blocks' => array( 'wpfInStock' ) ),
			array( 'id' => 'f_checkbox_label', 'group' => 'basics', 'scope' => 'block', 'key' => 'f_checkbox_label', 'type' => 'text', 'label' => __( 'Label next to the switch', 'woo-product-filter' ), 'blocks' => array( 'wpfOnSale', 'wpfFeatured' ) ),
			array( 'id' => 'f_customfield', 'group' => 'basics', 'scope' => 'block', 'key' => 'f_customfield_', 'type' => 'select', 'label' => __( 'Which custom field', 'woo-product-filter' ), 'options' => 'customfields', 'blocks' => array( 'wpfCustomField' ) ),

			/* ---------------------------------------------------------- per block: look */
			array( 'id' => 'display_type', 'group' => 'look', 'scope' => 'block', 'key' => 'display_type', 'type' => 'display_type', 'label' => __( 'Look · pick a picture', 'woo-product-filter' ) ),
			array( 'id' => 'show_more_rows', 'engine4Only' => true, 'group' => 'look', 'scope' => 'block', 'key' => 'show_more_rows', 'type' => 'number', 'label' => __( 'Show more after this many rows', 'woo-product-filter' ), 'help' => __( '0 shows everything. Above that, the rest sits behind a "Show more" link.', 'woo-product-filter' ), 'default' => '0', 'blocks' => array( 'wpfAttribute', 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand', 'wpfAuthor', 'wpfCustomField', 'wpfInStock' ) ), // star pills and price ranges sit in one short group, they never fold
			array( 'id' => 'f_open_desktop', 'engine4Only' => true, 'group' => 'look', 'scope' => 'block', 'key' => 'f_enable_title', 'type' => 'switch', 'label' => __( 'Open this block on desktop', 'woo-product-filter' ), 'help' => __( 'Off: the block starts folded under its title; shoppers open it with the arrow.', 'woo-product-filter' ), 'on' => 'yes_open', 'off' => 'yes_close' ),
			array( 'id' => 'f_price_bars', 'engine4Only' => true, 'group' => 'look', 'scope' => 'block', 'key' => 'f_price_bars', 'type' => 'switch', 'label' => __( 'Show where most prices are', 'woo-product-filter' ), 'help' => __( 'Little bars above the slider: tall where many products cost that much.', 'woo-product-filter' ), 'default' => '1', 'blocks' => array( 'wpfPrice' ) ),
			array( 'id' => 'f_few_as_tabs', 'engine4Only' => true, 'group' => 'look', 'scope' => 'block', 'key' => 'f_few_as_tabs', 'type' => 'switch', 'label' => __( 'Two to four choices become tabs', 'woo-product-filter' ), 'help' => __( 'A short list such as "Men · Women · Kids" shows as one row of tabs, easy to tap.', 'woo-product-filter' ), 'default' => '1', 'blocks' => array( 'wpfAttribute', 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand', 'wpfAuthor', 'wpfCustomField', 'wpfInStock' ) ),
			array( 'id' => 'show_more_top', 'engine4Only' => true, 'group' => 'look', 'scope' => 'block', 'key' => 'show_more_top', 'type' => 'number', 'label' => __( 'Show the most popular values first', 'woo-product-filter' ), 'help' => __( 'How many values show before "Show more", most products first. 0 uses the rows setting above. Colour dots and category trees always use rows.', 'woo-product-filter' ), 'default' => '0', 'blocks' => array( 'wpfAttribute', 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand', 'wpfAuthor', 'wpfCustomField', 'wpfInStock' ) ),
			array( 'id' => 'show_more_label', 'engine4Only' => true, 'group' => 'look', 'scope' => 'block', 'key' => 'show_more_label', 'type' => 'text', 'label' => __( 'The "Show more" link says', 'woo-product-filter' ), 'default' => __( 'Show more', 'woo-product-filter' ), 'blocks' => array( 'wpfAttribute', 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand', 'wpfAuthor', 'wpfCustomField', 'wpfInStock' ) ), // star pills and price ranges sit in one short group, they never fold

			/* ---------------------------------------------------------- per block: phone */
			array( 'id' => 'f_enable_title_mobile', 'group' => 'phone', 'scope' => 'block', 'key' => 'f_enable_title_mobile', 'type' => 'switch', 'label' => __( 'Open this block on phones', 'woo-product-filter' ), 'help' => __( 'Shown first in the drawer, already open.', 'woo-product-filter' ), 'on' => 'yes_open', 'off' => 'yes_close' ),
			array( 'id' => 'mobile_display_type', 'group' => 'phone', 'scope' => 'block', 'key' => 'mobile_display_type', 'type' => 'mobile_display_type', 'label' => __( 'Different look on phones', 'woo-product-filter' ), 'help' => __( 'Off: phones use the same look as desktop.', 'woo-product-filter' ) ),

			/* ---------------------------------------------------------- per block: title (advanced, plain) */
			array( 'id' => 'f_enable_title', 'group' => 'advanced', 'scope' => 'block', 'key' => 'f_enable_title', 'type' => 'select', 'label' => __( 'Show the block title on desktop', 'woo-product-filter' ), 'options' => $yesNoOpen, 'default' => 'yes_open' ),
		);
		// Controls of registered blocks (WooBeWoo_PF_Blocks), each limited to its own block id.
		if ( class_exists( 'WooBeWoo_PF_Blocks' ) ) {
			$controls = array_merge( $controls, WooBeWoo_PF_Blocks::controls() );
		}
		return $controls;
	}

	/**
	 * Plain labels for EVERY main setting key from 3.x and 4.0 (used by Advanced).
	 * type: switch | text | number | select | color | hidden
	 */
	public static function mainKeys() {
		$px = array( 'px' => 'px', '%' => '%' );
		return array(
			'ajax_pagination'                   => array( 'label' => __( 'Page links update without reloading', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'behaviour' ),
			'all_products_filtering'            => array( 'label' => __( 'Always filter the whole catalogue', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'behaviour' ),
			'check_get_names'                   => array( 'label' => __( 'Read picks from the web address', 'woo-product-filter' ), 'help' => __( 'Can slow big catalogues.', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'behaviour' ),
			'checked_items_bold'                => array( 'label' => __( 'Show picked choices in bold', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'look' ),
			'checked_items_top'                 => array( 'label' => __( 'Move picked choices to the top', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'look' ),
			'apply_button_word'                 => array( 'engine4Only' => true, 'label' => __( 'The Apply button says', 'woo-product-filter' ), 'help' => __( 'Shown when "Apply as soon as shoppers pick" is off.', 'woo-product-filter' ), 'type' => 'text', 'section' => 'buttons' ),
			'loading_overlay'                   => array( 'engine4Only' => true, 'label' => __( 'Dim the products while loading', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'loading' ),
			'columns_product_shop'              => array( 'label' => __( 'Products per row', 'woo-product-filter' ), 'type' => 'number', 'section' => 'products' ),
			'count_product_shop'                => array( 'label' => __( 'Products per page', 'woo-product-filter' ), 'type' => 'number', 'section' => 'products' ),
			'desctop_mobile_breakpoint_switcher' => array( 'label' => __( 'Use my own phone breakpoint', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'phone' ),
			'desctop_mobile_breakpoint_width'   => array( 'label' => __( 'Phone layout kicks in below this width (px)', 'woo-product-filter' ), 'type' => 'number', 'section' => 'phone' ),
			'disable_fontawesome_loading'       => array( 'label' => __( 'Do not load the icon font', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'look' ),
			'display_only_children_category'    => array( 'label' => __( 'On a category page, show only its direct sub-categories', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'pages' ),
			'display_only_descendants_category' => array( 'label' => __( 'On a category page, show only its sub-categories (all levels)', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'pages' ),
			'display_status_private'            => array( 'label' => __( 'Include private products', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'products' ),
			'do_not_use_shortcut'               => array( 'label' => __( 'Ignore the shortcode settings on standard shop pages', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'pages' ),
			'enable_overlay'                    => array( 'label' => __( 'Dim the products while loading', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'loading' ),
			'enable_overlay_icon'               => array( 'label' => __( 'Show a spinner while loading', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'loading' ),
			'enable_overlay_word'               => array( 'label' => __( 'Show a word while loading', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'loading' ),
			'exclude_backorder_variations'      => array( 'label' => __( 'Leave out variations on backorder', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'products' ),
			'f_multi_logic'                     => array( 'label' => __( 'A product must match', 'woo-product-filter' ), 'type' => 'select', 'options' => array( 'or' => __( 'Any picked block', 'woo-product-filter' ), 'and' => __( 'All picked blocks', 'woo-product-filter' ) ), 'section' => 'behaviour' ),
			'filter_block_height'               => array( 'label' => __( 'Height of each block (px)', 'woo-product-filter' ), 'type' => 'number', 'section' => 'look' ),
			'filter_block_width'                => array( 'label' => __( 'Width of each block', 'woo-product-filter' ), 'type' => 'number', 'section' => 'look' ),
			'filter_block_width_in'             => array( 'label' => __( 'Block width unit', 'woo-product-filter' ), 'type' => 'select', 'options' => $px, 'section' => 'look' ),
			'filter_block_width_in_mobile'      => array( 'label' => __( 'Block width unit on phones', 'woo-product-filter' ), 'type' => 'select', 'options' => $px, 'section' => 'phone' ),
			'filter_block_width_mobile'         => array( 'label' => __( 'Width of each block on phones', 'woo-product-filter' ), 'type' => 'number', 'section' => 'phone' ),
			'filter_loader_icon_color'          => array( 'label' => __( 'Spinner colour', 'woo-product-filter' ), 'type' => 'color', 'section' => 'loading' ),
			'filter_loader_icon_name'           => array( 'label' => __( 'Spinner style', 'woo-product-filter' ), 'type' => 'text', 'section' => 'loading' ),
			'filter_loader_icon_number'         => array( 'label' => __( 'Spinner number', 'woo-product-filter' ), 'type' => 'number', 'section' => 'loading' ),
			'filter_loader_icon_onload_enable'  => array( 'label' => __( 'Show the spinner while the page loads', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'loading' ),
			'filter_null_disabled'              => array( 'label' => __( 'Grey out choices with no products', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'behaviour' ),
			'filter_recount_price'              => array( 'label' => __( 'Update the price range as shoppers pick', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'behaviour' ),
			'filter_width_in'                   => array( 'label' => __( 'Filter width unit', 'woo-product-filter' ), 'type' => 'select', 'options' => $px, 'section' => 'look' ),
			'filter_width_in_mobile'            => array( 'label' => __( 'Filter width unit on phones', 'woo-product-filter' ), 'type' => 'select', 'options' => $px, 'section' => 'phone' ),
			'filter_width_mobile'               => array( 'label' => __( 'Width of the whole filter on phones', 'woo-product-filter' ), 'type' => 'number', 'section' => 'phone' ),
			'filtering_button_word'             => array( 'label' => __( 'The "Filter" button says', 'woo-product-filter' ), 'type' => 'text', 'section' => 'buttons' ),
			'filtering_by_variations'           => array( 'label' => __( 'Use variation details when filtering', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'products' ),
			'force_show_current'                => array( 'label' => __( 'Show only this filter on the page', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'pages' ),
			'force_theme_templates'             => array( 'label' => __( 'Use the theme product templates', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'products' ),
			'form_filter_by_all_products'       => array( 'label' => __( 'Build the filter from the whole catalogue', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'behaviour' ),
			'form_filter_by_variations'         => array( 'label' => __( 'Build attribute choices from variations', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'products' ),
			'hide_filter_icon'                  => array( 'label' => __( 'Fold a block when its title is clicked', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'look' ),
			'initialise_immediately'            => array( 'label' => __( 'Load the filter right away', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'loading' ),
			'keep_recount_price'                => array( 'label' => __( 'Keep the price range after a reload', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'behaviour' ),
			'main_buttons_order'                => array( 'label' => __( 'Button order', 'woo-product-filter' ), 'type' => 'select', 'options' => array( 'left' => __( 'Filter, then Clear', 'woo-product-filter' ), 'right' => __( 'Clear, then Filter', 'woo-product-filter' ) ), 'section' => 'buttons' ),
			'main_buttons_position'             => array( 'label' => __( 'Where the buttons go', 'woo-product-filter' ), 'type' => 'select', 'options' => array( 'top' => __( 'Top', 'woo-product-filter' ), 'bottom' => __( 'Bottom', 'woo-product-filter' ), 'both' => __( 'Top and bottom', 'woo-product-filter' ) ), 'section' => 'buttons' ),
			'no_redirect_by_no_products'        => array( 'label' => __( 'Stay on the page when nothing matches', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'behaviour' ),
			'overlay_background'                => array( 'label' => __( 'Dim colour', 'woo-product-filter' ), 'type' => 'color', 'section' => 'loading' ),
			'overlay_word'                      => array( 'label' => __( 'The loading word', 'woo-product-filter' ), 'type' => 'text', 'section' => 'loading' ),
			'padding_child_list'                => array( 'label' => __( 'Indent for sub-choices (px)', 'woo-product-filter' ), 'type' => 'number', 'section' => 'look' ),
			'product_list_selector'             => array( 'label' => __( 'Where to show the loading state (CSS selector)', 'woo-product-filter' ), 'type' => 'text', 'mono' => true, 'section' => 'pages' ),
			'recalculate_filters'               => array( 'label' => __( 'Rebuild the filter after each pick', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'behaviour' ),
			'remove_actions'                    => array( 'label' => __( 'Stop other plugins from changing the results', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'behaviour' ),
			'reset_all_filters'                 => array( 'label' => __( 'Clear every filter on the page', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'buttons' ),
			'show_clean_block'                  => array( 'label' => __( 'Add a "Clear" link to each block', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'buttons' ),
			'show_clean_block_word'             => array( 'label' => __( 'The block "Clear" link says', 'woo-product-filter' ), 'type' => 'text', 'section' => 'buttons' ),
			'show_clean_button_word'            => array( 'label' => __( 'The "Clear all" button says', 'woo-product-filter' ), 'type' => 'text', 'section' => 'buttons' ),
			'show_filter_immediately'           => array( 'label' => __( 'Show the filter before it is ready', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'loading' ),
			'show_filtering_button'             => array( 'label' => __( 'Shoppers press a "Filter" button', 'woo-product-filter' ), 'type' => 'select', 'options' => array( '0' => __( 'No, apply at once', 'woo-product-filter' ), '1' => __( 'Yes, show the button', 'woo-product-filter' ) ), 'section' => 'buttons' ),
			'slug_format'                       => array( 'label' => __( 'Clean links with words (/size-42/) (Pro)', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'pages' ),
			'sort_by_title'                     => array( 'label' => __( 'Sort results by name after filtering', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'products' ),
			'text_no_products'                  => array( 'label' => __( 'Message when nothing matches', 'woo-product-filter' ), 'type' => 'text', 'section' => 'products' ),
			'third_party_prefilter'             => array( 'label' => __( 'Keep filters other plugins already applied', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'behaviour' ),
			'use_category_filtration'           => array( 'label' => __( 'Narrow the category list to the current category', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'pages' ),
			'use_title_as_slug'                 => array( 'label' => __( 'Use the block titles in the clear buttons', 'woo-product-filter' ), 'type' => 'switch', 'section' => 'buttons' ),
			'frontend_engine'                   => array( 'label' => __( 'Storefront engine', 'woo-product-filter' ), 'help' => __( '4.0 is fast and phone first. Classic keeps the 3.x markup for custom CSS.', 'woo-product-filter' ), 'type' => 'select', 'options' => array( '4' => __( '4.0 (recommended)', 'woo-product-filter' ), 'legacy' => __( 'Classic 3.x', 'woo-product-filter' ) ), 'section' => 'behaviour' ),
		);
	}

	/**
	 * Plain labels for EVERY block (f_) key from 3.x and 4.0 (used by Advanced per block).
	 * blocks: the families the key applies to (empty = all).
	 */
	public static function blockKeys() {
		$lists   = array( 'wpfAttribute', 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand', 'wpfAuthor', 'wpfCustomField' );
		$taxes   = array( 'wpfAttribute', 'wpfCategory', 'wpfTags', 'wpfBrand', 'wpfPerfectBrand' );
		$sort    = array( 'default' => __( 'As they are', 'woo-product-filter' ), 'asc' => __( 'A to Z', 'woo-product-filter' ), 'desc' => __( 'Z to A', 'woo-product-filter' ) );
		$yesNo   = array( 'yes_open' => __( 'Yes, open', 'woo-product-filter' ), 'yes_close' => __( 'Yes, folded', 'woo-product-filter' ), 'no' => __( 'No', 'woo-product-filter' ) );
		return array(
			'f_enable'                          => array( 'label' => __( 'Block is on', 'woo-product-filter' ), 'type' => 'hidden' ),
			'f_name'                            => array( 'label' => __( 'Block type', 'woo-product-filter' ), 'type' => 'hidden' ),
			'f_wbw_style'                       => array( 'label' => __( 'Look (legacy key)', 'woo-product-filter' ), 'type' => 'hidden' ),
			'f_frontend_type'                   => array( 'label' => __( 'Look (legacy key)', 'woo-product-filter' ), 'type' => 'hidden' ),
			'f_preselect'                       => array( 'label' => __( 'Preselected range', 'woo-product-filter' ), 'type' => 'hidden' ),
			'f_range_by_hands_default'          => array( 'label' => __( 'Manual ranges (default)', 'woo-product-filter' ), 'type' => 'hidden' ),
			'f_range_by_hands_values'           => array( 'label' => __( 'Manual ranges (values)', 'woo-product-filter' ), 'type' => 'hidden' ),
			'f_description'                     => array( 'label' => __( 'A line under the title', 'woo-product-filter' ), 'type' => 'text' ),
			'f_custom_title'                    => array( 'label' => __( 'Custom title', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfFeatured' ) ),
			'f_add_text'                        => array( 'label' => __( 'Text after 1 to 4 stars', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfRating' ) ),
			'f_add_text5'                       => array( 'label' => __( 'Text after 5 stars', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfRating' ) ),
			'f_collapse_by_delete'              => array( 'label' => __( 'Fold everything when a pick is removed', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfCategory' ) ),
			'f_currency_position'               => array( 'label' => __( 'Currency symbol goes', 'woo-product-filter' ), 'type' => 'select', 'options' => array( 'before' => __( 'Before the number', 'woo-product-filter' ), 'after' => __( 'After the number', 'woo-product-filter' ) ), 'blocks' => array( 'wpfPrice' ) ),
			'f_currency_show_as'                => array( 'label' => __( 'Show the currency as', 'woo-product-filter' ), 'type' => 'select', 'options' => array( 'symbol' => __( 'Symbol ($)', 'woo-product-filter' ), 'code' => __( 'Code (USD)', 'woo-product-filter' ) ), 'blocks' => array( 'wpfPrice' ) ),
			'f_custom_tags'                     => array( 'label' => __( 'Group tags under headings', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfTags' ) ),
			'f_custom_tags_settings'            => array( 'label' => __( 'Tag group settings', 'woo-product-filter' ), 'type' => 'hidden' ),
			'f_multi_logic'                     => array( 'label' => __( 'A product must match (legacy key)', 'woo-product-filter' ), 'type' => 'select', 'options' => array( 'or' => __( 'Any picked choice', 'woo-product-filter' ), 'and' => __( 'All picked choices', 'woo-product-filter' ) ), 'blocks' => array( 'wpfCategory' ) ),
			'f_show_logos'                      => array( 'label' => __( 'Show brand logos instead of names', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfPerfectBrand', 'wpfBrand' ) ),
			'f_logo_layout'                     => array( 'label' => __( 'Logos run', 'woo-product-filter' ), 'type' => 'select', 'options' => array( 'ver' => __( 'Down, one per line', 'woo-product-filter' ), 'hor' => __( 'Across, side by side', 'woo-product-filter' ) ), 'blocks' => array( 'wpfPerfectBrand', 'wpfBrand' ) ),
			'f_custom_tags_settings[header]'    => array( 'label' => __( 'Heading tag', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfTags' ) ),
			'f_custom_tags_settings[title_1]'   => array( 'label' => __( 'First level heading', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfTags' ) ),
			'f_custom_tags_settings[title_2]'   => array( 'label' => __( 'Second level heading', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfTags' ) ),
			'f_custom_tags_settings[title_3]'   => array( 'label' => __( 'Third level heading', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfTags' ) ),
			'f_dropdown_first_option_text'      => array( 'label' => __( 'First line in the dropdown', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array_merge( $lists, array( 'wpfInStock', 'wpfRating', 'wpfPrice', 'wpfPriceRange' ) ) ),
			'f_dropdown_search'                 => array( 'label' => __( 'Add a search box to the dropdown', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfAttribute', 'wpfAuthor', 'wpfTags', 'wpfCategory' ) ),
			'f_dropdown_search_text'            => array( 'label' => __( 'The dropdown search box says', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfAttribute', 'wpfAuthor', 'wpfTags', 'wpfCategory' ) ),
			'f_exclude_terms'                   => array( 'label' => __( 'Choices to leave out (ids, comma separated)', 'woo-product-filter' ), 'type' => 'text', 'blocks' => $taxes ),
			'f_extend_parent_filtering'         => array( 'label' => __( 'Picking a parent also picks its children', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfCategory' ) ),
			'f_filtered_by_selected'            => array( 'label' => __( 'Only show products from the chosen categories', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfCategory', 'wpfPerfectBrand' ) ),
			'f_hidden_attributes'               => array( 'label' => __( 'Start with these choices picked (and hide them)', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfAttribute' ) ),
			'f_hidden_brands'                   => array( 'label' => __( 'Start with these brands picked (and hide them)', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfPerfectBrand' ) ),
			'f_hidden_categories'               => array( 'label' => __( 'Start with these categories picked (and hide them)', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfCategory' ) ),
			'f_hidden_tags'                     => array( 'label' => __( 'Start with these tags picked (and hide them)', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfTags' ) ),
			'f_hide_by_single'                  => array( 'label' => __( 'Hide this block when it has one choice', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfAttribute' ) ),
			'f_hide_empty_active'               => array( 'label' => __( 'Also hide empty choices while filtering', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => $taxes ),
			'f_hide_parent'                     => array( 'label' => __( 'Hide the parent choice', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => $taxes ),
			'f_hide_taxonomy'                   => array( 'label' => __( 'Hide sub-choices', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfCategory', 'wpfPerfectBrand' ) ),
			'f_layout'                          => array( 'label' => __( 'Choices run', 'woo-product-filter' ), 'type' => 'select', 'options' => array( 'ver' => __( 'Down, one per line', 'woo-product-filter' ), 'hor' => __( 'Across, side by side', 'woo-product-filter' ) ), 'blocks' => $lists ),
			'f_max_height'                      => array( 'label' => __( 'Tallest a block can be before it scrolls (px)', 'woo-product-filter' ), 'type' => 'number', 'blocks' => $lists ),
			'f_mlist'                           => array( 'label' => __( 'Only these choices (ids)', 'woo-product-filter' ), 'type' => 'text', 'blocks' => $taxes ),
			'f_mlist_with_children'             => array( 'label' => __( 'Include sub-choices of those', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfCategory', 'wpfPerfectBrand' ) ),
			'f_multi_logic_hierarchical'        => array( 'label' => __( 'A product counts when it is in', 'woo-product-filter' ), 'type' => 'select', 'options' => array( 'any' => __( 'The parent or a child', 'woo-product-filter' ), 'child' => __( 'A child only', 'woo-product-filter' ), 'parent' => __( 'The parent only', 'woo-product-filter' ) ), 'blocks' => array( 'wpfCategory' ) ),
			'f_option_labels'                   => array( 'label' => __( 'Custom names for the choices', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfSortBy', 'wpfCustomField' ) ),
			'f_order_custom'                    => array( 'label' => __( 'Use the order set in WooCommerce', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => $taxes ),
			'f_price_tooltip_show_as'           => array( 'label' => __( 'Show the price as a label instead of a box', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfPrice' ) ),
			'f_query_logic'                     => array( 'label' => __( 'A product must match', 'woo-product-filter' ), 'type' => 'select', 'options' => array( 'or' => __( 'Any picked choice', 'woo-product-filter' ), 'and' => __( 'All picked choices', 'woo-product-filter' ) ), 'blocks' => array( 'wpfAttribute', 'wpfTags' ) ),
			'f_range_automatic'                 => array( 'label' => __( 'Make price ranges automatically', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfPriceRange' ) ),
			'f_range_by_hands'                  => array( 'label' => __( 'Set price ranges by hand', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfPriceRange' ) ),
			'f_search_label'                    => array( 'label' => __( 'The search box says', 'woo-product-filter' ), 'type' => 'text', 'blocks' => $lists ),
			'f_show_all_attributes'             => array( 'label' => __( 'Always show every choice', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfAttribute' ) ),
			'f_show_all_brands'                 => array( 'label' => __( 'Always show every brand', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfPerfectBrand' ) ),
			'f_show_all_categories'             => array( 'label' => __( 'Always show every category', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfCategory' ) ),
			'f_show_all_tags'                   => array( 'label' => __( 'Always show every tag', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfTags' ) ),
			'f_show_count_parent_with_children' => array( 'label' => __( 'Parent counts include its children', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfCategory' ) ),
			'f_show_hierarchical'               => array( 'label' => __( 'Show as a tree', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfAttribute', 'wpfCategory', 'wpfPerfectBrand' ) ),
			'f_show_inputs'                     => array( 'label' => __( 'Let shoppers type a price', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfPrice' ) ),
			'f_show_inputs_enabled_currency'    => array( 'label' => __( 'Show the currency in the price boxes', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfPrice' ) ),
			'f_show_inputs_enabled_tooltip'     => array( 'label' => __( 'Show the price above the slider handles', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfPrice' ) ),
			'f_show_search_input'               => array( 'label' => __( 'Add a search box', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => $lists ),
			'f_skin_type'                       => array( 'label' => __( 'Slider style', 'woo-product-filter' ), 'type' => 'select', 'options' => array( 'default' => __( 'Default', 'woo-product-filter' ), 'flat' => __( 'Flat (Pro)', 'woo-product-filter' ), 'big' => __( 'Big (Pro)', 'woo-product-filter' ), 'modern' => __( 'Modern (Pro)', 'woo-product-filter' ), 'sharp' => __( 'Sharp (Pro)', 'woo-product-filter' ), 'round' => __( 'Round (Pro)', 'woo-product-filter' ), 'square' => __( 'Square (Pro)', 'woo-product-filter' ), 'compact' => __( 'Compact (Pro)', 'woo-product-filter' ), 'circle' => __( 'Circle (Pro)', 'woo-product-filter' ), 'rail' => __( 'Rail (Pro)', 'woo-product-filter' ), 'trolley' => __( 'Trolley (Pro)', 'woo-product-filter' ) ), 'blocks' => array( 'wpfPrice' ) ),
			'f_sort_by'                         => array( 'label' => __( 'Sort the choices', 'woo-product-filter' ), 'type' => 'select', 'options' => $sort, 'blocks' => $taxes ),
			'f_status_names'                    => array( 'label' => __( 'Use my own stock words', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfInStock' ) ),
			'f_step'                            => array( 'label' => __( 'Price step', 'woo-product-filter' ), 'type' => 'number', 'blocks' => array( 'wpfPriceRange', 'wpfPrice' ) ),
			'f_stock_statuses[in]'              => array( 'label' => __( '"In stock" says', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfInStock' ) ),
			'f_stock_statuses[out]'             => array( 'label' => __( '"Out of stock" says', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfInStock' ) ),
			'f_stock_statuses[on]'              => array( 'label' => __( '"On backorder" says', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfInStock' ) ),
			'f_unfolding_by_search'             => array( 'label' => __( 'Unfold the tree when shoppers search', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array( 'wpfCategory' ) ),
			'f_ver_columns'                     => array( 'label' => __( 'Columns of choices', 'woo-product-filter' ), 'type' => 'number', 'blocks' => $lists ),
			'f_enable_title'                    => array( 'label' => __( 'Show the block title on desktop', 'woo-product-filter' ), 'type' => 'select', 'options' => $yesNo ),
			'f_enable_title_mobile'             => array( 'label' => __( 'Show the block title on phones', 'woo-product-filter' ), 'type' => 'select', 'options' => $yesNo ),
			'f_title'                           => array( 'label' => __( 'Title shoppers see', 'woo-product-filter' ), 'type' => 'text' ),
			'f_list'                            => array( 'label' => __( 'Which attribute', 'woo-product-filter' ), 'type' => 'hidden' ),
			'f_show_count'                      => array( 'label' => __( 'Show how many products each choice has', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array_merge( $lists, array( 'wpfInStock', 'wpfRating', 'wpfPrice', 'wpfPriceRange' ) ) ),
			'f_hide_empty'                      => array( 'label' => __( 'Hide choices with no products', 'woo-product-filter' ), 'type' => 'switch', 'blocks' => array_merge( $lists, array( 'wpfInStock', 'wpfRating', 'wpfPrice', 'wpfPriceRange' ) ) ),
			'f_checkbox_label'                  => array( 'label' => __( 'Label next to the switch', 'woo-product-filter' ), 'type' => 'text', 'blocks' => array( 'wpfOnSale', 'wpfFeatured' ) ),
			'f_options[]'                       => array( 'label' => __( 'Choices offered', 'woo-product-filter' ), 'type' => 'hidden' ),
			'f_customfield_'                    => array( 'label' => __( 'Which custom field', 'woo-product-filter' ), 'type' => 'hidden' ),
			'f_sortby_'                         => array( 'label' => __( 'Sort choice', 'woo-product-filter' ), 'type' => 'hidden' ),
			'f_stock_'                          => array( 'label' => __( 'Stock choice', 'woo-product-filter' ), 'type' => 'hidden' ),
			'display_type'                      => array( 'label' => __( 'Look', 'woo-product-filter' ), 'type' => 'hidden' ),
			'logic'                             => array( 'label' => __( 'Pick logic', 'woo-product-filter' ), 'type' => 'hidden' ),
			'mobile_display_type'               => array( 'label' => __( 'Look on phones', 'woo-product-filter' ), 'type' => 'hidden' ),
		);
	}

	/**
	 * Section headings inside Advanced for the whole filter.
	 */
	public static function sections() {
		return array(
			'behaviour' => __( 'How filtering works', 'woo-product-filter' ),
			'products'  => __( 'Products', 'woo-product-filter' ),
			'pages'     => __( 'Pages', 'woo-product-filter' ),
			'look'      => __( 'Look', 'woo-product-filter' ),
			'phone'     => __( 'Phones', 'woo-product-filter' ),
			'buttons'   => __( 'Buttons and words', 'woo-product-filter' ),
			'loading'   => __( 'Loading', 'woo-product-filter' ),
			'other'     => __( 'Other', 'woo-product-filter' ),
		);
	}

	/**
	 * Every main key the builder knows (plain words controls, Advanced map, engine defaults).
	 */
	public static function allMainKeys() {
		$keys = array_keys( self::mainKeys() );
		foreach ( self::controls() as $c ) {
			if ( 'main' === $c['scope'] ) {
				$keys[] = $c['key'];
			}
		}
		$keys[] = 'filters[order]';
		$keys[] = 'filters[preselect]';
		return array_values( array_unique( $keys ) );
	}

	/**
	 * Every block key the builder knows.
	 */
	public static function allBlockKeys() {
		$keys = array_keys( self::blockKeys() );
		foreach ( self::controls() as $c ) {
			if ( 'block' === $c['scope'] ) {
				$keys[] = $c['key'];
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * The complete schema for the builder JS, after the Pro filter.
	 */
	public static function build( $settings = array() ) {
		$engineDefaults = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getDefaultSettings();
		$main           = self::mainKeys();
		foreach ( (array) $engineDefaults as $key => $def ) {
			if ( ! isset( $main[ $key ] ) && ! self::controlFor( $key ) && 'filters' !== $key ) {
				$main[ $key ] = array(
					'label'   => isset( WooBeWoo_PF_Frame::_()->getModule( 'templates' )->loadPlainLabels()[ $key ] ) ? WooBeWoo_PF_Frame::_()->getModule( 'templates' )->loadPlainLabels()[ $key ] : ucfirst( str_replace( '_', ' ', $key ) ),
					'type'    => is_array( $def ) ? 'hidden' : ( in_array( (string) $def, array( '0', '1', '' ), true ) ? 'switch' : 'text' ),
					'section' => 'other',
					'default' => is_array( $def ) ? '' : (string) $def,
				);
			}
		}
		$controls = self::controls();
		$front    = class_exists( 'WooBeWoo_PF_Frontend4' ) && method_exists( 'WooBeWoo_PF_Frontend4', 'schema' ) ? WooBeWoo_PF_Frontend4::schema() : array();
		foreach ( $controls as $i => $c ) {
			// The storefront engine's defaults (settings-schema.php) win, so the builder shows what the shop does.
			$d = self::frontDefault( $front, $c );
			if ( null !== $d ) {
				$controls[ $i ]['default'] = $d;
			}
		}
		// Same for the Advanced rows: a switch that is on in the shop must not read "off" in the builder.
		foreach ( $main as $key => $def ) {
			$d = self::frontDefault( $front, array_merge( $def, array( 'key' => $key ) ) );
			if ( null !== $d ) {
				$main[ $key ]['default'] = $d;
			}
		}
		$blockKeys = self::blockKeys();
		foreach ( $blockKeys as $key => $def ) {
			$d = self::frontDefault( $front, array_merge( $def, array( 'key' => $key ) ) );
			if ( null !== $d ) {
				$blockKeys[ $key ]['default'] = $d;
			}
		}
		$schema = array(
			'controls'      => $controls,
			'mainKeys'      => $main,
			'blockKeys'     => $blockKeys,
			'sections'      => self::sections(),
			'displayTypes'  => self::displayTypes(),
			'displayFor'    => array(),
			'blockCards'    => self::blockCards(),
			'defaults'      => $engineDefaults,
			// Settings the builder writes when a registered block is added (WooBeWoo_PF_Blocks 'defaults').
			'blockDefaults' => class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::defaults() : array(),
		);
		foreach ( array_keys( self::blockCards() ) as $b ) {
			$schema['displayFor'][ $b ] = self::displayTypesFor( $b );
		}
		$schema['displayFor']['wpfPriceRange']   = self::displayTypesFor( 'wpfPriceRange' );
		$schema['displayFor']['wpfPerfectBrand'] = self::displayTypesFor( 'wpfPerfectBrand' );
		return self::flagEngines( apply_filters( 'woobewoo_pf_admin_schema', $schema, $settings ) );
	}

	/**
	 * Settings the 4.0 storefront reads besides settings-schema.php: page rules, placement, size, custom code (Pro),
	 * plain words bar (Pro), search and custom field blocks (Pro), stock words.
	 */
	public static function engine4Keys() {
		return array(
			'main'  => array( 'frontend_engine', 'wbw_placement', 'display_on_page', 'display_for', 'display_page_list', 'display_cat_list', 'display_child_cat', 'display_pwb_list', 'display_on_page_shortcode', 'hide_without_products', 'filter_width', 'filter_width_in', 'css_editor', 'js_editor', 'plainwords_enable', 'plainwords_placeholder', 'finder', 'mobile_overrides', 'text_no_products' ),
			'block' => array( 'f_enable_title_mobile', 'f_title', 'f_stock_statuses[in]', 'f_stock_statuses[out]', 'f_stock_statuses[on]', 'f_search_label', 'f_colors_terms', 'f_custom_meta_field', 'f_meta_key', 'f_search_logic', 'f_range_display', 'f_range_step', 'f_search_by_title', 'f_search_by_content', 'f_search_by_excerpt', 'f_search_by_meta_sku', 'f_search_by_tax_categories', 'f_search_by_tax_tags', 'f_search_by_perfect_brand', 'f_search_by_attributes', 'f_search_by_attributes_list', 'f_search_by_meta_fields', 'f_search_by_meta_fields_list', 'f_meta_match', 'f_query_logic', 'f_search_only_by_full_word', 'f_frontend_type', 'mobile_display_type', 'f_customfield_', 'f_mlist', 'f_exclude_terms' ),
		);
	}

	/**
	 * Marks every builder control and Advanced row the 4.0 storefront never reads `classicOnly` (the builder dims it
	 * while the filter runs 4.0 and says so) and keeps `engine4Only` on the 4.0 ones (dimmed on a classic filter).
	 * A setting either engine reads stays as it is.
	 *
	 * @since 4.0.0
	 */
	public static function flagEngines( $schema ) {
		$front = class_exists( 'WooBeWoo_PF_Frontend4' ) && method_exists( 'WooBeWoo_PF_Frontend4', 'schema' ) ? WooBeWoo_PF_Frontend4::schema() : array();
		if ( ! is_array( $front ) || ! $front ) {
			return $schema;
		}
		$extra = self::engine4Keys();
		$main  = array_merge( array_keys( $front ), $extra['main'] );
		$block = array_merge( array_keys( $front ), $extra['block'] );
		$reads = function ( $key, $list ) {
			$root = preg_replace( '/\[.*$/', '', (string) $key );
			return in_array( $key, $list, true ) || in_array( $root, $list, true );
		};
		foreach ( $schema['controls'] as $i => $c ) {
			if ( empty( $c['key'] ) || ! empty( $c['engine4Only'] ) || in_array( $c['type'], array( 'display_type', 'mobile_display_type', 'finder_steps' ), true ) ) {
				continue;
			}
			if ( ! $reads( $c['key'], 'block' === $c['scope'] ? $block : $main ) ) {
				$schema['controls'][ $i ]['classicOnly'] = true;
			}
		}
		foreach ( array( 'mainKeys' => $main, 'blockKeys' => $block ) as $k => $list ) {
			if ( empty( $schema[ $k ] ) || ! is_array( $schema[ $k ] ) ) {
				continue;
			}
			foreach ( $schema[ $k ] as $key => $def ) {
				if ( ! $reads( $key, $list ) ) {
					$schema[ $k ][ $key ]['classicOnly'] = true;
				}
			}
		}
		$schema['engine4BlockKeys'] = array_values( array_unique( $block ) );
		return $schema;
	}

	/**
	 * The storefront default of a control, written the way the control stores it (switch: its on / off value), or null.
	 *
	 * @since 4.0.0
	 */
	protected static function frontDefault( $front, $c ) {
		if ( ! is_array( $front ) || empty( $c['key'] ) || ! isset( $front[ $c['key'] ] ) || ! array_key_exists( 'default', $front[ $c['key'] ] ) ) {
			return null;
		}
		$d    = $front[ $c['key'] ]['default'];
		$type = isset( $c['type'] ) ? $c['type'] : '';
		if ( is_array( $d ) || is_null( $d ) ) {
			return null;
		}
		if ( 'switch' === $type ) {
			if ( ! is_bool( $d ) && ! in_array( (string) $d, array( '0', '1', '' ), true ) ) {
				return null;
			}
			$on  = isset( $c['on'] ) ? (string) $c['on'] : '1';
			$off = isset( $c['off'] ) ? (string) $c['off'] : '';
			return $d ? $on : $off;
		}
		if ( in_array( $type, array( 'number', 'text', 'select', 'pictures' ), true ) ) {
			if ( is_bool( $d ) ) {
				return null;
			}
			if ( ( 'select' === $type || 'pictures' === $type ) && ( empty( $c['options'] ) || ! is_array( $c['options'] ) || ! array_key_exists( (string) $d, $c['options'] ) ) ) {
				return null;
			}
			return (string) $d;
		}
		return null;
	}

	protected static function controlFor( $key ) {
		foreach ( self::controls() as $c ) {
			if ( 'main' === $c['scope'] && $c['key'] === $key ) {
				return $c;
			}
		}
		return null;
	}
}
