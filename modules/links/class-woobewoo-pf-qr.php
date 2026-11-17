<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_QR Class
 *
 * Small QR code encoder (byte mode, error correction level M, versions 1 to 10, automatic mask)
 * that renders SVG. No external calls, no image library. Written for this plugin; MIT licensed:
 *
 * Copyright (c) 2026 woobewoo. Permission is hereby granted, free of charge, to any person
 * obtaining a copy of this software to deal in the software without restriction, subject to the
 * condition that this notice is included in all copies. THE SOFTWARE IS PROVIDED "AS IS".
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_QR {

	/**
	 * Data codewords and EC codewords per block for level M, versions 1..10:
	 * array( total codewords, ec per block, blocks group1, data per block group1, blocks group2, data per block group2 ).
	 */
	private static $tableM = array(
		1  => array( 26, 10, 1, 16, 0, 0 ),
		2  => array( 44, 16, 1, 28, 0, 0 ),
		3  => array( 70, 26, 1, 44, 0, 0 ),
		4  => array( 100, 18, 2, 32, 0, 0 ),
		5  => array( 134, 24, 2, 43, 0, 0 ),
		6  => array( 172, 16, 4, 27, 0, 0 ),
		7  => array( 196, 18, 4, 31, 0, 0 ),
		8  => array( 242, 22, 2, 38, 2, 39 ),
		9  => array( 292, 22, 3, 36, 2, 37 ),
		10 => array( 346, 26, 4, 43, 1, 44 ),
	);

	/** Alignment pattern centre coordinates per version. */
	private static $alignment = array(
		1  => array(),
		2  => array( 6, 18 ),
		3  => array( 6, 22 ),
		4  => array( 6, 26 ),
		5  => array( 6, 30 ),
		6  => array( 6, 34 ),
		7  => array( 6, 22, 38 ),
		8  => array( 6, 24, 42 ),
		9  => array( 6, 26, 46 ),
		10 => array( 6, 28, 52 ),
	);

	private static $exp = null;
	private static $log = null;

	/**
	 * Encode text and return the module matrix (array of rows of 0/1), or null when too long.
	 */
	public static function matrix( $text ) {
		$bytes = array_values( unpack( 'C*', (string) $text ) );
		$len   = count( $bytes );
		$version = 0;
		// Versions 7+ need the version information blocks; short links fit in 1..6 (up to 86 bytes).
		foreach ( array_slice( self::$tableM, 0, 6, true ) as $v => $t ) {
			$dataCw = $t[0] - $t[1] * ( $t[2] + $t[4] );
			$bitsNeeded = 4 + ( $v < 10 ? 8 : 16 ) + 8 * $len;
			if ( $bitsNeeded <= $dataCw * 8 ) {
				$version = $v;
				break;
			}
		}
		if ( ! $version ) {
			return null;
		}
		$t      = self::$tableM[ $version ];
		$dataCw = $t[0] - $t[1] * ( $t[2] + $t[4] );

		// Bit stream: mode 0100, length, data, terminator, pad.
		$bits = '0100' . str_pad( decbin( $len ), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT );
		foreach ( $bytes as $b ) {
			$bits .= str_pad( decbin( $b ), 8, '0', STR_PAD_LEFT );
		}
		$bits .= str_repeat( '0', min( 4, $dataCw * 8 - strlen( $bits ) ) );
		while ( strlen( $bits ) % 8 ) {
			$bits .= '0';
		}
		$pads = array( '11101100', '00010001' );
		$i    = 0;
		while ( strlen( $bits ) < $dataCw * 8 ) {
			$bits .= $pads[ $i++ % 2 ];
		}
		$data = array();
		for ( $i = 0; $i < $dataCw; $i++ ) {
			$data[] = bindec( substr( $bits, $i * 8, 8 ) );
		}

		// Split into blocks, compute EC, interleave.
		$blocks = array();
		$pos    = 0;
		for ( $g = 0; $g < 2; $g++ ) {
			$count = $t[ 2 + $g * 2 ];
			$size  = $t[ 3 + $g * 2 ];
			for ( $b = 0; $b < $count; $b++ ) {
				$blocks[] = array_slice( $data, $pos, $size );
				$pos     += $size;
			}
		}
		$ecBlocks = array();
		foreach ( $blocks as $block ) {
			$ecBlocks[] = self::reedSolomon( $block, $t[1] );
		}
		$final  = array();
		$maxLen = max( array_map( 'count', $blocks ) );
		for ( $i = 0; $i < $maxLen; $i++ ) {
			foreach ( $blocks as $block ) {
				if ( isset( $block[ $i ] ) ) {
					$final[] = $block[ $i ];
				}
			}
		}
		for ( $i = 0; $i < $t[1]; $i++ ) {
			foreach ( $ecBlocks as $block ) {
				$final[] = $block[ $i ];
			}
		}
		$stream = '';
		foreach ( $final as $cw ) {
			$stream .= str_pad( decbin( $cw ), 8, '0', STR_PAD_LEFT );
		}
		$remainder = array( 1 => 0, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 0, 8 => 0, 9 => 0, 10 => 0 );
		$stream   .= str_repeat( '0', $remainder[ $version ] );

		// Matrix.
		$size   = 17 + 4 * $version;
		$m      = array_fill( 0, $size, array_fill( 0, $size, null ) );
		$fixed  = array_fill( 0, $size, array_fill( 0, $size, false ) );
		$finder = function ( $r, $c ) use ( &$m, &$fixed, $size ) {
			for ( $dr = -1; $dr <= 7; $dr++ ) {
				for ( $dc = -1; $dc <= 7; $dc++ ) {
					$rr = $r + $dr;
					$cc = $c + $dc;
					if ( $rr < 0 || $cc < 0 || $rr >= $size || $cc >= $size ) {
						continue;
					}
					$on = ( $dr >= 0 && $dr <= 6 && ( 0 === $dc || 6 === $dc ) ) || ( $dc >= 0 && $dc <= 6 && ( 0 === $dr || 6 === $dr ) ) || ( $dr >= 2 && $dr <= 4 && $dc >= 2 && $dc <= 4 );
					$m[ $rr ][ $cc ]     = $on ? 1 : 0;
					$fixed[ $rr ][ $cc ] = true;
				}
			}
		};
		$finder( 0, 0 );
		$finder( 0, $size - 7 );
		$finder( $size - 7, 0 );
		// Timing.
		for ( $i = 8; $i < $size - 8; $i++ ) {
			$m[6][ $i ]     = $i % 2 === 0 ? 1 : 0;
			$m[ $i ][6]     = $i % 2 === 0 ? 1 : 0;
			$fixed[6][ $i ] = true;
			$fixed[ $i ][6] = true;
		}
		// Alignment.
		$al = self::$alignment[ $version ];
		foreach ( $al as $r ) {
			foreach ( $al as $c ) {
				if ( $fixed[ $r ][ $c ] ) {
					continue;
				}
				for ( $dr = -2; $dr <= 2; $dr++ ) {
					for ( $dc = -2; $dc <= 2; $dc++ ) {
						$on = ( 2 === abs( $dr ) || 2 === abs( $dc ) || ( 0 === $dr && 0 === $dc ) );
						$m[ $r + $dr ][ $c + $dc ]     = $on ? 1 : 0;
						$fixed[ $r + $dr ][ $c + $dc ] = true;
					}
				}
			}
		}
		// Dark module and reserved format areas.
		$m[ $size - 8 ][8]     = 1;
		$fixed[ $size - 8 ][8] = true;
		for ( $i = 0; $i < 9; $i++ ) {
			if ( ! $fixed[8][ $i ] ) {
				$fixed[8][ $i ] = true;
				$m[8][ $i ]     = 0;
			}
			if ( ! $fixed[ $i ][8] ) {
				$fixed[ $i ][8] = true;
				$m[ $i ][8]     = 0;
			}
		}
		for ( $i = $size - 8; $i < $size; $i++ ) {
			$fixed[8][ $i ] = true;
			$m[8][ $i ]     = 0;
			$fixed[ $i ][8] = true;
			$m[ $i ][8]     = 0;
		}
		// Place data.
		$bitIndex = 0;
		$total    = strlen( $stream );
		$up       = true;
		for ( $col = $size - 1; $col > 0; $col -= 2 ) {
			if ( 6 === $col ) {
				$col = 5;
			}
			for ( $k = 0; $k < $size; $k++ ) {
				$row = $up ? $size - 1 - $k : $k;
				for ( $d = 0; $d < 2; $d++ ) {
					$c = $col - $d;
					if ( $fixed[ $row ][ $c ] ) {
						continue;
					}
					$m[ $row ][ $c ] = $bitIndex < $total ? (int) $stream[ $bitIndex ] : 0;
					$bitIndex++;
				}
			}
			$up = ! $up;
		}
		// Pick the mask with the lowest penalty.
		$best      = null;
		$bestScore = PHP_INT_MAX;
		for ( $mask = 0; $mask < 8; $mask++ ) {
			$candidate = self::applyMask( $m, $fixed, $mask, $size );
			self::writeFormat( $candidate, $mask, $size );
			$score = self::penalty( $candidate, $size );
			if ( $score < $bestScore ) {
				$bestScore = $score;
				$best      = $candidate;
			}
		}
		return $best;
	}

	private static function applyMask( $m, $fixed, $mask, $size ) {
		for ( $r = 0; $r < $size; $r++ ) {
			for ( $c = 0; $c < $size; $c++ ) {
				if ( $fixed[ $r ][ $c ] ) {
					continue;
				}
				switch ( $mask ) {
					case 0:
						$f = ( $r + $c ) % 2 === 0;
						break;
					case 1:
						$f = $r % 2 === 0;
						break;
					case 2:
						$f = $c % 3 === 0;
						break;
					case 3:
						$f = ( $r + $c ) % 3 === 0;
						break;
					case 4:
						$f = ( intdiv( $r, 2 ) + intdiv( $c, 3 ) ) % 2 === 0;
						break;
					case 5:
						$f = ( ( $r * $c ) % 2 + ( $r * $c ) % 3 ) === 0;
						break;
					case 6:
						$f = ( ( ( $r * $c ) % 2 + ( $r * $c ) % 3 ) % 2 ) === 0;
						break;
					default:
						$f = ( ( ( $r + $c ) % 2 + ( $r * $c ) % 3 ) % 2 ) === 0;
				}
				if ( $f ) {
					$m[ $r ][ $c ] = $m[ $r ][ $c ] ? 0 : 1;
				}
			}
		}
		return $m;
	}

	/** Format information for level M (01) and the given mask, BCH coded. */
	private static function writeFormat( &$m, $mask, $size ) {
		$data = ( 0 << 3 ) | $mask; // level M = 00.
		$bits = $data << 10;
		$g    = 0x537;
		for ( $i = 14; $i >= 10; $i-- ) {
			if ( ( $bits >> $i ) & 1 ) {
				$bits ^= $g << ( $i - 10 );
			}
		}
		$format = ( ( $data << 10 ) | $bits ) ^ 0x5412;
		$bit    = function ( $i ) use ( $format ) {
			return ( $format >> $i ) & 1;
		};
		// First copy around the top left finder ($m[row][col]).
		for ( $i = 0; $i <= 5; $i++ ) {
			$m[ $i ][8] = $bit( $i );
		}
		$m[7][8] = $bit( 6 );
		$m[8][8] = $bit( 7 );
		$m[8][7] = $bit( 8 );
		for ( $i = 9; $i < 15; $i++ ) {
			$m[8][ 14 - $i ] = $bit( $i );
		}
		// Second copy: right part of row 8 and bottom part of column 8.
		for ( $i = 0; $i < 8; $i++ ) {
			$m[8][ $size - 1 - $i ] = $bit( $i );
		}
		for ( $i = 8; $i < 15; $i++ ) {
			$m[ $size - 15 + $i ][8] = $bit( $i );
		}
		$m[ $size - 8 ][8] = 1;
	}

	private static function penalty( $m, $size ) {
		$score = 0;
		for ( $pass = 0; $pass < 2; $pass++ ) {
			for ( $r = 0; $r < $size; $r++ ) {
				$run  = 1;
				$prev = null;
				for ( $c = 0; $c < $size; $c++ ) {
					$v = $pass ? $m[ $c ][ $r ] : $m[ $r ][ $c ];
					if ( $v === $prev ) {
						$run++;
					} else {
						if ( $run >= 5 ) {
							$score += $run - 2;
						}
						$run  = 1;
						$prev = $v;
					}
				}
				if ( $run >= 5 ) {
					$score += $run - 2;
				}
			}
		}
		for ( $r = 0; $r < $size - 1; $r++ ) {
			for ( $c = 0; $c < $size - 1; $c++ ) {
				$v = $m[ $r ][ $c ];
				if ( $v === $m[ $r ][ $c + 1 ] && $v === $m[ $r + 1 ][ $c ] && $v === $m[ $r + 1 ][ $c + 1 ] ) {
					$score += 3;
				}
			}
		}
		$dark = 0;
		foreach ( $m as $row ) {
			$dark += array_sum( $row );
		}
		$pct    = $dark * 100 / ( $size * $size );
		$score += intdiv( (int) abs( $pct - 50 ), 5 ) * 10;
		return $score;
	}

	private static function initTables() {
		if ( null !== self::$exp ) {
			return;
		}
		self::$exp = array_fill( 0, 512, 0 );
		self::$log = array_fill( 0, 256, 0 );
		$x         = 1;
		for ( $i = 0; $i < 255; $i++ ) {
			self::$exp[ $i ] = $x;
			self::$log[ $x ] = $i;
			$x             <<= 1;
			if ( $x & 0x100 ) {
				$x ^= 0x11d;
			}
		}
		for ( $i = 255; $i < 512; $i++ ) {
			self::$exp[ $i ] = self::$exp[ $i - 255 ];
		}
	}

	private static function mul( $a, $b ) {
		if ( 0 === $a || 0 === $b ) {
			return 0;
		}
		return self::$exp[ self::$log[ $a ] + self::$log[ $b ] ];
	}

	private static function reedSolomon( $data, $ecCount ) {
		self::initTables();
		$gen = array( 1 );
		for ( $i = 0; $i < $ecCount; $i++ ) {
			$next = array_fill( 0, count( $gen ) + 1, 0 );
			foreach ( $gen as $j => $g ) {
				$next[ $j ]    ^= $g;
				$next[ $j + 1 ] ^= self::mul( $g, self::$exp[ $i ] );
			}
			$gen = $next;
		}
		$res = array_fill( 0, $ecCount, 0 );
		foreach ( $data as $d ) {
			$factor = $d ^ array_shift( $res );
			$res[]  = 0;
			for ( $j = 0; $j < $ecCount; $j++ ) {
				$res[ $j ] ^= self::mul( $gen[ $j + 1 ], $factor );
			}
		}
		return $res;
	}

	/**
	 * SVG string for the text (empty string when the text is too long).
	 */
	public static function svg( $text, $pixel = 4, $title = '' ) {
		$m = self::matrix( $text );
		if ( null === $m ) {
			return '';
		}
		$n     = count( $m );
		$quiet = 4;
		$w     = ( $n + 2 * $quiet ) * $pixel;
		$path  = '';
		for ( $r = 0; $r < $n; $r++ ) {
			for ( $c = 0; $c < $n; $c++ ) {
				if ( $m[ $r ][ $c ] ) {
					$path .= 'M' . ( ( $c + $quiet ) * $pixel ) . ' ' . ( ( $r + $quiet ) * $pixel ) . 'h' . $pixel . 'v' . $pixel . 'h-' . $pixel . 'z';
				}
			}
		}
		$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $w . ' ' . $w . '" width="' . $w . '" height="' . $w . '" role="img" shape-rendering="crispEdges">';
		$svg .= '<title>' . esc_html( '' !== $title ? $title : $text ) . '</title>';
		$svg .= '<rect width="100%" height="100%" fill="#fff"/><path d="' . $path . '" fill="#171509"/></svg>';
		return $svg;
	}
}
