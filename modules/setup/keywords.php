<?php
/**
 * Product Filter by WBW - Store type keywords (4.0 smart setup)
 *
 * Rule based store type detection. Each store type lists lowercase keywords that are
 * searched in category names (weight 3), attribute names (weight 2) and a sample of
 * product titles (weight 1). The type with the highest score wins; confidence is the
 * winner's share of the total score.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'fashion'        => array( 'dress', 'dresses', 'shirt', 'shirts', 't-shirt', 'tee', 'jeans', 'jacket', 'coat', 'skirt', 'hoodie', 'sweater', 'blouse', 'trousers', 'pants', 'clothing', 'apparel', 'fashion', 'women', 'men', 'kids', 'lingerie', 'underwear', 'socks', 'scarf', 'hat', 'cap', 'size', 'fit', 'sleeve', 'neckline', 'fabric' ),
	'shoes'          => array( 'shoe', 'shoes', 'sneaker', 'sneakers', 'boot', 'boots', 'sandal', 'sandals', 'heel', 'heels', 'trainer', 'trainers', 'loafer', 'slipper', 'footwear', 'sole', 'running shoes', 'eu size', 'uk size', 'us size' ),
	'electronics'    => array( 'laptop', 'phone', 'smartphone', 'tablet', 'camera', 'headphone', 'headphones', 'speaker', 'tv', 'television', 'monitor', 'computer', 'pc', 'gaming', 'console', 'charger', 'cable', 'usb', 'bluetooth', 'wireless', 'electronics', 'gadget', 'drone', 'smartwatch', 'ram', 'storage', 'screen size', 'resolution', 'processor', 'battery', 'watt', 'hdmi' ),
	'auto_parts'     => array( 'brake', 'brakes', 'tyre', 'tyres', 'tire', 'tires', 'wheel', 'engine', 'oil filter', 'spark plug', 'bumper', 'headlight', 'car', 'auto', 'automotive', 'vehicle', 'motorcycle', 'exhaust', 'suspension', 'clutch', 'alternator', 'radiator', 'year', 'make', 'model', 'oem', 'part number' ),
	'home_furniture' => array( 'sofa', 'chair', 'table', 'bed', 'mattress', 'furniture', 'lamp', 'lighting', 'rug', 'carpet', 'curtain', 'cushion', 'pillow', 'kitchen', 'bathroom', 'decor', 'home', 'garden', 'shelf', 'wardrobe', 'desk', 'cabinet', 'material', 'wood', 'oak', 'fabric', 'dimensions' ),
	'beauty'         => array( 'skincare', 'skin care', 'makeup', 'make-up', 'lipstick', 'mascara', 'foundation', 'serum', 'moisturizer', 'moisturiser', 'shampoo', 'conditioner', 'perfume', 'fragrance', 'cosmetic', 'cosmetics', 'beauty', 'nail', 'hair care', 'cream', 'lotion', 'spf', 'skin type', 'shade' ),
	'food_grocery'   => array( 'food', 'grocery', 'groceries', 'snack', 'snacks', 'coffee', 'tea', 'chocolate', 'organic', 'vegan', 'gluten free', 'gluten-free', 'drink', 'drinks', 'beverage', 'wine', 'beer', 'spice', 'spices', 'sauce', 'cheese', 'meat', 'fruit', 'vegetable', 'bakery', 'fresh', 'frozen', 'weight', 'flavour', 'flavor', 'allergen' ),
	'books_media'    => array( 'book', 'books', 'novel', 'paperback', 'hardcover', 'ebook', 'e-book', 'audiobook', 'author', 'publisher', 'isbn', 'dvd', 'blu-ray', 'vinyl', 'cd', 'magazine', 'comic', 'manga', 'fiction', 'non-fiction', 'genre', 'language', 'pages' ),
	'sports'         => array( 'sport', 'sports', 'fitness', 'gym', 'yoga', 'bike', 'bicycle', 'cycling', 'running', 'football', 'soccer', 'basketball', 'tennis', 'golf', 'swim', 'swimming', 'hiking', 'camping', 'outdoor', 'ski', 'snowboard', 'dumbbell', 'protein', 'racket', 'ball', 'jersey' ),
	'toys'           => array( 'toy', 'toys', 'lego', 'puzzle', 'doll', 'dolls', 'plush', 'board game', 'game', 'games', 'kids', 'baby', 'toddler', 'age', 'ages', 'educational', 'playset', 'figure', 'figures', 'ride-on', 'building blocks' ),
	'jewellery'      => array( 'jewellery', 'jewelry', 'ring', 'rings', 'necklace', 'bracelet', 'earring', 'earrings', 'pendant', 'gold', 'silver', 'diamond', 'gemstone', 'pearl', 'watch', 'watches', 'carat', 'karat', 'sterling', 'metal', 'stone', 'chain' ),
	'industrial_b2b' => array( 'industrial', 'b2b', 'wholesale', 'bulk', 'tool', 'tools', 'bearing', 'bearings', 'valve', 'valves', 'pump', 'pumps', 'bolt', 'bolts', 'screw', 'screws', 'fastener', 'fasteners', 'cable', 'wire', 'motor', 'hydraulic', 'pneumatic', 'safety', 'ppe', 'welding', 'voltage', 'diameter', 'thread', 'grade', 'sku', 'pack of', 'length', 'thickness' ),
	'digital_goods'  => array( 'download', 'downloads', 'digital', 'software', 'license', 'licence', 'plugin', 'theme', 'template', 'templates', 'font', 'fonts', 'preset', 'presets', 'course', 'courses', 'ebook', 'pdf', 'audio', 'sample pack', 'stock photo', 'wordpress', 'subscription' ),
);
