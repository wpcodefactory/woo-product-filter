<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Swatches Class
 *
 * Colour, picture and chip swatches for any attribute, in Free: one colour and one picture per term (term meta,
 * fields on the term add and edit screens under Products > Attributes), automatic colour detection from the term
 * name (CSS names plus a multilingual word map) and, when the name says nothing, from the dominant colour of the
 * first product picture carrying the term (GD, downsampled). Automatic colours are cached in term meta with a flag
 * and can be detected again from the term screen.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Swatches {

	const META_COLOR   = 'wpf_swatch_color';
	const META_COLORS  = 'wpf_swatch_colors'; // Pro: up to four colours.
	const META_IMAGE   = 'wpf_swatch_image';
	const META_AUTO    = 'wpf_swatch_auto';
	const META_SOURCE  = 'wpf_swatch_auto_source';
	const META_TIME    = 'wpf_swatch_auto_time';
	const AJAX_DETECT  = 'woobewoo_pf_swatch_detect';
	const NONCE        = 'woobewoo_pf_swatch';
	const IMAGE_BUDGET = 10;

	private static $booted   = false;
	private static $words    = null;
	private static $imageRuns = 0;

	public static function boot() {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;
		add_action( 'init', array( __CLASS__, 'hookTaxonomies' ), 20 );
		add_action( 'wp_ajax_' . self::AJAX_DETECT, array( __CLASS__, 'ajaxDetect' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'adminAssets' ) );
	}

	/**
	 * Attribute taxonomies (pa_*) and the brand taxonomies get the swatch fields.
	 */
	public static function taxonomies() {
		$list = array();
		if ( function_exists( 'wc_get_attribute_taxonomy_names' ) ) {
			$list = (array) wc_get_attribute_taxonomy_names();
		}
		foreach ( array( 'product_brand', 'pwb-brand' ) as $t ) {
			if ( taxonomy_exists( $t ) ) {
				$list[] = $t;
			}
		}
		return array_values( array_unique( (array) apply_filters( 'woobewoo_pf_swatch_taxonomies', $list ) ) );
	}

	public static function hookTaxonomies() {
		foreach ( self::taxonomies() as $tax ) {
			add_action( $tax . '_add_form_fields', array( __CLASS__, 'addFields' ), 10, 1 );
			add_action( $tax . '_edit_form_fields', array( __CLASS__, 'editFields' ), 10, 2 );
			add_action( 'created_' . $tax, array( __CLASS__, 'save' ), 10, 2 );
			add_action( 'edited_' . $tax, array( __CLASS__, 'save' ), 10, 2 );
			add_filter( 'manage_edit-' . $tax . '_columns', array( __CLASS__, 'column' ) );
			add_filter( 'manage_' . $tax . '_custom_column', array( __CLASS__, 'columnContent' ), 10, 3 );
		}
	}

	/* ------------------------------------------------------------------ public API */

	/**
	 * The swatch of a term: array( type => color|image|none, css => '#hex' or gradient, url => picture, source ).
	 */
	public static function swatch( $term ) {
		$term = $term instanceof WP_Term ? $term : get_term( (int) $term );
		if ( ! $term || is_wp_error( $term ) ) {
			return array( 'type' => 'none' );
		}
		$image = (int) get_term_meta( $term->term_id, self::META_IMAGE, true );
		if ( $image ) {
			$url = wp_get_attachment_image_url( $image, 'thumbnail' );
			if ( $url ) {
				return array(
					'type'   => 'image',
					'url'    => $url,
					'source' => 'manual',
				);
			}
		}
		$colors = get_term_meta( $term->term_id, self::META_COLORS, true );
		$colors = is_array( $colors ) ? array_values( array_filter( array_map( 'sanitize_hex_color', $colors ) ) ) : array();
		$colors = (array) apply_filters( 'woobewoo_pf_swatch_colors', $colors, $term );
		$single = sanitize_hex_color( (string) get_term_meta( $term->term_id, self::META_COLOR, true ) );
		if ( $single && ! $colors ) {
			$colors = array( $single );
		}
		if ( count( $colors ) > 1 && ! self::pro_active() ) {
			$colors = array( $colors[0] );
		}
		if ( $colors ) {
			return array(
				'type'   => 'color',
				'css'    => count( $colors ) > 1 ? 'linear-gradient(135deg, ' . implode( ', ', self::stops( $colors ) ) . ')' : $colors[0],
				'colors' => $colors,
				'source' => 'manual',
			);
		}
		$auto = self::color_for_term( $term );
		if ( $auto ) {
			return array(
				'type'   => 'color',
				'css'    => $auto,
				'colors' => array( $auto ),
				'source' => (string) get_term_meta( $term->term_id, self::META_SOURCE, true ),
			);
		}
		return array( 'type' => 'none' );
	}

	private static function stops( $colors ) {
		$n     = count( $colors );
		$stops = array();
		foreach ( $colors as $i => $c ) {
			$from    = round( $i / $n * 100 );
			$to      = round( ( $i + 1 ) / $n * 100 );
			$stops[] = $c . ' ' . $from . '% ' . $to . '%';
		}
		return $stops;
	}

	public static function pro_active() {
		return defined( 'WPF_PRO_VERSION' ) || class_exists( 'WooBeWoo_PF_Pro' ) || apply_filters( 'woobewoo_pf_is_pro', false );
	}

	/**
	 * Manual colour, else the cached automatic colour, else detect now (name first, then the product picture).
	 */
	public static function color_for_term( $term, $force = false ) {
		$term = $term instanceof WP_Term ? $term : get_term( (int) $term );
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}
		$manual = sanitize_hex_color( (string) get_term_meta( $term->term_id, self::META_COLOR, true ) );
		if ( $manual && ! $force ) {
			return $manual;
		}
		if ( ! $force ) {
			$source = (string) get_term_meta( $term->term_id, self::META_SOURCE, true );
			if ( '' !== $source ) {
				$auto = sanitize_hex_color( (string) get_term_meta( $term->term_id, self::META_AUTO, true ) );
				if ( 'none' === $source || $auto ) {
					return $auto ? $auto : '';
				}
			}
		}
		return self::detect( $term, $force );
	}

	/**
	 * Run the detection and cache it: name, then first product picture, else "none".
	 */
	public static function detect( $term, $force = false ) {
		$term  = $term instanceof WP_Term ? $term : get_term( (int) $term );
		$color = self::detect_from_name( $term->name );
		$src   = 'name';
		if ( ! $color && ( $force || self::$imageRuns < self::IMAGE_BUDGET ) ) {
			++self::$imageRuns;
			$color = self::detect_from_image( $term );
			$src   = $color ? 'image' : 'none';
		} elseif ( ! $color ) {
			return ''; // out of budget for this request; detected on a later one
		}
		update_term_meta( $term->term_id, self::META_AUTO, $color );
		update_term_meta( $term->term_id, self::META_SOURCE, $src );
		update_term_meta( $term->term_id, self::META_TIME, time() );
		return $color;
	}

	/* ------------------------------------------------------------------ detection */

	public static function words() {
		if ( null === self::$words ) {
			$words = include __DIR__ . DIRECTORY_SEPARATOR . 'colors.php';
			self::$words = (array) apply_filters( 'woobewoo_pf_swatch_color_words', $words );
		}
		return self::$words;
	}

	/**
	 * Colour from a term name: whole name, then two word phrases, then single words; modifiers lighten or darken.
	 */
	public static function detect_from_name( $name ) {
		$words = self::words();
		$n     = mb_strtolower( trim( (string) $name ) );
		$n     = str_replace( array( '-', '_', '/' ), ' ', $n );
		$n     = preg_replace( '/\s+/', ' ', $n );
		if ( '' === $n ) {
			return '';
		}
		if ( preg_match( '/#?([0-9a-f]{6}|[0-9a-f]{3})\b/i', $n, $m ) && preg_match( '/^#/', trim( (string) $name ) ) ) {
			return sanitize_hex_color( '#' . $m[1] );
		}
		if ( isset( $words[ $n ] ) ) {
			return $words[ $n ];
		}
		$compact = str_replace( ' ', '', $n );
		if ( isset( $words[ $compact ] ) ) {
			return $words[ $compact ];
		}
		$parts     = explode( ' ', $n );
		$darker    = array( 'dark', 'deep', 'dunkel', 'foncé', 'fonce', 'oscuro', 'oscura', 'scuro', 'scura', 'night', 'midnight' );
		$lighter   = array( 'light', 'pale', 'hell', 'clair', 'claire', 'claro', 'clara', 'chiaro', 'chiara', 'baby', 'pastel', 'soft' );
		$modifier  = 0;
		$remaining = array();
		foreach ( $parts as $w ) {
			if ( in_array( $w, $darker, true ) ) {
				$modifier = -1;
			} elseif ( in_array( $w, $lighter, true ) ) {
				$modifier = 1;
			} else {
				$remaining[] = $w;
			}
		}
		$hex = '';
		for ( $i = 0; $i < count( $remaining ) && '' === $hex; $i++ ) {
			if ( isset( $remaining[ $i + 1 ] ) && isset( $words[ $remaining[ $i ] . ' ' . $remaining[ $i + 1 ] ] ) ) {
				$hex = $words[ $remaining[ $i ] . ' ' . $remaining[ $i + 1 ] ];
			} elseif ( isset( $remaining[ $i + 1 ] ) && isset( $words[ $remaining[ $i ] . $remaining[ $i + 1 ] ] ) ) {
				$hex = $words[ $remaining[ $i ] . $remaining[ $i + 1 ] ];
			}
		}
		if ( '' === $hex ) {
			foreach ( $remaining as $w ) {
				if ( isset( $words[ $w ] ) ) {
					$hex = $words[ $w ];
					break;
				}
				// Plural and simple suffixes: reds, blues, greenish, bleue, rossa.
				$stem = preg_replace( '/(ish|es|s|e|a|o)$/u', '', $w );
				if ( $stem !== $w && isset( $words[ $stem ] ) ) {
					$hex = $words[ $stem ];
					break;
				}
			}
		}
		if ( '' === $hex ) {
			return '';
		}
		if ( $modifier ) {
			$hex = self::shade( $hex, $modifier > 0 ? 0.25 : -0.3 );
		}
		return $hex;
	}

	/**
	 * Lighten (positive) or darken (negative) a hex colour by a fraction.
	 */
	public static function shade( $hex, $amount ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$out = '#';
		foreach ( array( 0, 2, 4 ) as $i ) {
			$c = hexdec( substr( $hex, $i, 2 ) );
			$c = $amount > 0 ? $c + ( 255 - $c ) * $amount : $c * ( 1 + $amount );
			$out .= str_pad( dechex( (int) round( max( 0, min( 255, $c ) ) ) ), 2, '0', STR_PAD_LEFT );
		}
		return strtoupper( $out );
	}

	/**
	 * Dominant colour of the first product picture carrying the term (variation picture first), via GD on a 24px copy.
	 */
	public static function detect_from_image( $term ) {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return '';
		}
		$file = self::first_image_for_term( $term );
		if ( ! $file ) {
			return '';
		}
		return self::dominant_color( $file );
	}

	/**
	 * Path of the first picture for a term: a variation with that attribute value and its own picture, else the
	 * product thumbnail.
	 */
	public static function first_image_for_term( $term ) {
		global $wpdb;
		$term = $term instanceof WP_Term ? $term : get_term( (int) $term );
		if ( ! $term || is_wp_error( $term ) ) {
			return '';
		}
		$ids = get_objects_in_term( $term->term_id, $term->taxonomy );
		if ( is_wp_error( $ids ) || empty( $ids ) ) {
			return '';
		}
		$ids = array_slice( array_map( 'intval', $ids ), 0, 20 );
		if ( 0 === strpos( $term->taxonomy, 'pa_' ) ) {
			$in  = implode( ',', $ids );
			$vid = $wpdb->get_var( $wpdb->prepare( "SELECT p.ID FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} a ON a.post_id = p.ID AND a.meta_key = %s AND a.meta_value = %s INNER JOIN {$wpdb->postmeta} t ON t.post_id = p.ID AND t.meta_key = '_thumbnail_id' AND t.meta_value <> '' WHERE p.post_type = 'product_variation' AND p.post_parent IN ($in) LIMIT 1", 'attribute_' . $term->taxonomy, $term->slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $in is a comma separated list of cast integers
			if ( $vid ) {
				$thumb = get_post_thumbnail_id( (int) $vid );
				$file  = $thumb ? self::image_path( (int) $thumb ) : '';
				if ( $file ) {
					return $file;
				}
			}
		}
		foreach ( $ids as $pid ) {
			$thumb = get_post_thumbnail_id( $pid );
			if ( $thumb ) {
				$file = self::image_path( (int) $thumb );
				if ( $file ) {
					return $file;
				}
			}
		}
		return '';
	}

	private static function image_path( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( $file && file_exists( $file ) ) {
			// Prefer a small size if one exists.
			$meta = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $meta['sizes']['thumbnail']['file'] ) ) {
				$small = dirname( $file ) . DIRECTORY_SEPARATOR . $meta['sizes']['thumbnail']['file'];
				if ( file_exists( $small ) ) {
					return $small;
				}
			}
			return $file;
		}
		return '';
	}

	/**
	 * Dominant colour of an image file: downsample to 24x24, drop near white / transparent background pixels when
	 * they are not the whole picture, quantize to 32 levels and average the biggest bucket.
	 */
	public static function dominant_color( $file ) {
		$data = @file_get_contents( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! $data ) {
			return '';
		}
		$src = @imagecreatefromstring( $data ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $src ) {
			return '';
		}
		$size = 24;
		$im   = imagecreatetruecolor( $size, $size );
		imagealphablending( $im, false );
		imagesavealpha( $im, true );
		imagefill( $im, 0, 0, imagecolorallocatealpha( $im, 0, 0, 0, 127 ) );
		imagecopyresampled( $im, $src, 0, 0, 0, 0, $size, $size, imagesx( $src ), imagesy( $src ) );
		imagedestroy( $src );
		$buckets = array();
		$all     = array();
		for ( $x = 0; $x < $size; $x++ ) {
			for ( $y = 0; $y < $size; $y++ ) {
				$rgb = imagecolorat( $im, $x, $y );
				$a   = ( $rgb >> 24 ) & 0x7F;
				if ( $a > 60 ) {
					continue; // transparent
				}
				$r = ( $rgb >> 16 ) & 0xFF;
				$g = ( $rgb >> 8 ) & 0xFF;
				$b = $rgb & 0xFF;
				$all[] = array( $r, $g, $b );
				// Background: near white or near neutral very light pixels (studio backgrounds).
				$bg  = ( $r > 235 && $g > 235 && $b > 235 );
				$key = ( ( $r >> 3 ) << 10 ) | ( ( $g >> 3 ) << 5 ) | ( $b >> 3 );
				if ( ! isset( $buckets[ $key ] ) ) {
					$buckets[ $key ] = array( 'n' => 0, 'r' => 0, 'g' => 0, 'b' => 0, 'bg' => $bg );
				}
				++$buckets[ $key ]['n'];
				$buckets[ $key ]['r'] += $r;
				$buckets[ $key ]['g'] += $g;
				$buckets[ $key ]['b'] += $b;
			}
		}
		imagedestroy( $im );
		if ( ! $buckets ) {
			return '';
		}
		// Drop background buckets unless they are everything.
		$fg = array_filter( $buckets, function ( $bk ) {
			return ! $bk['bg'];
		} );
		if ( count( $fg ) >= 3 ) {
			$buckets = $fg;
		}
		// Prefer saturated buckets: weight by saturation so a grey floor does not beat a red shoe.
		$best   = null;
		$bestW  = -1;
		foreach ( $buckets as $bk ) {
			$r = $bk['r'] / $bk['n'];
			$g = $bk['g'] / $bk['n'];
			$b = $bk['b'] / $bk['n'];
			$max = max( $r, $g, $b );
			$min = min( $r, $g, $b );
			$sat = $max > 0 ? ( $max - $min ) / $max : 0;
			$w   = $bk['n'] * ( 0.4 + $sat );
			if ( $w > $bestW ) {
				$bestW = $w;
				$best  = array( $r, $g, $b );
			}
		}
		if ( ! $best ) {
			return '';
		}
		return strtoupper( sprintf( '#%02x%02x%02x', (int) round( $best[0] ), (int) round( $best[1] ), (int) round( $best[2] ) ) );
	}

	/* ------------------------------------------------------------------ admin: term screens */

	public static function adminAssets( $hook ) {
		if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
			return;
		}
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->taxonomy, self::taxonomies(), true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'woobewoo-pf-swatches-admin', WPF_MODULES_PATH . 'swatches/js/admin.swatches.js', array(), WPF_VERSION, true );
		wp_localize_script(
			'woobewoo-pf-swatches-admin',
			'wpfSwatchesAdmin',
			array(
				'ajax'   => admin_url( 'admin-ajax.php' ),
				'action' => self::AJAX_DETECT,
				'nonce'  => wp_create_nonce( self::NONCE ),
				'i18n'   => array(
					'choose'   => __( 'Choose a picture', 'woo-product-filter' ),
					'use'      => __( 'Use this picture', 'woo-product-filter' ),
					'working'  => __( 'Looking…', 'woo-product-filter' ),
					/* translators: 1: colour found, 2: where it was found (product picture or name) */
					'found'    => __( 'Found %1$s (from the %2$s)', 'woo-product-filter' ),
					'nothing'  => __( 'Nothing found. Pick a colour by hand.', 'woo-product-filter' ),
					'name'     => __( 'name', 'woo-product-filter' ),
					'image'    => __( 'product picture', 'woo-product-filter' ),
				),
			)
		);
		wp_enqueue_style( 'woobewoo-pf-swatches-admin', WPF_MODULES_PATH . 'swatches/css/admin.swatches.css', array(), WPF_VERSION );
	}

	/**
	 * Fields on the "Add new" form.
	 */
	public static function addFields( $taxonomy ) {
		wp_nonce_field( self::NONCE, 'wpf_swatch_nonce' );
		echo '<div class="form-field wpf-swatch-field">';
		echo '<label for="wpf_swatch_color">' . esc_html__( 'Colour', 'woo-product-filter' ) . '</label>';
		echo '<div class="wpf-swatch-colorRow"><input type="color" id="wpf_swatch_color_pick" value="#CCCCCC" aria-label="' . esc_attr__( 'Pick a colour', 'woo-product-filter' ) . '"><input type="text" id="wpf_swatch_color" name="wpf_swatch_color" value="" class="wpf-pill" placeholder="#RRGGBB" pattern="#?[0-9a-fA-F]{3,6}" autocomplete="off"></div>';
		echo '<p class="description">' . esc_html__( 'The colour shown as a swatch in filters. Leave empty and we find it from the name or the first product picture.', 'woo-product-filter' ) . '</p>';
		echo '</div>';
		echo '<div class="form-field wpf-swatch-field">';
		echo '<label for="wpf_swatch_image">' . esc_html__( 'Picture', 'woo-product-filter' ) . '</label>';
		echo '<div class="wpf-swatch-imageRow"><input type="hidden" id="wpf_swatch_image" name="wpf_swatch_image" value=""><span class="wpf-swatch-preview" aria-hidden="true"></span><button type="button" class="button wpf-pill" data-wpf-swatch-pick>' . esc_html__( 'Choose a picture', 'woo-product-filter' ) . '</button> <button type="button" class="button-link wpf-swatch-remove" data-wpf-swatch-remove hidden>' . esc_html__( 'Remove', 'woo-product-filter' ) . '</button></div>';
		echo '<p class="description">' . esc_html__( 'A small picture used instead of a colour, for patterns and materials.', 'woo-product-filter' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Fields on the edit form (table rows).
	 */
	public static function editFields( $term, $taxonomy ) {
		$color  = sanitize_hex_color( (string) get_term_meta( $term->term_id, self::META_COLOR, true ) );
		$image  = (int) get_term_meta( $term->term_id, self::META_IMAGE, true );
		$auto   = sanitize_hex_color( (string) get_term_meta( $term->term_id, self::META_AUTO, true ) );
		$source = (string) get_term_meta( $term->term_id, self::META_SOURCE, true );
		$url    = $image ? wp_get_attachment_image_url( $image, 'thumbnail' ) : '';
		wp_nonce_field( self::NONCE, 'wpf_swatch_nonce' );
		echo '<tr class="form-field wpf-swatch-field"><th scope="row"><label for="wpf_swatch_color">' . esc_html__( 'Colour', 'woo-product-filter' ) . '</label></th><td>';
		echo '<div class="wpf-swatch-colorRow"><input type="color" id="wpf_swatch_color_pick" value="' . esc_attr( $color ? $color : ( $auto ? $auto : '#CCCCCC' ) ) . '" aria-label="' . esc_attr__( 'Pick a colour', 'woo-product-filter' ) . '"><input type="text" id="wpf_swatch_color" name="wpf_swatch_color" value="' . esc_attr( $color ) . '" class="wpf-pill" placeholder="#RRGGBB" pattern="#?[0-9a-fA-F]{3,6}" autocomplete="off"></div>';
		echo '<p class="description">' . esc_html__( 'The colour shown as a swatch in filters. Leave empty to use the one we found.', 'woo-product-filter' ) . '</p>';
		echo '<p class="wpf-swatch-auto">';
		if ( $auto ) {
			$from = 'image' === $source ? __( 'product picture', 'woo-product-filter' ) : __( 'name', 'woo-product-filter' );
			/* translators: 1: colour found, 2: where it was found (product picture or name) */
			echo '<span class="wpf-swatch-dot" style="background:' . esc_attr( $auto ) . '" aria-hidden="true"></span> <span data-wpf-swatch-auto-text>' . esc_html( sprintf( __( 'Found automatically: %1$s (from the %2$s)', 'woo-product-filter' ), $auto, $from ) ) . '</span> ';
		} else {
			echo '<span data-wpf-swatch-auto-text>' . esc_html__( 'No colour found automatically yet.', 'woo-product-filter' ) . '</span> ';
		}
		echo '<button type="button" class="button wpf-pill" data-wpf-swatch-detect data-term="' . (int) $term->term_id . '">' . esc_html__( 'Detect again', 'woo-product-filter' ) . '</button></p>';
		echo '</td></tr>';
		echo '<tr class="form-field wpf-swatch-field"><th scope="row"><label for="wpf_swatch_image">' . esc_html__( 'Picture', 'woo-product-filter' ) . '</label></th><td>';
		echo '<div class="wpf-swatch-imageRow"><input type="hidden" id="wpf_swatch_image" name="wpf_swatch_image" value="' . esc_attr( $image ? $image : '' ) . '"><span class="wpf-swatch-preview" aria-hidden="true">' . ( $url ? '<img src="' . esc_url( $url ) . '" alt="">' : '' ) . '</span><button type="button" class="button wpf-pill" data-wpf-swatch-pick>' . esc_html__( 'Choose a picture', 'woo-product-filter' ) . '</button> <button type="button" class="button-link wpf-swatch-remove" data-wpf-swatch-remove' . ( $url ? '' : ' hidden' ) . '>' . esc_html__( 'Remove', 'woo-product-filter' ) . '</button></div>';
		echo '<p class="description">' . esc_html__( 'A small picture used instead of a colour, for patterns and materials.', 'woo-product-filter' ) . '</p>';
		echo '</td></tr>';
		if ( ! self::pro_active() ) {
			echo '<tr class="form-field wpf-swatch-field"><th scope="row">' . esc_html__( 'More colours', 'woo-product-filter' ) . '</th><td><p class="description">' . esc_html__( 'Up to four colours on one swatch (two tone, stripes) is a Pro feature.', 'woo-product-filter' ) . '</p></td></tr>';
		}
	}

	public static function save( $term_id, $tt_id = 0 ) {
		if ( ! isset( $_POST['wpf_swatch_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpf_swatch_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_product_terms' ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_POST['wpf_swatch_color'] ) ) {
			$raw = trim( sanitize_text_field( wp_unslash( $_POST['wpf_swatch_color'] ) ) );
			if ( '' !== $raw && '#' !== $raw[0] ) {
				$raw = '#' . $raw;
			}
			$color = sanitize_hex_color( $raw );
			if ( $color ) {
				update_term_meta( $term_id, self::META_COLOR, strtoupper( $color ) );
			} else {
				delete_term_meta( $term_id, self::META_COLOR );
			}
		}
		if ( isset( $_POST['wpf_swatch_image'] ) ) {
			$img = (int) $_POST['wpf_swatch_image'];
			if ( $img > 0 && 'attachment' === get_post_type( $img ) ) {
				update_term_meta( $term_id, self::META_IMAGE, $img );
			} else {
				delete_term_meta( $term_id, self::META_IMAGE );
			}
		}
		if ( class_exists( 'WooBeWoo_PF_Index' ) ) {
			WooBeWoo_PF_Index::bump_version();
		}
	}

	public static function column( $columns ) {
		$out = array();
		foreach ( $columns as $k => $v ) {
			$out[ $k ] = $v;
			if ( 'cb' === $k ) {
				$out['wpf_swatch'] = __( 'Swatch', 'woo-product-filter' );
			}
		}
		return $out;
	}

	public static function columnContent( $content, $column, $term_id ) {
		if ( 'wpf_swatch' !== $column ) {
			return $content;
		}
		$sw = self::swatch( $term_id );
		if ( 'image' === $sw['type'] ) {
			return '<span class="wpf-swatch-dot" style="background-image:url(' . esc_url( $sw['url'] ) . ')" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Picture', 'woo-product-filter' ) . '</span>';
		}
		if ( 'color' === $sw['type'] ) {
			return '<span class="wpf-swatch-dot" style="background:' . esc_attr( $sw['css'] ) . '" title="' . esc_attr( $sw['css'] ) . '"></span><span class="screen-reader-text">' . esc_html( $sw['css'] ) . '</span>';
		}
		return '<span class="wpf-swatch-dot wpf-swatch-dot--none" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'No colour', 'woo-product-filter' ) . '</span>';
	}

	/**
	 * "Detect again": admin AJAX, nonce + manage_product_terms.
	 */
	public static function ajaxDetect() {
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page and try again.', 'woo-product-filter' ) ), 403 );
		}
		if ( ! current_user_can( 'manage_product_terms' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'woo-product-filter' ) ), 403 );
		}
		$term_id = isset( $_REQUEST['term'] ) ? (int) $_REQUEST['term'] : 0;
		$term    = $term_id ? get_term( $term_id ) : null;
		if ( ! $term || is_wp_error( $term ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown term', 'woo-product-filter' ) ), 404 );
		}
		$color = self::detect( $term, true );
		if ( class_exists( 'WooBeWoo_PF_Index' ) ) {
			WooBeWoo_PF_Index::bump_version();
		}
		wp_send_json_success(
			array(
				'color'  => $color,
				'source' => (string) get_term_meta( $term->term_id, self::META_SOURCE, true ),
			)
		);
	}
}
