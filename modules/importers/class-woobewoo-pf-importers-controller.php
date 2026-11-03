<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Importers_Controller Class
 *
 * AJAX (nonce + manage_options through verifyRequest()): detect rivals, build a side by side
 * preview, import a set as a draft, and a small HTML page that renders the rival's own shortcode
 * for the preview iframe.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Importers_Controller extends WooBeWoo_PF_Controller {

	protected $_code = 'importers';

	public function woobewoo_pf_importers_detect() {
		WooBeWoo_PF_Req::verifyRequest();
		$res = new WooBeWoo_PF_Response();
		$res->addData( 'rivals', $this->getModule()->detect() );
		return $res->ajaxExec();
	}

	private function mapperOrFail( &$res ) {
		$slug   = sanitize_key( (string) WooBeWoo_PF_Req::getVar( 'rival', 'post' ) );
		$mapper = $this->getModule()->mapper( $slug );
		if ( ! $mapper ) {
			$res->pushError( esc_html__( 'Unknown filter plugin.', 'woo-product-filter' ) );
			return null;
		}
		return $mapper;
	}

	/**
	 * The set mapped with the owner's choices from the review step (posted as JSON in "choices").
	 */
	private function mappedWithChoices( $mapper, $setId ) {
		$mapped = $mapper->map( $setId );
		if ( is_wp_error( $mapped ) ) {
			return $mapped;
		}
		$raw = (string) WooBeWoo_PF_Req::getVar( 'choices', 'post' );
		$ov  = '' !== $raw ? json_decode( wp_unslash( $raw ), true ) : null;
		return $mapper->applyOverrides( $mapped, $ov );
	}

	/**
	 * Review step: what we would create, block by block, with the choices the owner can change.
	 */
	public function woobewoo_pf_importers_review() {
		WooBeWoo_PF_Req::verifyRequest();
		$res    = new WooBeWoo_PF_Response();
		$mapper = $this->mapperOrFail( $res );
		if ( ! $mapper ) {
			return $res->ajaxExec();
		}
		$setId  = sanitize_text_field( (string) WooBeWoo_PF_Req::getVar( 'set', 'post' ) );
		$mapped = $mapper->map( $setId );
		if ( is_wp_error( $mapped ) ) {
			$res->pushError( $mapped->get_error_message() );
			return $res->ajaxExec();
		}
		$labels = array(
			'category'     => __( 'Categories', 'woo-product-filter' ),
			'attribute'    => __( 'Attribute', 'woo-product-filter' ),
			'tags'         => __( 'Tags', 'woo-product-filter' ),
			'brand'        => __( 'Brands', 'woo-product-filter' ),
			'perfectbrand' => __( 'Brands', 'woo-product-filter' ),
			'price'        => __( 'Price slider', 'woo-product-filter' ),
			'price_range'  => __( 'Price ranges', 'woo-product-filter' ),
			'stock'        => __( 'Stock status', 'woo-product-filter' ),
			'onsale'       => __( 'On sale', 'woo-product-filter' ),
			'featured'     => __( 'Featured', 'woo-product-filter' ),
			'rating'       => __( 'Rating', 'woo-product-filter' ),
			'sortby'       => __( 'Sort by', 'woo-product-filter' ),
			'search'       => __( 'Search', 'woo-product-filter' ),
			'author'       => __( 'Seller', 'woo-product-filter' ),
			'custom_field' => __( 'Custom field', 'woo-product-filter' ),
		);
		$blocks = array();
		foreach ( $mapped['blocks'] as $i => $b ) {
			$kind = isset( $labels[ $b['type'] ] ) ? $labels[ $b['type'] ] : $b['type'];
			if ( 'attribute' === $b['type'] && ! empty( $b['taxonomy'] ) && function_exists( 'wc_attribute_label' ) ) {
				$kind .= ': ' . wc_attribute_label( $b['taxonomy'] );
			}
			$blocks[] = array(
				'i'       => $i,
				'source'  => isset( $b['source'] ) ? $b['source'] : ( isset( $b['title'] ) ? $b['title'] : '' ),
				'kind'    => $kind,
				'title'   => isset( $b['title'] ) ? $b['title'] : '',
				'display' => isset( $b['display'] ) ? $b['display'] : '',
				'choices' => WooBeWoo_PF_Importer_Base_Model::displayChoices( $b ),
			);
		}
		$existing = $mapper->importedAs( $setId );
		$wf       = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
		$res->addData( 'title', WooBeWoo_PF_Importer_Base_Model::uniqueTitle( $mapped['title'] ) );
		$res->addData( 'blocks', $blocks );
		$res->addData( 'mapped', $mapped['mapped'] );
		$res->addData( 'unmapped', $mapped['unmapped'] );
		$res->addData( 'existing', $existing ? array( 'id' => $existing, 'edit' => $wf ? $wf->getEditLink( $existing ) : '' ) : null );
		$res->addData( 'active', $mapper->active() );
		$res->addData( 'name', $mapper->name() );
		return $res->ajaxExec();
	}

	/**
	 * Where their filter is shown today (sidebars, pages, templates, Elementor, automatic placement).
	 */
	public function woobewoo_pf_importers_placements() {
		WooBeWoo_PF_Req::verifyRequest();
		$res    = new WooBeWoo_PF_Response();
		$mapper = $this->mapperOrFail( $res );
		if ( ! $mapper ) {
			return $res->ajaxExec();
		}
		$setId = sanitize_text_field( (string) WooBeWoo_PF_Req::getVar( 'set', 'post' ) );
		$res->addData( 'places', $this->getModel( 'importer_switch' )->scan( $mapper, $setId ) );
		$res->addData( 'sets', count( $mapper->sets() ) );
		$res->addData( 'active', $mapper->active() );
		return $res->ajaxExec();
	}

	/**
	 * Put our filter where theirs was, optionally turn their plugin off. One undo entry.
	 */
	public function woobewoo_pf_importers_switch() {
		WooBeWoo_PF_Req::verifyRequest();
		$res    = new WooBeWoo_PF_Response();
		$mapper = $this->mapperOrFail( $res );
		if ( ! $mapper ) {
			return $res->ajaxExec();
		}
		$setId  = sanitize_text_field( (string) WooBeWoo_PF_Req::getVar( 'set', 'post' ) );
		$filter = (int) WooBeWoo_PF_Req::getVar( 'filter', 'post' );
		$keys   = json_decode( wp_unslash( (string) WooBeWoo_PF_Req::getVar( 'places', 'post' ) ), true );
		$off    = '1' === (string) WooBeWoo_PF_Req::getVar( 'deactivate', 'post' );
		if ( $off && ! current_user_can( 'activate_plugins' ) ) {
			$off = false;
		}
		$snap = $this->getModel( 'importer_switch' )->apply( $mapper, $setId, $filter, is_array( $keys ) ? $keys : array(), $off );
		if ( is_wp_error( $snap ) ) {
			$res->pushError( $snap->get_error_message() );
			return $res->ajaxExec();
		}
		$res->addData( 'switch', $this->switchRow( $snap ) );
		$res->addMessage( esc_html__( 'Done. Your shop now shows the new filter where the old one was.', 'woo-product-filter' ) );
		return $res->ajaxExec();
	}

	public function woobewoo_pf_importers_undo() {
		WooBeWoo_PF_Req::verifyRequest();
		$res  = new WooBeWoo_PF_Response();
		$snap = $this->getModel( 'importer_switch' )->undo( sanitize_text_field( (string) WooBeWoo_PF_Req::getVar( 'switch', 'post' ) ) );
		if ( is_wp_error( $snap ) ) {
			$res->pushError( $snap->get_error_message() );
			return $res->ajaxExec();
		}
		$res->addData( 'switch', $this->switchRow( $snap ) );
		$res->addMessage(
			empty( $snap['undoneKept'] )
				? esc_html__( 'Undone. Everything is back as it was; the imported filter is kept as a draft.', 'woo-product-filter' )
				/* translators: %s: page titles */
				: sprintf( esc_html__( 'Undone, except these pages, edited since the switch: %s. Put their filter back there by hand.', 'woo-product-filter' ), esc_html( implode( ', ', $snap['undoneKept'] ) ) )
		);
		return $res->ajaxExec();
	}

	/**
	 * Log entry as shown on the screen.
	 */
	public function switchRow( $snap ) {
		$wf = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
		return array(
			'id'      => $snap['id'],
			'when'    => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $snap['time'] ),
			'rival'   => $snap['rivalName'],
			'filter'  => (int) $snap['filter'],
			'edit'    => $wf ? $wf->getEditLink( (int) $snap['filter'] ) : '',
			'places'  => count( (array) $snap['done'] ),
			'plugins' => count( (array) $snap['plugins'] ),
			'skipped' => (array) $snap['skipped'],
			'undone'  => ! empty( $snap['undone'] ),
		);
	}

	/**
	 * Side by side preview: our draft (through ?wpf_preview=) next to their shortcode page.
	 */
	public function woobewoo_pf_importers_preview() {
		WooBeWoo_PF_Req::verifyRequest();
		$res    = new WooBeWoo_PF_Response();
		$mapper = $this->mapperOrFail( $res );
		if ( ! $mapper ) {
			return $res->ajaxExec();
		}
		$setId  = sanitize_text_field( (string) WooBeWoo_PF_Req::getVar( 'set', 'post' ) );
		$mapped = $this->mappedWithChoices( $mapper, $setId );
		if ( is_wp_error( $mapped ) ) {
			$res->pushError( $mapped->get_error_message() );
			return $res->ajaxExec();
		}
		$setup = WooBeWoo_PF_Frame::_()->getModule( 'setup' );
		$ours  = '';
		if ( $setup && method_exists( $setup->getModel(), 'saveDraftSettings' ) ) {
			$draftId = $setup->getModel()->saveDraftSettings( $mapped['title'], $mapper->draftSettings( $mapped ) );
			$ours    = add_query_arg( array( 'wpf_preview' => $draftId, 'wpf_place' => 'above' ), wc_get_page_permalink( 'shop' ) );
		}
		$sets   = $mapper->sets();
		$theirs = '';
		$note   = '';
		$short  = '';
		foreach ( $sets as $s ) {
			if ( (string) $s['id'] === (string) $setId ) {
				$short = $s['shortcode'];
			}
		}
		if ( $mapper->active() && $short ) {
			// Drawn on the front end (shop page request): many filter plugins only start their storefront code outside admin.
			$theirs = add_query_arg(
				array(
					'wpf_rival_preview' => 1,
					'rival'             => $mapper->slug(),
					'set'               => $setId,
					'wpfNonce'          => wp_create_nonce( 'woobewoo-pf-save-nonce' ),
				),
				wc_get_page_permalink( 'shop' )
			);
		} else {
			$note = $mapper->active()
				? esc_html__( 'This plugin has no shortcode for this set, so there is nothing to show on the left.', 'woo-product-filter' )
				: esc_html__( 'This plugin is not active, so its filter cannot be drawn. Activate it to compare, or import straight away.', 'woo-product-filter' );
		}
		$res->addData( 'ours', $ours );
		$res->addData( 'theirs', $theirs );
		$res->addData( 'note', $note );
		$res->addData( 'title', $mapped['title'] );
		$res->addData( 'blocks', count( $mapped['blocks'] ) );
		$res->addData( 'mapped', $mapped['mapped'] );
		$res->addData( 'unmapped', $mapped['unmapped'] );
		return $res->ajaxExec();
	}

	/**
	 * Minimal page with the rival's shortcode and the theme's head/footer, for the preview iframe.
	 */
	public function woobewoo_pf_importers_rival_preview() {
		WooBeWoo_PF_Req::verifyRequest();
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'woo-product-filter' ), '', array( 'response' => 403 ) );
		}
		$slug   = sanitize_key( (string) WooBeWoo_PF_Req::getVar( 'rival', 'get' ) );
		$setId  = sanitize_text_field( (string) WooBeWoo_PF_Req::getVar( 'set', 'get' ) );
		$mapper = $this->getModule()->mapper( $slug );
		$short  = '';
		if ( $mapper && $mapper->active() ) {
			foreach ( $mapper->sets() as $s ) {
				if ( (string) $s['id'] === (string) $setId ) {
					$short = $s['shortcode'];
				}
			}
		}
		// Only shortcodes from our own mapper tables are rendered, never user supplied text.
		header( 'Content-Type: text/html; charset=UTF-8' );
		echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_language_attributes() is escaped by core.
		wp_head();
		echo '<style>body{margin:0;padding:16px;background:#fff;font-family:inherit}#wpadminbar{display:none}html{margin-top:0!important}</style></head><body class="wpf-rival-preview">';
		if ( $short ) {
			echo '<div class="wpf-rival-preview__inner">' . do_shortcode( $short ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- third party shortcode output, rendered on purpose for the owner's comparison.
		} else {
			echo '<p>' . esc_html__( 'Nothing to show: the plugin is not active.', 'woo-product-filter' ) . '</p>';
		}
		wp_footer();
		echo '</body></html>';
		exit;
	}

	/**
	 * Import one set (or every set of a rival with set=all) as drafts.
	 */
	public function woobewoo_pf_importers_import() {
		WooBeWoo_PF_Req::verifyRequest();
		$res    = new WooBeWoo_PF_Response();
		$mapper = $this->mapperOrFail( $res );
		if ( ! $mapper ) {
			return $res->ajaxExec();
		}
		$setId = sanitize_text_field( (string) WooBeWoo_PF_Req::getVar( 'set', 'post' ) );
		$ids   = 'all' === $setId ? wp_list_pluck( $mapper->sets(), 'id' ) : array( $setId );
		$out   = array();
		$wf    = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' );
		foreach ( $ids as $id ) {
			$mapped = 'all' === $setId ? $mapper->map( $id ) : $this->mappedWithChoices( $mapper, $id );
			if ( is_wp_error( $mapped ) ) {
				$out[] = array( 'set' => $id, 'error' => $mapped->get_error_message() );
				continue;
			}
			$created = $mapper->createDraft( $mapped, '', $id );
			if ( is_wp_error( $created ) ) {
				$out[] = array( 'set' => $id, 'error' => $created->get_error_message() );
				continue;
			}
			$out[] = array(
				'set'      => $id,
				'id'       => (int) $created,
				'title'    => $mapped['title'],
				'blocks'   => count( $mapped['blocks'] ),
				'edit'     => $wf ? $wf->getEditLink( (int) $created ) : '',
				'mapped'   => $mapped['mapped'],
				'unmapped' => $mapped['unmapped'],
			);
		}
		$res->addData( 'imported', $out );
		$done = count( array_filter( $out, function ( $r ) {
			return empty( $r['error'] );
		} ) );
		$res->addMessage( sprintf( /* translators: %d: count */ _n( '%d filter imported as a draft. It is not shown in the shop until you publish it.', '%d filters imported as drafts. They are not shown in the shop until you publish them.', $done, 'woo-product-filter' ), $done ) );
		return $res->ajaxExec();
	}

	public function getPermissions() {
		return array(
			WPF_METHODS => array(
				'woobewoo_pf_importers_detect'        => array( WPF_ADMIN ),
				'woobewoo_pf_importers_preview'       => array( WPF_ADMIN ),
				'woobewoo_pf_importers_rival_preview' => array( WPF_ADMIN ),
				'woobewoo_pf_importers_import'        => array( WPF_ADMIN ),
				'woobewoo_pf_importers_review'        => array( WPF_ADMIN ),
				'woobewoo_pf_importers_placements'    => array( WPF_ADMIN ),
				'woobewoo_pf_importers_switch'        => array( WPF_ADMIN ),
				'woobewoo_pf_importers_undo'          => array( WPF_ADMIN ),
			),
		);
	}
}
