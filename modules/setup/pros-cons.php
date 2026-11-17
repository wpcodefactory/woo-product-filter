<?php
/**
 * Product Filter by WBW - Pros and cons library (4.0 smart setup)
 *
 * For each filter kind: one plain "why", one "watch out" and the effect on speed.
 * Lines follow the benchmark report, section 08. The wizard fills "{missing}" with the
 * number of products that have no value for the filter.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'price'          => array(
		'name'      => __( 'Price', 'woo-product-filter' ),
		'why'       => __( 'Almost every shopper uses it. Baymard lists it as essential.', 'woo-product-filter' ),
		'watch_out' => __( 'Needs sane steps; a 0 to 10,000 slider with 1 unit steps is useless. We set the steps from your real prices.', 'woo-product-filter' ),
		'speed'     => __( 'Low with the index', 'woo-product-filter' ),
	),
	'size'           => array(
		'name'      => __( 'Size', 'woo-product-filter' ),
		'why'       => __( 'Top filter for fashion and shoes; cuts zero result frustration.', 'woo-product-filter' ),
		'watch_out' => __( 'Hide out of stock sizes or shoppers click dead ends. We hide choices with no products.', 'woo-product-filter' ),
		'speed'     => __( 'Low with the variation index', 'woo-product-filter' ),
	),
	'colour'         => array(
		'name'      => __( 'Colour', 'woo-product-filter' ),
		'why'       => __( 'Swatches are faster than reading words; shoppers pick more.', 'woo-product-filter' ),
		'watch_out' => __( '{missing} of your products have no colour. They disappear when a colour is picked. Fix: tag them or keep colour off.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
	'brand'          => array(
		'name'      => __( 'Brand', 'woo-product-filter' ),
		'why'       => __( 'Essential for electronics, beauty and sports.', 'woo-product-filter' ),
		'watch_out' => __( 'Only useful when most products have a brand. {missing} of yours have none.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
	'rating'         => array(
		'name'      => __( 'Rating', 'woo-product-filter' ),
		'why'       => __( 'Builds trust.', 'woo-product-filter' ),
		'watch_out' => __( 'Stores with few reviews get empty results. {missing} of your products have no review yet.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
	'category'       => array(
		'name'      => __( 'Categories', 'woo-product-filter' ),
		'why'       => __( 'Good for big catalogues.', 'woo-product-filter' ),
		'watch_out' => __( 'Duplicates the menu on small stores; confusing on category pages.', 'woo-product-filter' ),
		'speed'     => __( 'Medium (hierarchy counts)', 'woo-product-filter' ),
	),
	'search'         => array(
		'name'      => __( 'Text search inside the filter', 'woo-product-filter' ),
		'why'       => __( 'Helps on 1,000+ products.', 'woo-product-filter' ),
		'watch_out' => __( 'Competes with the site search; keep one.', 'woo-product-filter' ),
		'speed'     => __( 'Medium', 'woo-product-filter' ),
	),
	'author'         => array(
		'name'      => __( 'Author or vendor', 'woo-product-filter' ),
		'why'       => __( 'Marketplaces and book stores only.', 'woo-product-filter' ),
		'watch_out' => __( 'Meaningless on single seller stores.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
	'sale'           => array(
		'name'      => __( 'On sale', 'woo-product-filter' ),
		'why'       => __( 'Bargain hunters look for it first.', 'woo-product-filter' ),
		'watch_out' => __( 'Shows an empty list when nothing is on sale. Right now {onsale} of your products are on sale.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
	'stock'          => array(
		'name'      => __( 'In stock', 'woo-product-filter' ),
		'why'       => __( 'Stops shoppers landing on sold out items.', 'woo-product-filter' ),
		'watch_out' => __( 'Pointless when everything is in stock. {outofstock} of your products are out of stock now.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
	'tags'           => array(
		'name'      => __( 'Tags', 'woo-product-filter' ),
		'why'       => __( 'Cheap way to add themes like "gift" or "new".', 'woo-product-filter' ),
		'watch_out' => __( 'Messy tag lists confuse shoppers. {missing} of your products have no tag.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
	'material'       => array(
		'name'      => __( 'Material', 'woo-product-filter' ),
		'why'       => __( 'Shoppers of furniture and jewellery pick by material.', 'woo-product-filter' ),
		'watch_out' => __( '{missing} of your products have no material set.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
	'year'           => array(
		'name'      => __( 'Year', 'woo-product-filter' ),
		'why'       => __( 'Parts shoppers start with the vehicle year.', 'woo-product-filter' ),
		'watch_out' => __( 'Works only when every part has a year. {missing} of yours have none.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
	'make'           => array(
		'name'      => __( 'Make', 'woo-product-filter' ),
		'why'       => __( 'Second step of year, make, model.', 'woo-product-filter' ),
		'watch_out' => __( '{missing} of your products have no make.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
	'model'          => array(
		'name'      => __( 'Model', 'woo-product-filter' ),
		'why'       => __( 'Third step of year, make, model.', 'woo-product-filter' ),
		'watch_out' => __( '{missing} of your products have no model.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
	'age'            => array(
		'name'      => __( 'Age', 'woo-product-filter' ),
		'why'       => __( 'Parents shop toys by age first.', 'woo-product-filter' ),
		'watch_out' => __( '{missing} of your products have no age range.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
	'numeric_fields' => array(
		'name'      => __( 'Numeric fields', 'woo-product-filter' ),
		'why'       => __( 'B2B buyers filter by exact specs like length or voltage.', 'woo-product-filter' ),
		'watch_out' => __( 'Needs the Pro custom field ranges. Free can filter ACF choice fields only.', 'woo-product-filter' ),
		'speed'     => __( 'Medium', 'woo-product-filter' ),
	),
	'attribute'      => array(
		'name'      => __( 'Attribute', 'woo-product-filter' ),
		'why'       => __( 'Most of your products have this, so shoppers can narrow down fast.', 'woo-product-filter' ),
		'watch_out' => __( '{missing} of your products have no value here. They disappear when a choice is picked.', 'woo-product-filter' ),
		'speed'     => __( 'Low', 'woo-product-filter' ),
	),
);
