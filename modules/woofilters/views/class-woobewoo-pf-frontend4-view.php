<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Frontend4_View Class
 *
 * Renders the 4.0 storefront markup: semantic fieldset/legend blocks with native inputs, chips, the phone drawer
 * shell and the step by step finder shell. No inline scripts; the vanilla JS in js/frontend-4 does the rest.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Frontend4_View {

	private $filter;
	private $settings;
	/** @var WooBeWoo_PF_Query */
	private $query;
	/** @var WooBeWoo_PF_Engine */
	private $engine;
	private $ctx;
	private $facets = array();
	private $id     = 0;
	private $uid    = '';
	private $n      = 0;

	/**
	 * Read-only access for block renderers (WooBeWoo_PF_Blocks `render` callbacks): $view->engine, $view->query,
	 * $view->settings, $view->facets, $view->filter, $view->ctx.
	 *
	 * @since 4.0.0
	 */
	public function __get( $name ) {
		if ( in_array( $name, array( 'engine', 'query', 'settings', 'facets', 'filter', 'ctx' ), true ) ) {
			return $this->{$name};
		}
		return null;
	}

	/**
	 * Full filter markup.
	 */
	public function render( $filter, $query, $engine, $ctx, $opts = array() ) {
		$this->filter   = $filter;
		$this->settings = $filter['settings'];
		$this->query    = $query;
		$this->engine   = $engine;
		$this->ctx      = $ctx;
		$this->id       = (int) $filter['id'];
		$this->uid      = 'wpf4-' . $this->id . ( empty( $opts['uid'] ) ? '' : '-' . sanitize_key( $opts['uid'] ) );
		$this->facets   = $engine->facets();
		$blocks         = WooBeWoo_PF_Frontend4::blocks( $this->settings );
		$style          = WooBeWoo_PF_Frontend4::setting( $this->settings, 'wbw_style' );
		$accent         = WooBeWoo_PF_Frontend4::setting( $this->settings, 'wbw_accent' );
		$onAccent       = WooBeWoo_PF_Frontend4::contrast_text( $accent );
		$drawer         = (bool) WooBeWoo_PF_Frontend4::setting( $this->settings, 'mobile_drawer' ) || (bool) WooBeWoo_PF_Frontend4::setting( $this->settings, 'mobile_drawer_always' ) || ! empty( $opts['force_drawer'] );
		$finder         = WooBeWoo_PF_Frontend4::setting( $this->settings, 'finder' );
		$finderOn       = ! empty( $finder['enabled'] ) || ! empty( $opts['force_finder'] );
		$config         = $this->config( $blocks, $finder, $finderOn );
		$classes        = array( 'wpf4', 'wpf4--' . $style );
		if ( $drawer ) {
			$classes[] = 'wpf4--drawer';
		}
		if ( WooBeWoo_PF_Frontend4::setting( $this->settings, 'mobile_drawer_always' ) ) {
			$classes[] = 'wpf4--drawer-always';
		}
		if ( self::bar_on( $this->settings ) ) {
			$classes[] = 'wpf4--bar';
		}
		$classes = apply_filters( 'woobewoo_pf_frontend4_wrapper_classes', $classes, $filter );

		$ref = empty( $filter['draft'] ) ? (string) $this->id : 'draft_' . $filter['draft'];
		$h  = '<div class="' . esc_attr( implode( ' ', $classes ) ) . '" id="' . esc_attr( $this->uid ) . '" data-wpf4 data-filter="' . esc_attr( $ref ) . '"';
		$h .= ' data-params="' . esc_attr( wp_json_encode( $query->params() ) ) . '"';
		$h .= ' data-ctx="' . esc_attr( wp_json_encode( array( 'taxonomy' => $ctx['taxonomy'], 'term_id' => (int) $ctx['term_id'], 'search' => $ctx['search'], 'all_products' => ! empty( $ctx['all_products'] ), 'base' => $ctx['base'], 'title' => $ctx['title'] ) + array_intersect_key( $ctx, array_flip( array( 'currency', 'lang', 'v' ) ) ) ) ) . '"';
		$h .= ' data-config="' . esc_attr( wp_json_encode( $config ) ) . '"';
		$h .= ' style="--wpf4-accent:' . esc_attr( $accent ) . ';--wpf4-on-accent:' . esc_attr( $onAccent ) . '">';
		$before = apply_filters( 'woobewoo_pf_frontend4_before_form', '', $filter, $query );
		if ( '' !== $before ) {
			$h .= '<div class="wpf4__before">' . $before . '</div>';
		}
		$h .= '<form class="wpf4__form" method="get" action="' . esc_url( $ctx['base'] ) . '" aria-label="' . esc_attr( WooBeWoo_PF_Frontend4::setting( $this->settings, 'filter_title' ) ) . '" data-wpf4-form>';
		$h .= $this->head();
		$h .= $this->chips();
		if ( $finderOn ) {
			$h .= '<button type="button" class="wpf4__finderBtn" data-wpf4-finder-open aria-haspopup="dialog">' . $this->icon( 'sparkle' ) . '<span>' . esc_html( empty( $finder['button'] ) ? __( 'Find it step by step', 'woo-product-filter' ) : $finder['button'] ) . '</span></button>';
		}
		$h .= '<div class="wpf4__blocks" data-wpf4-blocks>';
		foreach ( $blocks as $b ) {
			$h .= $this->block( $b );
		}
		$h .= '</div>';
		$applyWord = (string) WooBeWoo_PF_Frontend4::setting( $this->settings, 'apply_button_word' );
		$h .= '<button type="submit" class="wpf4__apply" data-wpf4-apply>' . esc_html( '' !== trim( $applyWord ) ? $applyWord : __( 'Apply', 'woo-product-filter' ) ) . '</button>';
		$h .= '</form>';
		$h .= '<p class="wpf4__live" role="status" aria-live="polite" data-wpf4-live></p>';
		if ( $drawer ) {
			$h .= $this->drawer();
		}
		if ( $finderOn ) {
			$h .= $this->finder( $finder );
		}
		$after = apply_filters( 'woobewoo_pf_frontend4_after_form', '', $filter, $query );
		if ( '' !== $after ) {
			$h .= '<div class="wpf4__after">' . $after . '</div>';
		}
		$h .= '</div>';
		return $h;
	}

	/**
	 * Settings the script needs (no secrets, all public).
	 */
	private function config( $blocks, $finder, $finderOn ) {
		$s        = $this->settings;
		$steps    = array();
		$overrides = WooBeWoo_PF_Frontend4::setting( $s, 'mobile_overrides' );
		if ( $finderOn ) {
			$order = ! empty( $finder['steps'] ) && is_array( $finder['steps'] ) ? $finder['steps'] : array();
			if ( ! $order ) {
				foreach ( $blocks as $b ) {
					if ( in_array( $b['kind'], array( 'terms', 'price', 'stock', 'rating' ), true ) || $this->finder_block( $b ) ) {
						$order[] = $b['uniqId'] ? $b['uniqId'] : $b['key'];
					}
				}
			}
			foreach ( $order as $ref ) {
				foreach ( $blocks as $b ) {
					if ( $ref === $b['uniqId'] || $ref === $b['key'] ) {
						$qText   = ( ! empty( $finder['questions'] ) && is_array( $finder['questions'] ) && ! empty( $finder['questions'][ $ref ] ) ) ? $finder['questions'][ $ref ] : $this->default_question( $b );
						$step    = array(
							'key'      => $b['key'],
							'uniq'     => $b['uniqId'],
							'title'    => $b['title'],
							'question' => $qText,
							'kind'     => $b['kind'],
							'single'   => (bool) $b['single'],
						);
						if ( $this->finder_block( $b ) ) {
							$step['custom'] = true; // a registered kind: answered from its counts and labels like a terms step
						}
						$steps[] = $step;
					}
				}
			}
		}
		return array(
			'filterId'      => $this->id,
			'autoUpdate'    => (bool) WooBeWoo_PF_Frontend4::setting( $s, 'auto_update_filter' ),
			'reload'        => ! WooBeWoo_PF_Frontend4::setting( $s, 'enable_ajax' ),
			'checkedTop'    => (bool) WooBeWoo_PF_Frontend4::setting( $s, 'checked_items_top' ),
			'overlay'       => (bool) WooBeWoo_PF_Frontend4::setting( $s, 'loading_overlay' ),
			'selector'      => (string) WooBeWoo_PF_Frontend4::setting( $s, 'product_container_selector' ),
			'pagination'    => WooBeWoo_PF_Frontend4::setting( $s, 'pagination_mode' ),
			'remember'      => (bool) WooBeWoo_PF_Frontend4::setting( $s, 'remember_filters' ),
			'updateTitle'   => (bool) WooBeWoo_PF_Frontend4::setting( $s, 'canonical_filtered' ),
			'showChips'     => (bool) WooBeWoo_PF_Frontend4::setting( $s, 'show_chips' ),
			'live'          => (bool) WooBeWoo_PF_Frontend4::setting( $s, 'show_counts_bar' ),
			'drawer'        => array(
				'enabled'    => (bool) WooBeWoo_PF_Frontend4::setting( $s, 'mobile_drawer' ),
				'always'     => (bool) WooBeWoo_PF_Frontend4::setting( $s, 'mobile_drawer_always' ),
				'text'       => (string) WooBeWoo_PF_Frontend4::setting( $s, 'mobile_drawer_text' ),
				'chips'      => (bool) WooBeWoo_PF_Frontend4::setting( $s, 'mobile_drawer_chips' ),
				'results'    => (bool) WooBeWoo_PF_Frontend4::setting( $s, 'mobile_drawer_results' ),
				'breakpoint' => (int) WooBeWoo_PF_Frontend4::setting( $s, 'mobile_breakpoint' ),
			),
			'bar'           => array(
				'enabled'    => self::bar_on( $s ),
				'breakpoint' => (int) WooBeWoo_PF_Frontend4::setting( $s, 'mobile_breakpoint' ),
				'i18n'       => array(
					'all'   => __( 'All filters', 'woo-product-filter' ),
					'close' => __( 'Close filters', 'woo-product-filter' ),
					'clear' => __( 'Clear all', 'woo-product-filter' ),
					'show'  => __( 'Show results', 'woo-product-filter' ),
					/* translators: %s: number of products */
					'showN' => __( 'Show %s products', 'woo-product-filter' ),
				),
			),
			'mobileOverrides' => array(
				'enabled' => ! empty( $overrides['enabled'] ),
				'layout'  => isset( $overrides['layout'] ) ? sanitize_key( $overrides['layout'] ) : '',
			),
			'finder'        => array(
				'enabled'    => $finderOn,
				/* translators: %s: filter title or site name */
				'title'      => ! empty( $finder['title'] ) ? $finder['title'] : sprintf( __( '%s finder', 'woo-product-filter' ), $this->ctx['title'] ? $this->ctx['title'] : get_bloginfo( 'name' ) ),
				'showCounts' => ! isset( $finder['show_counts'] ) || ! empty( $finder['show_counts'] ),
				'steps'      => $steps,
			),
			'sizesLeft'     => (bool) WooBeWoo_PF_Frontend4::setting( $s, 'sizes_left_badge' ),
			'threshold'     => (int) WooBeWoo_PF_Frontend4::setting( $s, 'sizes_left_threshold' ),
			'noProducts'    => (string) WooBeWoo_PF_Frontend4::setting( $s, 'text_no_products' ),
			'sideWidth'     => self::side_width( $s ),
			// Block rules (modules/conditions): key => { parent, min, single }, evaluated by js/frontend-4/conditions.js.
			'conditions'    => apply_filters( 'woobewoo_pf_frontend4_config_conditions', array(), $this ),
			'i18n'          => array(
				'showLess'  => __( 'Show less', 'woo-product-filter' ),
				'clear'     => __( 'Clear', 'woo-product-filter' ),
				/* translators: 1: the "Show more" label, 2: number of hidden values */
				'moreCount' => __( '%1$s (%2$s)', 'woo-product-filter' ),
			),
		);
	}

	private function default_question( $b ) {
		switch ( $b['kind'] ) {
			case 'price':
				return __( 'How much do you want to spend?', 'woo-product-filter' );
			case 'stock':
				return __( 'Only show what is in stock?', 'woo-product-filter' );
			case 'rating':
				return __( 'How well rated should it be?', 'woo-product-filter' );
			default:
				$q = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::question( WooBeWoo_PF_Blocks::of( $b ) ) : '';
				if ( '' !== $q ) {
					return $q;
				}
		}
		/* translators: %s: filter block title, lower case (for example "size") */
		return sprintf( __( 'Which %s?', 'woo-product-filter' ), mb_strtolower( $b['title'] ) );
	}

	/**
	 * A registered block that asked to be a finder step ('finder' => true).
	 */
	private function finder_block( $b ) {
		$def = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::of( $b ) : null;
		return $def && ! empty( $def['finder'] ) && ! in_array( $b['kind'], WooBeWoo_PF_Blocks::BUILTIN_KINDS, true );
	}

	/**
	 * Width of the filter column beside the products, clamped to the schema bounds (220 to 400 px).
	 */
	/**
	 * The filter bar ("All filters" plus one dropdown button per block) on wide screens: setting top_bar, where
	 * empty means "when the filter sits above the products" (wizard placement or the builder's "Where on the page").
	 *
	 * @since 4.0.0
	 */
	public static function bar_on( $settings ) {
		$v = (string) WooBeWoo_PF_Frontend4::setting( $settings, 'top_bar' );
		if ( '1' === $v ) {
			return true;
		}
		if ( '0' === $v ) {
			return false;
		}
		return 'above' === (string) WooBeWoo_PF_Frontend4::setting( $settings, 'wbw_placement' );
	}

	public static function side_width( $settings ) {
		$schema = WooBeWoo_PF_Frontend4::schema();
		$min    = isset( $schema['side_width']['min'] ) ? (int) $schema['side_width']['min'] : 220;
		$max    = isset( $schema['side_width']['max'] ) ? (int) $schema['side_width']['max'] : 400;
		$w      = (int) WooBeWoo_PF_Frontend4::setting( $settings, 'side_width' );
		if ( $w <= 0 ) {
			$w = isset( $schema['side_width']['default'] ) ? (int) $schema['side_width']['default'] : 280;
		}
		return max( $min, min( $max, $w ) );
	}

	/* ------------------------------------------------------------------ pieces */

	private function head() {
		$title = WooBeWoo_PF_Frontend4::setting( $this->settings, 'filter_title' );
		$h     = '<div class="wpf4__head"><span class="wpf4__title" id="' . esc_attr( $this->uid ) . '-title">' . esc_html( $title ) . '</span>';
		if ( WooBeWoo_PF_Frontend4::setting( $this->settings, 'show_clean_button' ) ) {
			$h .= '<button type="button" class="wpf4__clear" data-wpf4-clear' . ( $this->query->has_filters() ? '' : ' hidden' ) . '>' . esc_html( WooBeWoo_PF_Frontend4::setting( $this->settings, 'show_clean_button_word' ) ) . '</button>';
		}
		// "Remember the last filters" on desktop (the drawer has its own): filled and shown by the script.
		if ( WooBeWoo_PF_Frontend4::setting( $this->settings, 'remember_filters' ) ) {
			$h .= '<button type="button" class="wpf4__remember" data-wpf4-remember-desk hidden aria-label="' . esc_attr__( 'Use your last filters again', 'woo-product-filter' ) . '">' . $this->icon( 'refresh' ) . '<span data-wpf4-remember-text></span></button>';
		}
		return $h . '</div>';
	}

	private function chips() {
		$picked = WooBeWoo_PF_Frontend4::picked_labels( $this->query, $this->filter );
		$h      = '<div class="wpf4__chips" role="group" aria-label="' . esc_attr__( 'Picked', 'woo-product-filter' ) . '" data-wpf4-chips' . ( $picked ? '' : ' hidden' ) . '>';
		foreach ( $picked as $p ) {
			$h .= $this->chip( $p['key'], $p['value'], $p['label'] );
		}
		return $h . '</div>';
	}

	public function chip( $key, $value, $label ) {
		return '<button type="button" class="wpf4__chip" data-wpf4-chip data-key="' . esc_attr( $key ) . '" data-value="' . esc_attr( $value ) . '">' . esc_html( $label ) . ' <span aria-hidden="true">×</span><span class="wpf4__sr">' . esc_html__( 'remove', 'woo-product-filter' ) . '</span></button>';
	}

	/**
	 * One block: fieldset, legend and the options in the configured display type (plus a phone variant).
	 */
	private function block( $b ) {
		$overrides = WooBeWoo_PF_Frontend4::setting( $this->settings, 'mobile_overrides' );
		$mobileDisplay = '';
		if ( ! empty( $overrides['enabled'] ) && ! empty( $overrides['blocks'] ) && is_array( $overrides['blocks'] ) ) {
			$ref = isset( $overrides['blocks'][ $b['uniqId'] ] ) ? $overrides['blocks'][ $b['uniqId'] ] : ( isset( $overrides['blocks'][ $b['key'] ] ) ? $overrides['blocks'][ $b['key'] ] : '' );
			$ref = sanitize_key( (string) $ref );
			if ( $ref && $ref !== $b['display'] ) {
				$mobileDisplay = WooBeWoo_PF_Frontend4::display_type( array( 'id' => $b['block']['id'], 'settings' => array_merge( $b['block']['settings'], array( 'display_type' => $ref ) ) ), $b['kind'], true );
			}
		}
		$collapsed = in_array( WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_enable_title' ), array( 'yes_close', 'yes_closed' ), true );
		$showTitle = 'no' !== WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_enable_title' );
		$blockId   = $this->uid . '-b-' . sanitize_key( $b['key'] );
		$extra     = '';
		// "Show more" after N rows: chips, squares, swatches and tick box lists, unless the block scrolls at a fixed height.
		$moreRows = (int) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'show_more_rows' );
		// 4.0.0: "Show the N most popular values first" (chips, squares and tick box lists; swatches and category trees keep the rows).
		$moreTop  = (int) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'show_more_top' );
		$folds    = array( 'chips', 'squares', 'swatches', 'list' );
		if ( ( $moreRows > 0 || $moreTop > 0 ) && ( in_array( $b['display'], $folds, true ) || ( $mobileDisplay && in_array( $mobileDisplay, $folds, true ) ) ) && ! (int) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_max_height' ) && in_array( $b['kind'], array( 'terms', 'author', 'stock', 'sort' ), true ) ) {
			$label  = trim( (string) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'show_more_label' ) );
			$extra .= ' data-show-more="' . $moreRows . '" data-show-more-label="' . esc_attr( '' === $label ? __( 'Show more', 'woo-product-filter' ) : $label ) . '"';
			if ( $moreTop > 0 ) {
				$extra .= ' data-show-more-top="' . $moreTop . '"';
			}
		}
		if ( WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_hide_empty' ) ) {
			$extra .= ' data-hide-empty="1"';
		}
		// Phones (the drawer): "Open this block on phones" off (yes_close) starts it folded; "no" hides the title there.
		$phoneTitle = isset( $b['block']['settings']['f_enable_title_mobile'] ) ? sanitize_key( (string) $b['block']['settings']['f_enable_title_mobile'] ) : 'yes_open';
		if ( in_array( $phoneTitle, array( 'yes_close', 'yes_closed', 'no' ), true ) ) {
			$extra .= ' data-phone-title="' . esc_attr( 'no' === $phoneTitle ? 'no' : 'closed' ) . '"';
		}
		/**
		 * Extra data-* attributes on the block's fieldset (modules/conditions adds its rules): name without "data-" => value.
		 *
		 * @since 4.0.0
		 * @param array                     $attrs Attribute name (without the data- prefix) => value.
		 * @param array                     $b     The resolved block.
		 * @param WooBeWoo_PF_Frontend4_View $view  This view.
		 */
		$attrs = apply_filters( 'woobewoo_pf_block_attrs', array(), $b, $this );
		foreach ( (array) $attrs as $name => $value ) {
			$name = preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $name ) );
			if ( '' !== $name && is_scalar( $value ) ) {
				$extra .= ' data-' . esc_attr( $name ) . '="' . esc_attr( (string) $value ) . '"';
			}
		}
		$h  = '<fieldset class="wpf4__block wpf4__block--' . esc_attr( $b['display'] ) . ' wpf4__block--' . esc_attr( $b['kind'] ) . ( $collapsed ? ' is-collapsed' : '' ) . '" id="' . esc_attr( $blockId ) . '" data-wpf4-block data-key="' . esc_attr( $b['key'] ) . '" data-kind="' . esc_attr( $b['kind'] ) . '" data-logic="' . esc_attr( $b['logic'] ) . '" data-display="' . esc_attr( $b['display'] ) . '"' . ( $mobileDisplay ? ' data-display-mobile="' . esc_attr( $mobileDisplay ) . '"' : '' ) . ( $b['uniqId'] ? ' data-uniq="' . esc_attr( $b['uniqId'] ) . '"' : '' ) . ( $b['taxonomy'] ? ' data-taxonomy="' . esc_attr( $b['taxonomy'] ) . '"' : '' ) . $extra . '>';
		$hint = $this->legend_hint( $b );
		// Title and chevron share one line: the fold button lives inside the legend, pinned to the right edge.
		$h   .= '<legend class="wpf4__legend' . ( $showTitle ? '' : ' wpf4__sr' ) . '"><span class="wpf4__legendText">' . esc_html( $b['title'] ) . '</span><span class="wpf4__hint" data-wpf4-hint>' . ( $hint ? ' · ' . esc_html( $hint ) : '' ) . '</span>';
		if ( $showTitle && 'toggle' !== $b['display'] ) {
			/* translators: %s: filter block title */
			$h .= '<button type="button" class="wpf4__fold" data-wpf4-fold aria-expanded="' . ( $collapsed ? 'false' : 'true' ) . '" aria-controls="' . esc_attr( $blockId ) . '-body"><span class="wpf4__sr">' . esc_html( sprintf( __( 'Show or hide %s', 'woo-product-filter' ), $b['title'] ) ) . '</span>' . $this->icon( 'chevron' ) . '</button>';
		}
		$h .= '</legend>';
		$h .= '<div class="wpf4__body" id="' . esc_attr( $blockId ) . '-body"' . ( $collapsed ? ' hidden' : '' ) . '>';
		$h .= $this->options( $b, $b['display'], false );
		if ( $mobileDisplay ) {
			$h .= $this->options( $b, $mobileDisplay, true );
		}
		$h .= '</div></fieldset>';
		return $h;
	}

	/**
	 * "2 left in 42" for a picked size with low stock, from the index.
	 */
	private function legend_hint( $b ) {
		if ( 'terms' !== $b['kind'] || ! $b['taxonomy'] || 0 !== strpos( $b['taxonomy'], 'pa_' ) ) {
			return '';
		}
		if ( ! WooBeWoo_PF_Frontend4::setting( $this->settings, 'sizes_left_badge' ) || ! WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_sizes_left' ) ) {
			return '';
		}
		if ( empty( $this->query->terms[ $b['key'] ] ) ) {
			return '';
		}
		$threshold = (int) WooBeWoo_PF_Frontend4::setting( $this->settings, 'sizes_left_threshold' );
		$parts     = array();
		foreach ( $this->query->terms[ $b['key'] ] as $slug ) {
			$left = $this->engine->units_left( $b['taxonomy'], $slug );
			if ( null !== $left && $left > 0 && $left <= $threshold ) {
				$term    = get_term_by( 'slug', $slug, $b['taxonomy'] );
				/* translators: 1: number of items left in stock, 2: term name */
				$parts[] = sprintf( __( '%1$d left in %2$s', 'woo-product-filter' ), $left, $term ? $term->name : $slug );
			}
		}
		return implode( ', ', $parts );
	}

	/**
	 * The options of a block in a display type.
	 */
	private function options( $b, $display, $mobile ) {
		$cls = 'wpf4__options wpf4__options--' . $display . ( $mobile ? ' wpf4__options--mobile' : ' wpf4__options--desktop' );
		$attr = ' data-wpf4-options' . ( $mobile ? ' data-mobile="1"' : '' );
		switch ( $b['kind'] ) {
			case 'terms':
				return '<div class="' . esc_attr( $cls ) . '"' . $attr . ' role="group">' . $this->term_options( $b, $display, $mobile ) . '</div>';
			case 'author':
				return '<div class="' . esc_attr( $cls ) . '"' . $attr . ' role="group">' . $this->author_options( $b, $display, $mobile ) . '</div>';
			case 'price':
				if ( 'range_list' === $display || 'list' === $display || 'dropdown' === $display ) {
					return '<div class="' . esc_attr( $cls ) . '"' . $attr . ' role="group">' . $this->range_list( $b, $mobile, 'dropdown' === $display ? 'dropdown' : 'list' ) . '</div>';
				}
				return '<div class="' . esc_attr( $cls ) . '"' . $attr . '>' . $this->slider( $b, 'price', $mobile ) . '</div>';
			case 'meta':
				return '<div class="' . esc_attr( $cls ) . '"' . $attr . '>' . $this->slider( $b, $b['key'], $mobile ) . '</div>';
			case 'stock':
				return '<div class="' . esc_attr( $cls ) . '"' . $attr . ' role="group">' . $this->stock_options( $b, $display, $mobile ) . '</div>';
			case 'sale':
			case 'featured':
				return '<div class="' . esc_attr( $cls ) . '"' . $attr . '>' . $this->toggle( $b, $mobile ) . '</div>';
			case 'rating':
				if ( 'dropdown' === $display ) {
					return '<div class="' . esc_attr( $cls ) . '"' . $attr . ' role="group">' . $this->rating_options( $b, $display, $mobile ) . '</div>';
				}
				return '<div class="' . esc_attr( $cls ) . ' wpf4__options--stars"' . $attr . '>' . $this->rating_pills( $b, $mobile ) . '</div>';
			case 'sort':
				return '<div class="' . esc_attr( $cls ) . '"' . $attr . '>' . $this->sort_options( $b, $display, $mobile ) . '</div>';
			case 'q':
			case 'text':
				return '<div class="' . esc_attr( $cls ) . '"' . $attr . '>' . $this->search_box( $b, $mobile ) . '</div>';
		}
		// A registered block (WooBeWoo_PF_Blocks) renders its own options inside the same wrapper.
		$def = class_exists( 'WooBeWoo_PF_Blocks' ) ? WooBeWoo_PF_Blocks::of( $b ) : null;
		if ( $def ) {
			$inner = WooBeWoo_PF_Blocks::render( $def, $b, $display, $mobile, $this );
			if ( '' === $inner ) {
				return '';
			}
			$group = in_array( $display, array( 'slider', 'toggle', 'search', 'range_list' ), true ) ? '' : ' role="group"';
			return '<div class="' . esc_attr( $cls ) . '"' . $attr . $group . '>' . $inner . '</div>';
		}
		return '';
	}

	/**
	 * Terms of a taxonomy block in the requested order, with counts from the facets.
	 */
	private function term_list( $b ) {
		$s       = $b['block']['settings'];
		// "Only these choices": 3.x stored f_mlist[], the 4.0 builder's Advanced row writes f_mlist.
		$mlist   = ! empty( $s['f_mlist[]'] ) ? $s['f_mlist[]'] : ( ! empty( $s['f_mlist'] ) ? $s['f_mlist'] : '' );
		$include = '' !== $mlist ? array_filter( array_map( 'intval', explode( ',', is_array( $mlist ) ? implode( ',', $mlist ) : (string) $mlist ) ) ) : array();
		$exclude = array();
		foreach ( array( 'f_exclude_terms[]', 'f_exclude_terms' ) as $k ) {
			if ( ! empty( $s[ $k ] ) ) {
				$exclude = array_merge( $exclude, array_filter( array_map( 'intval', explode( ',', (string) $s[ $k ] ) ) ) );
			}
		}
		$args = array(
			'taxonomy'   => $b['taxonomy'],
			'hide_empty' => false,
		);
		if ( $include ) {
			$args['include'] = $include;
		}
		if ( $exclude ) {
			$args['exclude'] = $exclude; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- engine argument (ids to hide), resolved against the index tables
		}
		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return array();
		}
		$counts = isset( $this->facets[ $b['key'] ]['counts'] ) ? $this->facets[ $b['key'] ]['counts'] : array();
		$sort   = WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_sort_by' );
		$asNum  = WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_sort_as_numbers' );
		if ( $include && ! empty( $s['f_order_custom'] ) ) {
			$pos = array_flip( $include );
			usort( $terms, function ( $x, $y ) use ( $pos ) {
				return ( isset( $pos[ $x->term_id ] ) ? $pos[ $x->term_id ] : 999 ) - ( isset( $pos[ $y->term_id ] ) ? $pos[ $y->term_id ] : 999 );
			} );
		} elseif ( 'count' === $sort ) {
			usort( $terms, function ( $x, $y ) use ( $counts ) {
				return ( isset( $counts[ $y->slug ] ) ? $counts[ $y->slug ] : 0 ) - ( isset( $counts[ $x->slug ] ) ? $counts[ $x->slug ] : 0 );
			} );
		} elseif ( 'desc' === $sort || 'asc' === $sort || $asNum || 0 === strpos( $b['taxonomy'], 'pa_' ) ) {
			// Attribute terms follow their menu order unless the owner asked for a name order.
			if ( 'desc' === $sort || 'asc' === $sort ) {
				usort( $terms, function ( $x, $y ) use ( $asNum, $sort ) {
					$r = ( $asNum && is_numeric( $x->name ) && is_numeric( $y->name ) ) ? ( (float) $x->name <=> (float) $y->name ) : strnatcasecmp( $x->name, $y->name );
					return 'desc' === $sort ? -$r : $r;
				} );
			} elseif ( 0 === strpos( $b['taxonomy'], 'pa_' ) && function_exists( 'wc_attribute_orderby' ) ) {
				$orderby = wc_attribute_orderby( $b['taxonomy'] );
				if ( 'menu_order' === $orderby ) {
					usort( $terms, function ( $x, $y ) {
						$ox = (int) get_term_meta( $x->term_id, 'order', true );
						$oy = (int) get_term_meta( $y->term_id, 'order', true );
						return $ox === $oy ? strnatcasecmp( $x->name, $y->name ) : $ox - $oy;
					} );
				} elseif ( 'name_num' === $orderby ) {
					usort( $terms, function ( $x, $y ) {
						return strnatcasecmp( $x->name, $y->name );
					} );
				}
			}
		}
		if ( $b['hierarchical'] && ! $include ) {
			$terms = $this->tree( $terms );
		}
		return apply_filters( 'woobewoo_pf_frontend4_terms', $terms, $b, $this->filter );
	}

	/**
	 * Flatten a hierarchical term list depth first; sets ->depth.
	 */
	private function tree( $terms, $parent = 0, $depth = 0 ) {
		$out = array();
		foreach ( $terms as $t ) {
			if ( (int) $t->parent === (int) $parent ) {
				$t->depth = $depth;
				$out[]    = $t;
				$out      = array_merge( $out, $this->tree( $terms, $t->term_id, $depth + 1 ) );
			}
		}
		if ( 0 === $depth && count( $out ) < count( $terms ) ) {
			// Orphans (parent excluded): add them flat.
			$ids = wp_list_pluck( $out, 'term_id' );
			foreach ( $terms as $t ) {
				if ( ! in_array( $t->term_id, $ids, true ) ) {
					$t->depth = 0;
					$out[]    = $t;
				}
			}
		}
		return $out;
	}

	private function term_options( $b, $display, $mobile ) {
		$terms     = $this->term_list( $b );
		$counts    = isset( $this->facets[ $b['key'] ]['counts'] ) ? $this->facets[ $b['key'] ]['counts'] : array();
		$picked    = isset( $this->query->terms[ $b['key'] ] ) ? $this->query->terms[ $b['key'] ] : array();
		$hideEmpty = WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_hide_empty' );
		$showCount = WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_show_count' );
		$name      = 'wpf_' . $b['key'];
		$opts      = array();
		foreach ( $terms as $t ) {
			$c  = isset( $counts[ $t->slug ] ) ? (int) $counts[ $t->slug ] : 0;
			$on = in_array( $t->slug, $picked, true );
			if ( $hideEmpty && 0 === $c && ! $on ) {
				continue;
			}
			$opt = array(
				'value'   => $t->slug,
				'label'   => $t->name,
				'count'   => $c,
				'checked' => $on,
				'depth'   => isset( $t->depth ) ? (int) $t->depth : 0,
				'term'    => $t,
			);
			if ( 'swatches' === $display ) {
				$opt['swatch'] = $this->swatch_for( $t );
			}
			$opts[] = $opt;
		}
		return $this->render_options( $b, $display, $name, $opts, $showCount, $mobile );
	}

	private function author_options( $b, $display, $mobile ) {
		$counts = isset( $this->facets['author']['counts'] ) ? $this->facets['author']['counts'] : array();
		$picked = isset( $this->query->terms['author'] ) ? $this->query->terms['author'] : array();
		$opts   = array();
		foreach ( $counts as $uid => $c ) {
			$u = get_user_by( 'id', (int) $uid );
			if ( ! $u ) {
				continue;
			}
			$opts[] = array(
				'value'   => $u->user_nicename,
				'label'   => $u->display_name,
				'count'   => (int) $c,
				'checked' => in_array( $u->user_nicename, $picked, true ) || in_array( (string) $u->ID, $picked, true ),
				'depth'   => 0,
			);
		}
		/**
		 * The options of an Author / Vendors block before they are sorted and rendered (value, label, count, checked,
		 * depth): Pro swaps user names for vendor store names here.
		 *
		 * @since 4.0.0
		 */
		$opts = apply_filters( 'woobewoo_pf_author_options', $opts, $b, $this );
		usort( $opts, function ( $x, $y ) {
			return strcasecmp( $x['label'], $y['label'] );
		} );
		return $this->render_options( $b, $display, 'wpf_author', $opts, WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_show_count' ), $mobile );
	}

	/**
	 * Generic option renderer for list / chips / squares / swatches / dropdown. Public for registered block renderers (@since 4.0.0).
	 */
	public function render_options( $b, $display, $name, $opts, $showCount, $mobile ) {
		$single = ! empty( $b['single'] );
		$type   = $single ? 'radio' : 'checkbox';
		$dis    = $mobile ? ' disabled' : '';
		$maxH   = (int) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_max_height' );
		if ( 'dropdown' === $display && $single ) {
			$h  = '<label class="wpf4__sr" for="' . esc_attr( $this->fid( $name, $mobile ) ) . '">' . esc_html( $b['title'] ) . '</label>';
			$h .= '<select class="wpf4__select" id="' . esc_attr( $this->fid( $name, $mobile ) ) . '" name="' . esc_attr( $name ) . '"' . $dis . ' data-wpf4-control>';
			$h .= '<option value="">' . esc_html( WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_dropdown_first_option_text' ) ) . '</option>';
			foreach ( $opts as $o ) {
				$h .= '<option value="' . esc_attr( $o['value'] ) . '"' . ( $o['checked'] ? ' selected' : '' ) . ( 0 === $o['count'] && ! $o['checked'] ? ' disabled' : '' ) . ' data-label="' . esc_attr( $o['label'] ) . '" data-count="' . (int) $o['count'] . '">' . esc_html( str_repeat( '- ', $o['depth'] ) . $o['label'] ) . ( $showCount ? ' (' . (int) $o['count'] . ')' : '' ) . '</option>';
			}
			return $h . '</select>';
		}
		if ( 'dropdown' === $display ) {
			// Multi pick dropdown: a disclosure with the checkbox list inside.
			$pickedLabels = array();
			foreach ( $opts as $o ) {
				if ( $o['checked'] ) {
					$pickedLabels[] = $o['label'];
				}
			}
			$h  = '<details class="wpf4__dd" data-wpf4-dd><summary class="wpf4__ddSummary"><span data-wpf4-dd-label data-any="' . esc_attr( WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_dropdown_first_option_text' ) ) . '">' . esc_html( $pickedLabels ? implode( ', ', $pickedLabels ) : WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_dropdown_first_option_text' ) ) . '</span>' . $this->icon( 'chevron' ) . '</summary>';
			$h .= '<div class="wpf4__ddList">' . $this->render_options( $b, 'list', $name, $opts, $showCount, $mobile ) . '</div></details>';
			return $h;
		}
		$h = '';
		if ( WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_show_search_input' ) && count( $opts ) > 8 ) {
			$sid = $this->fid( $name . '_search', $mobile );
			/* translators: %s: filter block title */
			$h  .= '<div class="wpf4__search"><label class="wpf4__sr" for="' . esc_attr( $sid ) . '">' . esc_html( sprintf( __( 'Search %s', 'woo-product-filter' ), $b['title'] ) ) . '</label><input type="search" class="wpf4__searchInput" id="' . esc_attr( $sid ) . '" placeholder="' . esc_attr( '' !== trim( (string) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_search_label' ) ) ? WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_search_label' ) : __( 'Search…', 'woo-product-filter' ) ) . '" data-wpf4-list-search autocomplete="off"></div>';
		}
		// 4.0.0: two to four short choices with no child values show as one row of tabs (chips markup, no tick boxes).
		$tabs = false;
		if ( in_array( $display, array( 'list', 'chips', 'squares' ), true ) && WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_few_as_tabs' ) && count( $opts ) >= 2 && count( $opts ) <= 4 && ! $single ) {
			$tabs = true;
			foreach ( $opts as $o ) {
				if ( ! empty( $o['depth'] ) || ( function_exists( 'mb_strlen' ) ? mb_strlen( (string) $o['label'] ) : strlen( (string) $o['label'] ) ) > 18 ) {
					$tabs = false;
					break;
				}
			}
		}
		if ( $tabs ) {
			$h .= '<div class="wpf4__list wpf4__list--tabs" data-wpf4-tabs>';
			foreach ( $opts as $o ) {
				$h .= $this->option( 'chips', $type, $name, $o, $showCount, $mobile );
			}
			return $h . '</div>';
		}
		$h .= '<div class="wpf4__list"' . ( $maxH ? ' style="max-height:' . $maxH . 'px"' : '' ) . '>';
		if ( $single && 'list' === $display ) {
			$id  = $this->fid( $name . '_any', $mobile );
			$any = true;
			foreach ( $opts as $o ) {
				if ( $o['checked'] ) {
					$any = false;
				}
			}
			$h .= '<label class="wpf4__opt wpf4__opt--list" for="' . esc_attr( $id ) . '"><input type="radio" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value=""' . ( $any ? ' checked' : '' ) . $dis . ' data-wpf4-control data-label=""><span class="wpf4__optText">' . esc_html( WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_dropdown_first_option_text' ) ) . '</span></label>';
		}
		foreach ( $opts as $o ) {
			$h .= $this->option( $display, $type, $name, $o, $showCount, $mobile );
		}
		return $h . '</div>';
	}

	/**
	 * One option (label + input) of a list / chips / squares / swatches block. Public for registered block renderers.
	 *
	 * @since 4.0.0 public
	 */
	public function option( $display, $type, $name, $o, $showCount, $mobile ) {
		$id    = $this->fid( $name . '_' . $o['value'], $mobile );
		$empty = 0 === (int) $o['count'] && ! $o['checked'];
		$cls   = 'wpf4__opt wpf4__opt--' . $display;
		if ( 'swatches' === $display ) {
			$cls .= ' wpf4__swatch';
		} elseif ( 'squares' === $display ) {
			$cls .= ' wpf4__square';
		} elseif ( 'chips' === $display ) {
			$cls .= ' wpf4__chipOpt';
		}
		if ( $empty ) {
			$cls .= ' is-empty';
		}
		if ( $o['checked'] ) {
			$cls .= ' is-checked';
		}
		if ( ! empty( $o['depth'] ) ) {
			$cls .= ' wpf4__opt--child';
		}
		$h  = '<label class="' . esc_attr( $cls ) . '" for="' . esc_attr( $id ) . '"' . ( ! empty( $o['depth'] ) ? ' style="--wpf4-depth:' . (int) $o['depth'] . '"' : '' ) . '>';
		$h .= '<input type="' . $type . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $o['value'] ) . '"' . ( $o['checked'] ? ' checked' : '' ) . ( $mobile ? ' disabled' : '' ) . ' data-wpf4-control data-label="' . esc_attr( $o['label'] ) . '" data-count="' . (int) $o['count'] . '">';
		if ( 'swatches' === $display ) {
			$sw = isset( $o['swatch'] ) ? $o['swatch'] : array( 'type' => 'none' );
			if ( 'image' === $sw['type'] ) {
				$h .= '<span class="wpf4__swatchColor" style="background-image:url(' . esc_url( $sw['url'] ) . ')"></span>';
			} elseif ( 'color' === $sw['type'] ) {
				$h .= '<span class="wpf4__swatchColor" style="background:' . esc_attr( $sw['css'] ) . '"></span>';
			} else {
				$h .= '<span class="wpf4__swatchColor wpf4__swatchColor--text">' . esc_html( mb_substr( $o['label'], 0, 2 ) ) . '</span>';
			}
			$h .= '<span class="wpf4__optText wpf4__sr">' . esc_html( $o['label'] ) . ( $showCount ? ' (' . (int) $o['count'] . ')' : '' ) . '</span>';
			$h .= '<span class="wpf4__tip" aria-hidden="true">' . esc_html( $o['label'] ) . '</span>';
		} else {
			$h .= '<span class="wpf4__optText">' . esc_html( $o['label'] ) . '</span>';
			if ( $showCount ) {
				$h .= '<span class="wpf4__count" data-count>' . (int) $o['count'] . '</span>';
			}
		}
		return $h . '</label>';
	}

	/**
	 * Colour or picture of a term (manual term meta, then automatic detection) via the swatches module.
	 */
	private function swatch_for( $term ) {
		if ( class_exists( 'WooBeWoo_PF_Swatches' ) ) {
			return WooBeWoo_PF_Swatches::swatch( $term );
		}
		return array( 'type' => 'none' );
	}

	private function stock_options( $b, $display, $mobile ) {
		$s      = $b['block']['settings'];
		$list   = ! empty( $s['f_options[]'] ) ? explode( ',', (string) $s['f_options[]'] ) : array( 'instock', 'outofstock', 'onbackorder' );
		$map    = array( 'instock' => 'in', 'outofstock' => 'out', 'onbackorder' => 'backorder' );
		$labels = array(
			'in'        => ! empty( $s['f_stock_statuses[in]'] ) ? $s['f_stock_statuses[in]'] : __( 'In stock', 'woo-product-filter' ),
			'out'       => ! empty( $s['f_stock_statuses[out]'] ) ? $s['f_stock_statuses[out]'] : __( 'Sold out', 'woo-product-filter' ),
			'backorder' => ! empty( $s['f_stock_statuses[on]'] ) ? $s['f_stock_statuses[on]'] : __( 'On backorder', 'woo-product-filter' ),
		);
		$counts = isset( $this->facets['stock']['counts'] ) ? $this->facets['stock']['counts'] : array();
		if ( 'toggle' === $display ) {
			$on = 'in' === $this->query->stock;
			$id = $this->fid( 'wpf_stock', $mobile );
			return '<label class="wpf4__toggle" for="' . esc_attr( $id ) . '"><span class="wpf4__toggleText">' . esc_html( ! empty( $s['f_stock_statuses[in]'] ) ? $s['f_stock_statuses[in]'] : __( 'In stock only', 'woo-product-filter' ) ) . '</span><input type="checkbox" role="switch" id="' . esc_attr( $id ) . '" name="wpf_stock" value="in"' . ( $on ? ' checked' : '' ) . ' aria-checked="' . ( $on ? 'true' : 'false' ) . '"' . ( $mobile ? ' disabled' : '' ) . ' data-wpf4-control data-label="' . esc_attr( $labels['in'] ) . '"><span class="wpf4__switch" aria-hidden="true"></span></label>';
		}
		$opts = array();
		foreach ( $list as $k ) {
			$k = trim( $k );
			if ( ! isset( $map[ $k ] ) ) {
				continue;
			}
			$v      = $map[ $k ];
			$opts[] = array(
				'value'   => $v,
				'label'   => $labels[ $v ],
				'count'   => isset( $counts[ $v ] ) ? (int) $counts[ $v ] : 0,
				'checked' => $this->query->stock === $v,
				'depth'   => 0,
			);
		}
		return $this->render_options( $b, $display, 'wpf_stock', $opts, WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_show_count' ), $mobile );
	}

	private function toggle( $b, $mobile ) {
		$key   = $b['key'];
		$on    = 'sale' === $key ? $this->query->sale : $this->query->featured;
		$label = WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_checkbox_label' );
		if ( '' === $label ) {
			$label = 'sale' === $key ? __( 'On sale only', 'woo-product-filter' ) : __( 'Featured only', 'woo-product-filter' );
		}
		$counts = isset( $this->facets[ $key ]['counts']['1'] ) ? (int) $this->facets[ $key ]['counts']['1'] : null;
		$id     = $this->fid( 'wpf_' . $key, $mobile );
		$h      = '<label class="wpf4__toggle" for="' . esc_attr( $id ) . '"><span class="wpf4__toggleText">' . esc_html( $label );
		if ( null !== $counts && WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_show_count' ) ) {
			$h .= ' <span class="wpf4__count" data-count>' . $counts . '</span>';
		}
		$h .= '</span><input type="checkbox" role="switch" id="' . esc_attr( $id ) . '" name="wpf_' . esc_attr( $key ) . '" value="1"' . ( $on ? ' checked' : '' ) . ' aria-checked="' . ( $on ? 'true' : 'false' ) . '"' . ( $mobile ? ' disabled' : '' ) . ' data-wpf4-control data-label="' . esc_attr( $label ) . '"><span class="wpf4__switch" aria-hidden="true"></span></label>';
		return $h;
	}

	private function rating_options( $b, $display, $mobile ) {
		$counts = isset( $this->facets['rating']['counts'] ) ? $this->facets['rating']['counts'] : array();
		$opts   = array();
		for ( $i = 5; $i >= 1; $i-- ) {
			$opts[] = array(
				'value'   => (string) $i,
				/* translators: %d: number of stars */
				'label'   => 5 === $i ? __( '5 stars', 'woo-product-filter' ) : sprintf( __( '%d stars and up', 'woo-product-filter' ), $i ),
				'count'   => isset( $counts[ (string) $i ] ) ? (int) $counts[ (string) $i ] : 0,
				'checked' => (int) $this->query->rating === $i,
				'depth'   => 0,
			);
		}
		return $this->render_options( $b, $display, 'wpf_rating', $opts, WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_show_count' ), $mobile );
	}

	/**
	 * One short row of five equal boxes: "1+ ★" … "4+ ★", "5 ★" (1 and up … 5 stars), like a segmented control,
	 * so the block takes one line even in a narrow sidebar. A radio group; the product count of each box is in its
	 * tooltip and screen reader text; "Clear" shows only once something is picked.
	 *
	 * @since 4.0.0 compact boxes (4.0: five-star rows with "& up").
	 */
	private function rating_pills( $b, $mobile ) {
		$counts    = isset( $this->facets['rating']['counts'] ) ? $this->facets['rating']['counts'] : array();
		$showCount = WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_show_count' );
		$hideEmpty = WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_hide_empty' );
		$cur       = (int) $this->query->rating;
		$dis       = $mobile ? ' disabled' : '';
		$name      = 'wpf_rating';
		$h         = '<div class="wpf4__stars wpf4__stars--compact" role="radiogroup" aria-label="' . esc_attr( $b['title'] ) . '">';
		for ( $i = 1; $i <= 5; $i++ ) {
			$id    = $this->fid( $name . '_' . $i, $mobile );
			$c     = isset( $counts[ (string) $i ] ) ? (int) $counts[ (string) $i ] : 0;
			$on    = $cur === $i;
			$empty = 0 === $c && ! $on;
			/* translators: %d: number of stars */
			$label = 5 === $i ? __( '5 stars', 'woo-product-filter' ) : sprintf( __( '%d stars and up', 'woo-product-filter' ), $i );
			/* translators: %s: number of products */
			$tip   = $label . ( $showCount ? ' · ' . sprintf( _n( '%s product', '%s products', $c, 'woo-product-filter' ), number_format_i18n( $c ) ) : '' );
			$cls   = 'wpf4__opt wpf4__star' . ( $on ? ' is-checked' : '' ) . ( $empty ? ' is-empty' : '' );
			$h    .= '<label class="' . esc_attr( $cls ) . '" for="' . esc_attr( $id ) . '" title="' . esc_attr( $tip ) . '"' . ( $empty && $hideEmpty ? ' hidden' : '' ) . '>';
			$h    .= '<input type="radio" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . $i . '"' . ( $on ? ' checked' : '' ) . $dis . ' data-wpf4-control data-label="' . esc_attr( $label ) . '" data-count="' . $c . '">';
			$h    .= '<span class="wpf4__sr">' . esc_html( $tip ) . '</span>';
			$h    .= '<span class="wpf4__starNum" aria-hidden="true">' . $i . ( $i < 5 ? '+' : '' ) . '</span>' . $this->star( true );
			$h    .= '</label>';
		}
		$h .= '</div>';
		$h .= '<button type="button" class="wpf4__starClear" data-wpf4-star-clear' . ( $cur ? '' : ' hidden' ) . $dis . '>' . esc_html__( 'Clear', 'woo-product-filter' ) . '</button>';
		return $h;
	}

	/**
	 * One star on the 24 grid, 1.8 stroke outline; filled for the stars that count.
	 */
	private function star( $filled ) {
		return '<svg class="wpf4__starIcon' . ( $filled ? ' is-on' : '' ) . '" aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" fill="' . ( $filled ? 'currentColor' : 'none' ) . '" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3.4 2.6 5.4 5.9.8-4.3 4.2 1 5.9-5.2-2.8-5.2 2.8 1-5.9-4.3-4.2 5.9-.8z"/></svg>';
	}

	private function sort_options( $b, $display, $mobile ) {
		$model  = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' );
		$labels = $model->getFilterLabels( 'SortBy' );
		$s      = $b['block']['settings'];
		$keys   = ! empty( $s['f_options[]'] ) ? array_map( 'trim', explode( ',', (string) $s['f_options[]'] ) ) : array_keys( $labels );
		$cur    = $this->query->sort ? $this->query->sort : 'default';
		$id     = $this->fid( 'wpf_sort', $mobile );
		if ( 'dropdown' === $display ) {
			$h  = '<label class="wpf4__sr" for="' . esc_attr( $id ) . '">' . esc_html( $b['title'] ) . '</label><select class="wpf4__select" id="' . esc_attr( $id ) . '" name="wpf_sort"' . ( $mobile ? ' disabled' : '' ) . ' data-wpf4-control>';
			foreach ( $keys as $k ) {
				if ( ! isset( $labels[ $k ] ) ) {
					continue;
				}
				$h .= '<option value="' . esc_attr( 'default' === $k ? '' : $k ) . '"' . ( $cur === $k ? ' selected' : '' ) . ' data-label="' . esc_attr( $labels[ $k ] ) . '">' . esc_html( $labels[ $k ] ) . '</option>';
			}
			return $h . '</select>';
		}
		$opts = array();
		foreach ( $keys as $k ) {
			if ( ! isset( $labels[ $k ] ) ) {
				continue;
			}
			$opts[] = array( 'value' => 'default' === $k ? '' : $k, 'label' => $labels[ $k ], 'count' => 1, 'checked' => $cur === $k, 'depth' => 0 );
		}
		return $this->render_options( $b, $display, 'wpf_sort', $opts, false, $mobile );
	}

	/**
	 * The text box of a search block. Public for registered block renderers.
	 *
	 * @since 4.0.0 public
	 */
	public function search_box( $b, $mobile ) {
		// The main box is wpf_q; a custom field text box or a second box has its own key (wpf_cf_<key>, wpf_q2).
		$name  = 'q' === $b['kind'] ? 'wpf_q' : 'wpf_' . $b['key'];
		$value = 'q' === $b['kind'] ? $this->query->q : ( isset( $this->query->text[ $b['key'] ] ) ? $this->query->text[ $b['key'] ] : '' );
		$id    = $this->fid( $name, $mobile );
		return '<label class="wpf4__sr" for="' . esc_attr( $id ) . '">' . esc_html( $b['title'] ) . '</label><input type="search" class="wpf4__text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" placeholder="' . esc_attr( WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_search_label', __( 'Search products', 'woo-product-filter' ) ) ) . '"' . ( $mobile ? ' disabled' : '' ) . ' data-wpf4-control data-debounce="400">';
	}

	/**
	 * Two native range inputs plus number inputs with visible values. Public for registered block renderers (@since 4.0.0).
	 */
	public function slider( $b, $key, $mobile ) {
		$facet = isset( $this->facets[ $b['key'] ] ) ? $this->facets[ $b['key'] ] : array( 'min' => 0, 'max' => 0 );
		$min   = (float) $facet['min'];
		$max   = (float) $facet['max'];
		$cur   = 'price' === $key ? $this->query->price : ( isset( $this->query->meta[ $key ] ) ? $this->query->meta[ $key ] : null );
		$curMin = ( is_array( $cur ) && null !== $cur[0] ) ? (float) $cur[0] : $min;
		$curMax = ( is_array( $cur ) && null !== $cur[1] ) ? (float) $cur[1] : $max;
		$lo    = min( $min, $curMin );
		$hi    = max( $max, $curMax );
		$step  = (float) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_step' );
		$step  = $step > 0 ? $step : 1;
		// 4.0.1: ends on the step grid, else the browser snaps the top handle below the dearest product (2079 -> 2075 with step 5).
		if ( $step > 0 && $hi > $lo ) {
			$lo = round( floor( $lo / $step ) * $step, 6 );
			$hi = round( ceil( $hi / $step ) * $step, 6 );
			if ( ! ( is_array( $cur ) && null !== $cur[0] ) ) {
				$curMin = $lo;
			}
			if ( ! ( is_array( $cur ) && null !== $cur[1] ) ) {
				$curMax = $hi;
			}
		}
		$isPrice = 'price' === $key;
		$fmt   = function ( $v ) use ( $isPrice ) {
			return $isPrice ? WooBeWoo_PF_Frontend4::plain_price( $v ) : WooBeWoo_PF_Query::trim_number( $v );
		};
		$dis   = $mobile ? ' disabled' : '';
		$idMin = $this->fid( 'wpf_' . $key . '_min', $mobile );
		$idMax = $this->fid( 'wpf_' . $key . '_max', $mobile );
		$pMin  = $hi > $lo ? ( $curMin - $lo ) / ( $hi - $lo ) * 100 : 0;
		$pMax  = $hi > $lo ? ( $curMax - $lo ) / ( $hi - $lo ) * 100 : 100;
		$h  = '';
		// 4.0.0: bars over the slider show where the prices sit (products per price bucket), the picked part in colour.
		if ( $isPrice && ! empty( $facet['hist'] ) && is_array( $facet['hist'] ) && WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_price_bars' ) ) {
			$h .= $this->price_bars( $facet['hist'], (float) $facet['min'], (float) $facet['max'], $curMin, $curMax );
		}
		$h .= '<div class="wpf4__slider" data-wpf4-slider data-key="' . esc_attr( $key ) . '" data-min="' . esc_attr( $lo ) . '" data-max="' . esc_attr( $hi ) . '" data-step="' . esc_attr( $step ) . '" data-price="' . ( $isPrice ? '1' : '0' ) . '">';
		$h .= '<div class="wpf4__sliderTrack" aria-hidden="true"><div class="wpf4__sliderFill" style="left:' . esc_attr( round( $pMin, 2 ) ) . '%;right:' . esc_attr( round( 100 - $pMax, 2 ) ) . '%"></div></div>';
		/* translators: %s: filter block title, lower case */
		$h .= '<input type="range" class="wpf4__range wpf4__range--min" id="' . esc_attr( $idMin ) . '-r" min="' . esc_attr( $lo ) . '" max="' . esc_attr( $hi ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $curMin ) . '" aria-label="' . esc_attr( sprintf( __( 'Minimum %s', 'woo-product-filter' ), mb_strtolower( $b['title'] ) ) ) . '" aria-valuetext="' . esc_attr( $fmt( $curMin ) ) . '"' . $dis . ' data-role="min">';
		/* translators: %s: filter block title, lower case */
		$h .= '<input type="range" class="wpf4__range wpf4__range--max" id="' . esc_attr( $idMax ) . '-r" min="' . esc_attr( $lo ) . '" max="' . esc_attr( $hi ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $curMax ) . '" aria-label="' . esc_attr( sprintf( __( 'Maximum %s', 'woo-product-filter' ), mb_strtolower( $b['title'] ) ) ) . '" aria-valuetext="' . esc_attr( $fmt( $curMax ) ) . '"' . $dis . ' data-role="max">';
		$h .= '</div>';
		$h .= '<div class="wpf4__sliderValues">';
		$h .= '<label class="wpf4__num"><span class="wpf4__numLabel">' . esc_html__( 'Min', 'woo-product-filter' ) . '</span>' . ( $isPrice ? '<span class="wpf4__cur" aria-hidden="true">' . esc_html( html_entity_decode( get_woocommerce_currency_symbol() ) ) . '</span>' : '' ) . '<input type="number" id="' . esc_attr( $idMin ) . '" name="wpf_' . esc_attr( $key ) . '_min" min="' . esc_attr( $lo ) . '" max="' . esc_attr( $hi ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( WooBeWoo_PF_Query::trim_number( $curMin ) ) . '" inputmode="decimal"' . $dis . ' data-role="min" data-wpf4-control data-range="1"></label>';
		/* translators: 1: minimum value, 2: maximum value */
		$h .= '<output class="wpf4__sliderOut" for="' . esc_attr( $idMin ) . '-r ' . esc_attr( $idMax ) . '-r" data-wpf4-slider-out>' . esc_html( sprintf( __( '%1$s to %2$s', 'woo-product-filter' ), $fmt( $curMin ), $fmt( $curMax ) ) ) . '</output>';
		$h .= '<label class="wpf4__num"><span class="wpf4__numLabel">' . esc_html__( 'Max', 'woo-product-filter' ) . '</span>' . ( $isPrice ? '<span class="wpf4__cur" aria-hidden="true">' . esc_html( html_entity_decode( get_woocommerce_currency_symbol() ) ) . '</span>' : '' ) . '<input type="number" id="' . esc_attr( $idMax ) . '" name="wpf_' . esc_attr( $key ) . '_max" min="' . esc_attr( $lo ) . '" max="' . esc_attr( $hi ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( WooBeWoo_PF_Query::trim_number( $curMax ) ) . '" inputmode="decimal"' . $dis . ' data-role="max" data-wpf4-control data-range="1"></label>';
		$h .= '</div>';
		$h .= '<div class="wpf4__sliderBounds" aria-hidden="true"><span>' . esc_html( $fmt( $lo ) ) . '</span><span>' . esc_html( $fmt( $hi ) ) . '</span></div>';
		return $h;
	}

	/**
	 * The bar chart above the price slider: one bar per price bucket, as tall as its share of the busiest bucket.
	 * The script recolours the bars as the handles move and redraws them when other picks change the counts.
	 *
	 * @since 4.0.0
	 */
	private function price_bars( $hist, $min, $max, $curMin, $curMax ) {
		$top = max( 1, max( $hist ) );
		$n   = count( $hist );
		$w   = $n > 0 ? ( $max - $min ) / $n : 0;
		$h   = '<div class="wpf4__hist" data-wpf4-hist data-min="' . esc_attr( $min ) . '" data-max="' . esc_attr( $max ) . '" aria-hidden="true">';
		foreach ( array_values( $hist ) as $i => $c ) {
			$mid = $min + ( $i + 0.5 ) * $w;
			$in  = $mid >= $curMin && $mid <= $curMax;
			$h  .= '<span class="wpf4__histBar' . ( $in ? ' is-in' : '' ) . '" style="height:' . esc_attr( $c > 0 ? max( 6, round( (int) $c / $top * 100 ) ) : 0 ) . '%"></span>';
		}
		return $h . '</div>';
	}

	/**
	 * Price ranges as a radio list (configured "min-max" lines, or four even steps). Public for registered block renderers (@since 4.0.0).
	 */
	public function range_list( $b, $mobile, $display = 'list' ) {
		$raw    = (string) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_range_by_hands_values' );
		$facet  = isset( $this->facets['price'] ) ? $this->facets['price'] : array( 'min' => 0, 'max' => 0 );
		$ranges = array();
		foreach ( preg_split( '/[\r\n;]+/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$r = WooBeWoo_PF_Query::range( $line );
			if ( null !== $r[0] || null !== $r[1] ) {
				$ranges[] = $r;
			}
		}
		if ( ! $ranges && $facet['max'] > $facet['min'] ) {
			$step = ( $facet['max'] - $facet['min'] ) / 4;
			$a    = (float) $facet['min'];
			for ( $i = 0; $i < 4; $i++ ) {
				$to       = 3 === $i ? null : round( $a + $step, 0 );
				$ranges[] = array( 0 === $i ? null : round( $a, 0 ), $to );
				$a       += $step;
			}
		}
		$cur  = is_array( $this->query->price ) ? WooBeWoo_PF_Query::format_range( $this->query->price ) : '';
		$opts = array();
		foreach ( $ranges as $r ) {
			$v      = WooBeWoo_PF_Query::format_range( $r );
			$opts[] = array(
				'value'   => $v,
				'label'   => WooBeWoo_PF_Frontend4::price_label( $r ),
				'count'   => $this->engine->count_price_range( $r[0], $r[1] ),
				'checked' => $cur === $v,
				'depth'   => 0,
			);
		}
		$b['single'] = true;
		return $this->render_options( $b, $display, 'wpf_price', $opts, WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_show_count' ), $mobile );
	}

	/* ------------------------------------------------------------------ drawer and finder shells */

	private function drawer() {
		$text = WooBeWoo_PF_Frontend4::setting( $this->settings, 'mobile_drawer_text' );
		$id   = $this->uid . '-drawer';
		$h  = '<button type="button" class="wpf4-drawerBtn" data-wpf4-drawer-open aria-haspopup="dialog" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '" hidden>' . $this->icon( 'filter' ) . '<span>' . esc_html( $text ) . '</span><span class="wpf4-drawerBtn__n" data-wpf4-drawer-n></span></button>';
		$h .= '<div class="wpf4-scrim" data-wpf4-scrim hidden></div>';
		$h .= '<div class="wpf4-drawer" id="' . esc_attr( $id ) . '" role="dialog" aria-modal="true" aria-label="' . esc_attr( $text ) . '" hidden data-wpf4-drawer>';
		$h .= '<div class="wpf4-drawer__handle" aria-hidden="true"></div>';
		$h .= '<div class="wpf4-drawer__head"><h2 class="wpf4-drawer__title">' . esc_html( $text ) . '</h2><div class="wpf4-drawer__actions">';
		$h .= '<button type="button" class="wpf4-drawer__clear" data-wpf4-clear>' . esc_html( WooBeWoo_PF_Frontend4::setting( $this->settings, 'show_clean_button_word' ) ) . '</button>';
		$h .= '<button type="button" class="wpf4-drawer__close" data-wpf4-drawer-close aria-label="' . esc_attr__( 'Close filters', 'woo-product-filter' ) . '">' . $this->icon( 'x' ) . '</button>';
		$h .= '</div></div>';
		$h .= '<button type="button" class="wpf4-drawer__remember" data-wpf4-remember hidden aria-label="' . esc_attr__( 'Use your last filters again', 'woo-product-filter' ) . '">' . $this->icon( 'refresh' ) . '<span data-wpf4-remember-text></span></button>';
		$h .= '<div class="wpf4-drawer__body" data-wpf4-drawer-body></div>';
		$h .= '<button type="button" class="wpf4-drawer__show" data-wpf4-drawer-show>' . esc_html__( 'Show results', 'woo-product-filter' ) . '</button>';
		$h .= '<p class="wpf4-drawer__live" aria-live="polite" data-wpf4-drawer-live></p>';
		$h .= '</div>';
		return $h;
	}

	private function finder( $finder ) {
		$id    = $this->uid . '-finder';
		/* translators: %s: filter title or site name */
		$title = ! empty( $finder['title'] ) ? $finder['title'] : sprintf( __( '%s finder', 'woo-product-filter' ), $this->ctx['title'] ? $this->ctx['title'] : get_bloginfo( 'name' ) );
		$h  = '<div class="wpf4-finder" id="' . esc_attr( $id ) . '" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $id ) . '-title" hidden data-wpf4-finder>';
		$h .= '<header class="wpf4-finder__head"><span class="wpf4-finder__name" id="' . esc_attr( $id ) . '-title">' . esc_html( $title ) . '</span><button type="button" class="wpf4-finder__skip" data-wpf4-finder-skip>' . esc_html__( 'Skip to all products', 'woo-product-filter' ) . '</button></header>';
		$h .= '<nav class="wpf4-finder__steps" aria-label="' . esc_attr__( 'Finder steps', 'woo-product-filter' ) . '" data-wpf4-finder-steps></nav>';
		$h .= '<div class="wpf4-finder__answers" data-wpf4-finder-answers role="group" aria-label="' . esc_attr__( 'Your answers', 'woo-product-filter' ) . '"></div>';
		$h .= '<div class="wpf4-finder__q"><p class="wpf4-finder__kicker" data-wpf4-finder-kicker></p><h2 class="wpf4-finder__question" data-wpf4-finder-question tabindex="-1"></h2><p class="wpf4-finder__help" data-wpf4-finder-help></p></div>';
		$h .= '<div class="wpf4-finder__options" data-wpf4-finder-options role="group" aria-label="' . esc_attr__( 'Answers', 'woo-product-filter' ) . '"></div>';
		$h .= '<div class="wpf4-finder__foot"><button type="button" class="wpf4-finder__next" data-wpf4-finder-next>' . esc_html__( 'Next', 'woo-product-filter' ) . '</button><button type="button" class="wpf4-finder__back" data-wpf4-finder-back>' . esc_html__( 'Back', 'woo-product-filter' ) . '</button>';
		$h .= '<p class="wpf4-finder__note">' . esc_html( apply_filters( 'woobewoo_pf_finder_note', __( 'Free in 4.0. Year, make and model with "my garage" is Pro.', 'woo-product-filter' ) ) ) . '</p></div>';
		$h .= '</div>';
		return $h;
	}

	/* ------------------------------------------------------------------ helpers */

	/**
	 * A unique input id inside this filter instance (suffix -m for the phone variant). Public for registered block renderers.
	 *
	 * @since 4.0.0 public
	 */
	public function fid( $name, $mobile ) {
		return $this->uid . '-' . sanitize_html_class( str_replace( array( '[', ']' ), '', $name ) ) . ( $mobile ? '-m' : '' );
	}

	/**
	 * Inline SVG icons on the 24 grid, 1.8 stroke, currentColor.
	 */
	public function icon( $name ) {
		$paths = array(
			'chevron' => '<path d="m6 9 6 6 6-6"/>',
			'x'       => '<path d="M18 6 6 18M6 6l12 12"/>',
			'filter'  => '<path d="M4 6h16M7 12h10M10 18h4"/>',
			'refresh' => '<path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/>',
			'sparkle' => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M5.6 5.6l2.8 2.8M15.6 15.6l2.8 2.8M5.6 18.4l2.8-2.8M15.6 8.4l2.8-2.8"/>',
			'check'   => '<path d="M20 6 9 17l-5-5"/>',
		);
		$p = isset( $paths[ $name ] ) ? $paths[ $name ] : $paths['check'];
		return '<svg class="wpf4__icon" aria-hidden="true" focusable="false" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $p . '</svg>';
	}
}
