<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Measures_Date Class
 *
 * "New arrivals" block (wpfDate): "Added in the last 7 / 14 / 30 / 90 days" (plus a custom number of days) as a
 * radio list, chips or a dropdown, with a count per choice from wpf_index_products.created. URL key wpf_added
 * with the number of days (wpf_added=30). Sorting by newest is the Sort by block's job, not this one's.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Measures_Date {

	const ID   = 'wpfDate';
	const KIND = 'added';
	const KEY  = 'added';

	/** The built-in choices, in days. */
	const DAYS = array( 7, 14, 30, 90 );

	/** The longest custom span accepted (ten years). */
	const MAX_DAYS = 3650;

	/**
	 * Register the block with the registry.
	 */
	public static function register() {
		WooBeWoo_PF_Blocks::register(
			self::ID,
			array(
				'kind'         => self::KIND,
				'label'        => __( 'New arrivals', 'woo-product-filter' ),
				'slug'         => 'new_arrivals',
				'unique'       => true,
				'content_type' => 'meta',
				'card'         => array(
					'icon'  => '<svg viewBox="0 0 28 20" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="22" height="14" rx="2.5"/><path d="M3 9h22M8 2v4M20 2v4"/><path d="M14 11l1.2 2.4 2.6.4-1.9 1.8.5 2.6-2.4-1.3-2.4 1.3.5-2.6-1.9-1.8 2.6-.4z"/></svg>',
					'group' => 'product',
					'spec'  => __( 'Added in the last 7, 14, 30 or 90 days', 'woo-product-filter' ),
				),
				'displays'     => array( 'list', 'chips', 'dropdown' ),
				'controls'     => self::controls(),
				'defaults'     => array(
					'f_title'                      => __( 'New arrivals', 'woo-product-filter' ),
					'f_days_7'                     => '1',
					'f_days_14'                    => '1',
					'f_days_30'                    => '1',
					'f_days_90'                    => '1',
					'f_custom_days'                => '0',
					'f_show_count'                 => '1',
					'f_dropdown_first_option_text' => __( 'Any date', 'woo-product-filter' ),
				),
				'question'     => __( 'How new should it be?', 'woo-product-filter' ),
				'finder'       => true,
				'single'       => true,
				'key'          => self::KEY,
				'parse'        => array( __CLASS__, 'parse' ),
				'param'        => array( __CLASS__, 'param' ),
				'sql'          => array( __CLASS__, 'sql' ),
				'facets'       => array( __CLASS__, 'facets' ),
				'render'       => array( __CLASS__, 'render' ),
				'chip'         => array( __CLASS__, 'chip' ),
				'cli'          => 'new',
			)
		);
	}

	private static function controls() {
		$controls = array();
		foreach ( self::DAYS as $d ) {
			$controls[] = array(
				'key'     => 'f_days_' . $d,
				'type'    => 'switch',
				/* translators: %d: number of days */
				'label'   => sprintf( __( 'Offer "the last %d days"', 'woo-product-filter' ), $d ),
				'default' => '1',
			);
		}
		$controls[] = array(
			'key'     => 'f_custom_days',
			'type'    => 'number',
			'label'   => __( 'Custom days', 'woo-product-filter' ),
			'help'    => __( 'One more choice with your own number of days. 0: none.', 'woo-product-filter' ),
			'default' => '0',
		);
		$controls[] = array(
			'key'     => 'f_option_text',
			'type'    => 'text',
			'group'   => 'look',
			'label'   => __( 'Each choice says', 'woo-product-filter' ),
			'help'    => __( '%d becomes the number of days.', 'woo-product-filter' ),
			'default' => self::default_text(),
		);
		$controls[] = array(
			'key'     => 'f_dropdown_first_option_text',
			'type'    => 'text',
			'group'   => 'look',
			'label'   => __( 'The "no date" choice says', 'woo-product-filter' ),
			'default' => __( 'Any date', 'woo-product-filter' ),
		);
		$controls[] = array(
			'key'   => 'f_show_count',
			'type'  => 'switch',
			'group' => 'look',
			'label' => __( 'Show how many products each choice has', 'woo-product-filter' ),
		);
		return $controls;
	}

	/* ------------------------------------------------------------------ settings */

	/**
	 * The choices of a block, in days, ascending and unique.
	 */
	public static function days( $block ) {
		$s   = isset( $block['settings'] ) && is_array( $block['settings'] ) ? $block['settings'] : array();
		$out = array();
		foreach ( self::DAYS as $d ) {
			$k = 'f_days_' . $d;
			if ( ! array_key_exists( $k, $s ) || WooBeWoo_PF_Frontend4::block_setting( $block, $k ) ) {
				$out[] = $d;
			}
		}
		$custom = isset( $s['f_custom_days'] ) ? (int) $s['f_custom_days'] : 0;
		if ( $custom > 0 && $custom <= self::MAX_DAYS ) {
			$out[] = $custom;
		}
		$out = array_values( array_unique( $out ) );
		sort( $out );
		return $out;
	}

	private static function default_text() {
		/* translators: %d: number of days */
		return __( 'Added in the last %d days', 'woo-product-filter' );
	}

	/**
	 * The label of a choice: the block's own pattern (with %d), else the default.
	 */
	public static function text( $days, $block = null ) {
		$pattern = $block && isset( $block['settings']['f_option_text'] ) ? trim( (string) $block['settings']['f_option_text'] ) : '';
		if ( '' === $pattern ) {
			$pattern = self::default_text();
		}
		if ( false === strpos( $pattern, '%d' ) && false === strpos( $pattern, '%s' ) ) {
			$pattern .= ' %d';
		}
		return sprintf( str_replace( '%s', '%d', $pattern ), (int) $days ); // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText -- the pattern is a block setting with the default text as its fallback
	}

	/**
	 * The local date and time N days ago, in the index's created format.
	 */
	public static function since( $days ) {
		return wp_date( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS );
	}

	/* ------------------------------------------------------------------ query contract */

	/**
	 * wpf_added=30: a whole number of days, 1 to MAX_DAYS, else nothing.
	 */
	public static function parse( $raw, $query, $key ) {
		if ( is_array( $raw ) ) {
			$raw = isset( $raw[0] ) ? $raw[0] : '';
		}
		$d = (int) trim( (string) $raw );
		if ( $d >= 1 && $d <= self::MAX_DAYS ) {
			$query->extra[ $key ] = (string) $d;
		} else {
			unset( $query->extra[ $key ] );
		}
	}

	public static function param( $value ) {
		$d = (int) ( is_array( $value ) ? reset( $value ) : $value );
		return $d >= 1 && $d <= self::MAX_DAYS ? (string) $d : '';
	}

	private static function picked( $query, $key ) {
		$d = isset( $query->extra[ $key ] ) ? (int) $query->extra[ $key ] : 0;
		return ( $d >= 1 && $d <= self::MAX_DAYS ) ? $d : 0;
	}

	/* ------------------------------------------------------------------ engine */

	/**
	 * Products created in the last N days: the date of the product itself (the root row), so a variation added
	 * later to an old product does not make it new.
	 */
	public static function sql( $b, $query, $engine ) {
		global $wpdb;
		$d = self::picked( $query, $b['key'] );
		if ( ! $d ) {
			return null;
		}
		return array(
			'joins' => array( $wpdb->prepare( "INNER JOIN {$engine->t['products']} mdr ON mdr.product_id = l.root_id AND mdr.created >= %s", self::since( $d ) ) ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- our own index table name
		);
	}

	/**
	 * A count per choice over the narrowed set, in one query (COUNT(CASE WHEN created >= ...)). Whole catalogue
	 * counts are cached per day with the index version.
	 */
	public static function facets( $blocks, $engine, $skipKey ) {
		global $wpdb;
		$out = array();
		foreach ( $blocks as $k => $b ) {
			$days   = self::days( $b['block'] );
			$counts = array_fill_keys( array_map( 'strval', $days ), 0 );
			$labels = array();
			foreach ( $days as $d ) {
				$labels[ (string) $d ] = self::text( $d, $b['block'] );
			}
			$set = $engine->set_ids( $skipKey );
			if ( $days && ! ( is_array( $set ) && ! $set ) ) {
				$plainKey = null === $set ? $engine->plain_key( 'measures_added', array( $days, wp_date( 'Y-m-d' ) ) ) : null;
				$row      = $plainKey ? WooBeWoo_PF_Engine::cache_get( $plainKey ) : false;
				if ( ! is_array( $row ) ) {
					$cols = array();
					foreach ( $days as $i => $d ) {
						$cols[] = $wpdb->prepare( "COUNT(CASE WHEN p.created >= %s THEN 1 END) AS c$i", self::since( $d ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $i is a loop index
					}
					$where = null === $set ? 'p.parent_id = 0 AND p.published = 1' : 'p.product_id IN (' . implode( ',', array_map( 'intval', $set ) ) . ')';
					$row   = $wpdb->get_row( 'SELECT ' . implode( ', ', $cols ) . " FROM {$engine->t['products']} p WHERE $where", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- our own index table; the columns are prepared fragments and the id list is cast
					if ( $plainKey && is_array( $row ) ) {
						WooBeWoo_PF_Engine::cache_set( $plainKey, $row );
					}
				}
				foreach ( $days as $i => $d ) {
					$counts[ (string) $d ] = ( is_array( $row ) && isset( $row[ "c$i" ] ) ) ? (int) $row[ "c$i" ] : 0;
				}
			}
			$out[ $k ] = array(
				'kind'   => self::KIND,
				'counts' => $counts,
				'labels' => $labels,
			);
		}
		return $out;
	}

	/* ------------------------------------------------------------------ storefront */

	public static function render( $b, $display, $mobile, $view ) {
		$key    = $b['key'];
		$days   = self::days( $b['block'] );
		if ( ! $days ) {
			return '';
		}
		$cur    = self::picked( $view->query, $key );
		$counts = isset( $view->facets[ $key ]['counts'] ) ? $view->facets[ $key ]['counts'] : array();
		$opts   = array();
		foreach ( $days as $d ) {
			$opts[] = array(
				'value'   => (string) $d,
				'label'   => self::text( $d, $b['block'] ),
				'count'   => isset( $counts[ (string) $d ] ) ? (int) $counts[ (string) $d ] : 0,
				'checked' => $cur === $d,
				'depth'   => 0,
			);
		}
		$b['single'] = true;
		if ( '' === trim( (string) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_dropdown_first_option_text' ) ) ) {
			$b['block']['settings']['f_dropdown_first_option_text'] = __( 'Any date', 'woo-product-filter' );
		}
		$display = in_array( $display, array( 'list', 'chips', 'dropdown' ), true ) ? $display : 'list';
		return $view->render_options( $b, $display, 'wpf_' . $key, $opts, (bool) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_show_count' ), $mobile );
	}

	/**
	 * Chip text: "Added in the last 30 days".
	 */
	public static function chip( $value, $b ) {
		$d = (int) $value;
		if ( $d < 1 ) {
			return (string) $value;
		}
		return self::text( $d, is_array( $b ) && isset( $b['block'] ) ? $b['block'] : null );
	}
}
