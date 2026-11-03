<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Brands Class
 *
 * WooCommerce Brands (product_brand) block in Free: unlocks the block, renders it in list,
 * dropdown and checkbox displays with optional logos (term thumbnail or the engine's per term
 * image meta `wpf_term_image`), and answers brand archive pages. Perfect Brands keeps working
 * through the existing wpfPerfectBrand block.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Brands extends WooBeWoo_PF_Module {

	const TERM_IMAGE_META = 'wpf_term_image';

	public function init() {
		parent::init();
		WooBeWoo_PF_Dispatcher::addFilter( 'addFilterTypes', array( $this, 'unlockBrandBlock' ), 5 );
		add_filter( 'woobewoo_pf_free_blocks', array( $this, 'freeBlocks' ) );
		WooBeWoo_PF_Dispatcher::addFilter( 'woobewoo_pf_get_pro_view', array( $this, 'brandView' ), 5 );
		WooBeWoo_PF_Dispatcher::addFilter( 'woobewoo_pf_add_product_brand_query', array( $this, 'brandQuery' ), 5, 2 );
		add_filter( 'woobewoo_pf_term_image', array( $this, 'termImage' ), 10, 3 );
	}

	/**
	 * The product_brand block is a Free block since 4.0.
	 */
	public function unlockBrandBlock( $filters ) {
		if ( taxonomy_exists( 'product_brand' ) && isset( $filters['wpfBrand'] ) ) {
			$filters['wpfBrand']['enabled'] = true;
			$filters['wpfBrand']['free']    = true;
		}
		return $filters;
	}

	/**
	 * Block ids available without Pro (used by the builder picker and the wizard).
	 */
	public function freeBlocks( $blocks ) {
		$blocks = (array) $blocks;
		if ( taxonomy_exists( 'product_brand' ) && ! in_array( 'wpfBrand', $blocks, true ) ) {
			$blocks[] = 'wpfBrand';
		}
		return $blocks;
	}

	/**
	 * Supply a view that knows generateBrandFilterHtml() when Pro is not around.
	 */
	public function brandView( $view ) {
		if ( $view ) {
			return $view; // Pro (or another module) already answers.
		}
		return $this->getView();
	}

	/**
	 * Brand archive pages: tell the filter query which brand term is current.
	 */
	public function brandQuery( $querySettings, $value ) {
		if ( ! isset( $querySettings['product_brand'] ) ) {
			$querySettings['product_brand'] = $value;
		}
		return $querySettings;
	}

	/**
	 * Logo URL for a term: the engine's per term image meta first, then the WooCommerce
	 * term thumbnail (thumbnail_id), then the Perfect Brands image.
	 *
	 * @param string $url     Current URL (empty).
	 * @param int    $termId  Term id.
	 * @param string $size    Image size.
	 */
	public function termImage( $url, $termId, $size = 'thumbnail' ) {
		if ( $url ) {
			return $url;
		}
		$meta = get_term_meta( (int) $termId, self::TERM_IMAGE_META, true );
		if ( $meta ) {
			if ( is_numeric( $meta ) ) {
				$src = wp_get_attachment_image_url( (int) $meta, $size );
				if ( $src ) {
					return $src;
				}
			} elseif ( is_string( $meta ) && 0 === strpos( $meta, 'http' ) ) {
				return $meta;
			}
		}
		foreach ( array( 'thumbnail_id', 'pwb_brand_image' ) as $key ) {
			$id = (int) get_term_meta( (int) $termId, $key, true );
			if ( $id ) {
				$src = wp_get_attachment_image_url( $id, $size );
				if ( $src ) {
					return $src;
				}
			}
		}
		return '';
	}

	/**
	 * Static helper for templates and other modules.
	 */
	public static function logoUrl( $termId, $size = 'thumbnail' ) {
		return (string) apply_filters( 'woobewoo_pf_term_image', '', (int) $termId, $size );
	}
}
