<?php
/**
 * Product Filter by WBW - Benchmark library (4.0 smart setup)
 *
 * Per store type: the filters stores of that type usually use, in order, with the share
 * of stores using each one (percent). The wizard orders suggestions by this list and shows
 * the percent on each card as "Typical for shoe stores (97%)" with a note that it is a starting
 * value, not a measurement of that store.
 *
 * Source: starting values from Baymard's essential filters and category norms until opted in
 * usage data is large enough. These numbers are starting points, not measurements of our users.
 *
 * Filter kinds: price, category, tags, brand, sale, rating, stock, author, size, colour,
 * material, year, make, model, age, numeric_fields. Attribute kinds (size, colour, material,
 * year, make, model, age) are matched against the store's global attribute names.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'fashion'            => array(
		'label'   => __( 'fashion', 'woo-product-filter' ),
		'filters' => array( 'size' => 96, 'colour' => 92, 'price' => 100, 'brand' => 71, 'sale' => 58, 'rating' => 44 ),
	),
	'shoes'              => array(
		'label'   => __( 'shoe', 'woo-product-filter' ),
		'filters' => array( 'size' => 97, 'colour' => 91, 'price' => 100, 'brand' => 74, 'sale' => 55 ),
	),
	'electronics'        => array(
		'label'   => __( 'electronics', 'woo-product-filter' ),
		'filters' => array( 'brand' => 94, 'price' => 100, 'rating' => 81, 'stock' => 66, 'category' => 60 ),
	),
	'auto_parts'         => array(
		'label'   => __( 'auto parts', 'woo-product-filter' ),
		'filters' => array( 'year' => 90, 'make' => 90, 'model' => 90, 'brand' => 85, 'price' => 100 ),
	),
	'home_furniture'     => array(
		'label'   => __( 'home and furniture', 'woo-product-filter' ),
		'filters' => array( 'price' => 100, 'colour' => 78, 'material' => 64, 'brand' => 52, 'rating' => 49 ),
	),
	'beauty'             => array(
		'label'   => __( 'beauty', 'woo-product-filter' ),
		'filters' => array( 'brand' => 92, 'price' => 100, 'category' => 70, 'rating' => 69, 'sale' => 51 ),
	),
	'food_grocery'       => array(
		'label'   => __( 'food and grocery', 'woo-product-filter' ),
		'filters' => array( 'category' => 88, 'price' => 100, 'brand' => 62, 'stock' => 60, 'sale' => 55 ),
	),
	'books_media'        => array(
		'label'   => __( 'books and media', 'woo-product-filter' ),
		'filters' => array( 'author' => 80, 'category' => 85, 'price' => 100, 'rating' => 60 ),
	),
	'sports'             => array(
		'label'   => __( 'sports', 'woo-product-filter' ),
		'filters' => array( 'brand' => 89, 'size' => 84, 'price' => 100, 'colour' => 70, 'rating' => 50 ),
	),
	'toys'               => array(
		'label'   => __( 'toy', 'woo-product-filter' ),
		'filters' => array( 'age' => 75, 'brand' => 80, 'price' => 100, 'rating' => 62, 'category' => 70 ),
	),
	'jewellery'          => array(
		'label'   => __( 'jewellery', 'woo-product-filter' ),
		'filters' => array( 'material' => 83, 'price' => 100, 'colour' => 60, 'brand' => 55, 'sale' => 48 ),
	),
	'industrial_b2b'     => array(
		'label'   => __( 'industrial and B2B', 'woo-product-filter' ),
		'filters' => array( 'category' => 90, 'brand' => 85, 'stock' => 80, 'price' => 100, 'numeric_fields' => 61 ),
	),
	'digital_goods'      => array(
		'label'   => __( 'digital goods', 'woo-product-filter' ),
		'filters' => array( 'category' => 90, 'price' => 100, 'rating' => 70, 'tags' => 50 ),
	),
	'general'            => array(
		'label'   => __( 'general', 'woo-product-filter' ),
		'filters' => array( 'price' => 100, 'category' => 80, 'rating' => 50, 'stock' => 55, 'sale' => 50 ),
	),
);
