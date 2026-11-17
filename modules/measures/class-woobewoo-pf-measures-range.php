<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Measures_Range Class
 *
 * Shared pieces of the measure blocks (size and weight, stock quantity): the "min-max" URL value, typed or
 * automatic range lists, plain labels ("Under 2 kg"), the slider markup with a unit, and the radio range list.
 * Stateless: every method is static and takes the storefront view or the engine it needs.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Measures_Range {

	/** How many even steps an automatic range list has. */
	const AUTO_STEPS = 5;

	/* ------------------------------------------------------------------ URL value */

	/**
	 * A raw URL value ("2-5", "-2", "5-") as array( min, max ), or null when it holds no number.
	 *
	 * @param mixed $raw
	 * @return array|null
	 */
	public static function parse( $raw ) {
		if ( is_array( $raw ) ) {
			$raw = isset( $raw[0] ) ? (string) $raw[0] : '';
		}
		$r = WooBeWoo_PF_Query::range( $raw );
		if ( null === $r[0] && null === $r[1] ) {
			return null;
		}
		if ( null !== $r[0] && null !== $r[1] && $r[0] > $r[1] ) {
			$r = array( $r[1], $r[0] );
		}
		return $r;
	}

	/**
	 * Store a URL value on the query as one "min-max" string (one chip, one param), or drop it.
	 */
	public static function store( $raw, $query, $key ) {
		$r = self::parse( $raw );
		if ( null === $r ) {
			unset( $query->extra[ $key ] );
			return;
		}
		$query->extra[ $key ] = WooBeWoo_PF_Query::format_range( $r );
	}

	/**
	 * The picked range of a key on a query: array( min, max ) or null.
	 */
	public static function picked( $query, $key ) {
		if ( ! $query || ! isset( $query->extra[ $key ] ) ) {
			return null;
		}
		return self::parse( $query->extra[ $key ] );
	}

	/* ------------------------------------------------------------------ steps and ranges */

	/**
	 * A slider step that suits the span when the block has none: whole numbers for wide spans, tenths or
	 * hundredths for narrow ones. Integer measures (stock) always step by one.
	 */
	public static function auto_step( $min, $max, $integer = false ) {
		if ( $integer ) {
			return 1;
		}
		$span = abs( (float) $max - (float) $min );
		if ( $span > 100 ) {
			return 1;
		}
		if ( $span > 10 ) {
			return 0.5;
		}
		if ( $span > 1 ) {
			return 0.1;
		}
		return 0.01;
	}

	/**
	 * Ranges typed in the builder ("0-1,1-5,5-10", one per comma, semicolon or line; "5-" is "over 5", "-2" is
	 * "under 2") or, when nothing is typed, five even steps between the catalogue bounds.
	 *
	 * @return array list of array( min|null, max|null )
	 */
	public static function ranges( $typed, $min, $max, $step ) {
		$out = array();
		foreach ( preg_split( '/[,;\r\n]+/', (string) $typed ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$r = self::parse( $line );
			if ( null !== $r ) {
				$out[] = $r;
			}
		}
		if ( $out ) {
			return $out;
		}
		$min = (float) $min;
		$max = (float) $max;
		if ( $max <= $min ) {
			return array();
		}
		$step     = (float) $step > 0 ? (float) $step : 1;
		$decimals = self::decimals( $step );
		$bounds   = array();
		for ( $i = 1; $i < self::AUTO_STEPS; $i++ ) {
			$v = $min + ( $max - $min ) * $i / self::AUTO_STEPS;
			$v = round( ceil( $v / $step ) * $step, $decimals );
			if ( $v > $min && $v < $max && ! in_array( $v, $bounds, true ) ) {
				$bounds[] = $v;
			}
		}
		if ( ! $bounds ) {
			return array();
		}
		$prev = null;
		foreach ( $bounds as $v ) {
			$out[] = array( $prev, $v );
			$prev  = $v;
		}
		$out[] = array( $prev, null );
		return $out;
	}

	/**
	 * Decimal places a step needs (0.5 -> 1, 0.01 -> 2, 1 -> 0).
	 */
	public static function decimals( $step ) {
		$s = rtrim( rtrim( number_format( (float) $step, 6, '.', '' ), '0' ), '.' );
		$p = strpos( $s, '.' );
		return false === $p ? 0 : strlen( $s ) - $p - 1;
	}

	/* ------------------------------------------------------------------ words */

	/**
	 * A number with its unit: "2 kg" (after) or "kg 2" (before). No unit: the number alone.
	 */
	public static function with_unit( $n, $unit, $position = 'after' ) {
		$n    = WooBeWoo_PF_Query::trim_number( $n );
		$unit = trim( (string) $unit );
		if ( '' === $unit ) {
			return $n;
		}
		return 'before' === $position ? $unit . ' ' . $n : $n . ' ' . $unit;
	}

	/**
	 * Plain words for a range: "Under 2 kg", "2 to 5 kg", "Over 5 kg", "2 kg".
	 */
	public static function label( $r, $unit = '', $position = 'after' ) {
		if ( ! is_array( $r ) ) {
			return '';
		}
		$min = $r[0];
		$max = $r[1];
		if ( null !== $min && null !== $max ) {
			if ( (float) $min === (float) $max ) {
				return self::with_unit( $min, $unit, $position );
			}
			/* translators: 1: lower value, 2: higher value with its unit ("2 to 5 kg") */
			return sprintf( __( '%1$s to %2$s', 'woo-product-filter' ), WooBeWoo_PF_Query::trim_number( $min ), self::with_unit( $max, $unit, $position ) );
		}
		if ( null !== $max ) {
			/* translators: %s: value with its unit ("2 kg") */
			return sprintf( __( 'Under %s', 'woo-product-filter' ), self::with_unit( $max, $unit, $position ) );
		}
		/* translators: %s: value with its unit ("5 kg") */
		return sprintf( __( 'Over %s', 'woo-product-filter' ), self::with_unit( $min, $unit, $position ) );
	}

	/* ------------------------------------------------------------------ SQL pieces */

	/**
	 * "col >= min AND col <= max" for a range, prepared. '' when the range has no bound.
	 */
	public static function sql_between( $col, $r, $integer = false ) {
		global $wpdb;
		$parts = array();
		if ( is_array( $r ) && null !== $r[0] ) {
			$parts[] = $integer ? $wpdb->prepare( "$col >= %d", (int) $r[0] ) : $wpdb->prepare( "$col >= %f", (float) $r[0] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $col is a fixed alias.column string
		}
		if ( is_array( $r ) && null !== $r[1] ) {
			$parts[] = $integer ? $wpdb->prepare( "$col <= %d", (int) $r[1] ) : $wpdb->prepare( "$col <= %f", (float) $r[1] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $col is a fixed alias.column string
		}
		return implode( ' AND ', $parts );
	}

	/**
	 * "COUNT(DISTINCT CASE WHEN <range> THEN l.root_id END) AS c0, ..." columns, one per range, in range order.
	 */
	public static function sql_count_columns( $col, $ranges, $integer = false ) {
		$cols = array();
		foreach ( $ranges as $i => $r ) {
			$cond     = self::sql_between( $col, $r, $integer );
			$cols[]   = 'COUNT(DISTINCT CASE WHEN ' . ( '' === $cond ? '1 = 1' : $cond ) . " THEN l.root_id END) AS c$i";
		}
		return $cols;
	}

	/**
	 * Counts keyed by the range's URL value ("-2", "2-5", "5-") from a row with c0, c1 ... columns.
	 */
	public static function counts_from_row( $row, $ranges ) {
		$counts = array();
		foreach ( $ranges as $i => $r ) {
			$counts[ WooBeWoo_PF_Query::format_range( $r ) ] = ( $row && isset( $row[ "c$i" ] ) ) ? (int) $row[ "c$i" ] : 0;
		}
		return $counts;
	}

	/**
	 * Labels keyed by the range's URL value.
	 */
	public static function labels( $ranges, $unit = '', $position = 'after' ) {
		$labels = array();
		foreach ( $ranges as $r ) {
			$labels[ WooBeWoo_PF_Query::format_range( $r ) ] = self::label( $r, $unit, $position );
		}
		return $labels;
	}

	/* ------------------------------------------------------------------ markup */

	/**
	 * Two-handle slider with number boxes, the same markup the engine's price and number sliders use (so
	 * frontend-4/slider.js drives it and the facet bounds move it), plus the unit next to the numbers.
	 *
	 * @param WooBeWoo_PF_Frontend4_View $view
	 * @param array                      $b        Normalized block.
	 * @param string                     $key      URL key (without wpf_).
	 * @param array|null                 $cur      Picked range.
	 * @param array                      $facet    array( min, max ) over the narrowed set.
	 * @param float                      $step
	 * @param string                     $unit
	 * @param string                     $position 'before' or 'after'.
	 * @param bool                       $mobile
	 * @return string
	 */
	public static function slider( $view, $b, $key, $cur, $facet, $step, $unit, $position, $mobile ) {
		$min    = isset( $facet['min'] ) ? (float) $facet['min'] : 0;
		$max    = isset( $facet['max'] ) ? (float) $facet['max'] : 0;
		$curMin = ( is_array( $cur ) && null !== $cur[0] ) ? (float) $cur[0] : $min;
		$curMax = ( is_array( $cur ) && null !== $cur[1] ) ? (float) $cur[1] : $max;
		$lo     = min( $min, $curMin );
		$hi     = max( $max, $curMax );
		$step   = (float) $step > 0 ? (float) $step : 1;
		// Ends on the step grid, else the browser snaps the top handle below the heaviest product.
		if ( $hi > $lo ) {
			$lo = round( floor( $lo / $step ) * $step, 6 );
			$hi = round( ceil( $hi / $step ) * $step, 6 );
			if ( ! ( is_array( $cur ) && null !== $cur[0] ) ) {
				$curMin = $lo;
			}
			if ( ! ( is_array( $cur ) && null !== $cur[1] ) ) {
				$curMax = $hi;
			}
		}
		$fmt = function ( $v ) use ( $unit, $position ) {
			return self::with_unit( $v, $unit, $position );
		};
		$unitSpan = '' !== trim( (string) $unit ) ? '<span class="wpf4__cur wpf4__unit" aria-hidden="true">' . esc_html( $unit ) . '</span>' : '';
		$dis      = $mobile ? ' disabled' : '';
		$idMin    = $view->fid( 'wpf_' . $key . '_min', $mobile );
		$idMax    = $view->fid( 'wpf_' . $key . '_max', $mobile );
		$pMin     = $hi > $lo ? ( $curMin - $lo ) / ( $hi - $lo ) * 100 : 0;
		$pMax     = $hi > $lo ? ( $curMax - $lo ) / ( $hi - $lo ) * 100 : 100;
		$title    = isset( $b['title'] ) ? (string) $b['title'] : $key;
		$num      = function ( $role, $id, $value, $label ) use ( $lo, $hi, $step, $key, $dis, $unitSpan, $position ) {
			$h  = '<label class="wpf4__num wpf4__num--' . esc_attr( $position ) . '"><span class="wpf4__numLabel">' . esc_html( $label ) . '</span>';
			$h .= 'before' === $position ? $unitSpan : '';
			$h .= '<input type="number" id="' . esc_attr( $id ) . '" name="wpf_' . esc_attr( $key ) . '_' . $role . '" min="' . esc_attr( $lo ) . '" max="' . esc_attr( $hi ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( WooBeWoo_PF_Query::trim_number( $value ) ) . '" inputmode="decimal"' . $dis . ' data-role="' . $role . '" data-wpf4-control data-range="1">';
			$h .= 'before' === $position ? '' : $unitSpan;
			return $h . '</label>';
		};
		$h  = '<div class="wpf4__slider" data-wpf4-slider data-key="' . esc_attr( $key ) . '" data-min="' . esc_attr( $lo ) . '" data-max="' . esc_attr( $hi ) . '" data-step="' . esc_attr( $step ) . '" data-price="0" data-unit="' . esc_attr( $unit ) . '">';
		$h .= '<div class="wpf4__sliderTrack" aria-hidden="true"><div class="wpf4__sliderFill" style="left:' . esc_attr( round( $pMin, 2 ) ) . '%;right:' . esc_attr( round( 100 - $pMax, 2 ) ) . '%"></div></div>';
		/* translators: %s: filter block title, lower case */
		$h .= '<input type="range" class="wpf4__range wpf4__range--min" id="' . esc_attr( $idMin ) . '-r" min="' . esc_attr( $lo ) . '" max="' . esc_attr( $hi ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $curMin ) . '" aria-label="' . esc_attr( sprintf( __( 'Minimum %s', 'woo-product-filter' ), mb_strtolower( $title ) ) ) . '" aria-valuetext="' . esc_attr( $fmt( $curMin ) ) . '"' . $dis . ' data-role="min">';
		/* translators: %s: filter block title, lower case */
		$h .= '<input type="range" class="wpf4__range wpf4__range--max" id="' . esc_attr( $idMax ) . '-r" min="' . esc_attr( $lo ) . '" max="' . esc_attr( $hi ) . '" step="' . esc_attr( $step ) . '" value="' . esc_attr( $curMax ) . '" aria-label="' . esc_attr( sprintf( __( 'Maximum %s', 'woo-product-filter' ), mb_strtolower( $title ) ) ) . '" aria-valuetext="' . esc_attr( $fmt( $curMax ) ) . '"' . $dis . ' data-role="max">';
		$h .= '</div>';
		$h .= '<div class="wpf4__sliderValues">';
		$h .= $num( 'min', $idMin, $curMin, __( 'Min', 'woo-product-filter' ) );
		/* translators: 1: minimum value, 2: maximum value */
		$h .= '<output class="wpf4__sliderOut" for="' . esc_attr( $idMin ) . '-r ' . esc_attr( $idMax ) . '-r" data-wpf4-slider-out>' . esc_html( sprintf( __( '%1$s to %2$s', 'woo-product-filter' ), WooBeWoo_PF_Query::trim_number( $curMin ), $fmt( $curMax ) ) ) . '</output>';
		$h .= $num( 'max', $idMax, $curMax, __( 'Max', 'woo-product-filter' ) );
		$h .= '</div>';
		$h .= '<div class="wpf4__sliderBounds" aria-hidden="true"><span>' . esc_html( $fmt( $lo ) ) . '</span><span>' . esc_html( $fmt( $hi ) ) . '</span></div>';
		return $h;
	}

	/**
	 * Radio list (or single dropdown) of ranges with counts, through the view's own option renderer.
	 *
	 * @param WooBeWoo_PF_Frontend4_View $view
	 * @param array                      $b       Normalized block.
	 * @param string                     $key     URL key (without wpf_).
	 * @param array                      $ranges  list of array( min, max ).
	 * @param array|null                 $cur     Picked range.
	 * @param array                      $counts  URL value => count.
	 * @param string                     $unit
	 * @param string                     $position
	 * @param string                     $display 'list', 'chips' or 'dropdown'.
	 * @param bool                       $mobile
	 * @return string
	 */
	public static function range_list( $view, $b, $key, $ranges, $cur, $counts, $unit, $position, $display, $mobile ) {
		if ( ! $ranges ) {
			return '';
		}
		$curV = is_array( $cur ) ? WooBeWoo_PF_Query::format_range( $cur ) : '';
		$opts = array();
		foreach ( $ranges as $r ) {
			$v      = WooBeWoo_PF_Query::format_range( $r );
			$opts[] = array(
				'value'   => $v,
				'label'   => self::label( $r, $unit, $position ),
				'count'   => isset( $counts[ $v ] ) ? (int) $counts[ $v ] : 0,
				'checked' => $curV === $v,
				'depth'   => 0,
			);
		}
		$b['single'] = true;
		if ( '' === trim( (string) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_dropdown_first_option_text' ) ) ) {
			$b['block']['settings']['f_dropdown_first_option_text'] = __( 'Any', 'woo-product-filter' );
		}
		$display = in_array( $display, array( 'list', 'chips', 'dropdown' ), true ) ? $display : 'list';
		return $view->render_options( $b, $display, 'wpf_' . $key, $opts, (bool) WooBeWoo_PF_Frontend4::block_setting( $b['block'], 'f_show_count' ), $mobile );
	}
}
