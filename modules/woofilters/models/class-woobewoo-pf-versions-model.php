<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Versions_Model Class
 *
 * Version history and undo: a copy of the filter row is saved on every save (last 30 per
 * filter). Restore creates a new version first, then writes the old copy back.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Versions_Model extends WooBeWoo_PF_Model {

	const KEEP = 30;

	public function __construct() {
		$this->_setTbl( 'filter_versions' );
	}

	/**
	 * Save a copy of the filter row as it is in the database right now.
	 */
	public function snapshot( $filterId ) {
		$filterId = (int) $filterId;
		if ( ! $filterId || ! WooBeWoo_PF_Db::exist( '@__filter_versions' ) ) {
			return false;
		}
		$row = WooBeWoo_PF_Db::get( 'SELECT title, setting_data FROM `@__filters` WHERE id = %d', 'row', ARRAY_A, array( $filterId ) );
		if ( ! $row ) {
			return false;
		}
		// Skip when nothing changed since the last copy.
		$last = WooBeWoo_PF_Db::get( 'SELECT title, setting_data FROM `@__filter_versions` WHERE filter_id = %d ORDER BY id DESC LIMIT 1', 'row', ARRAY_A, array( $filterId ) );
		if ( $last && $last['title'] === $row['title'] && $last['setting_data'] === $row['setting_data'] ) {
			return true;
		}
		$ok = WooBeWoo_PF_Db::query(
			'INSERT INTO `@__filter_versions` (filter_id, title, setting_data, created, user_id) VALUES (%d, %s, %s, %s, %d)',
			false,
			array( $filterId, (string) $row['title'], (string) $row['setting_data'], current_time( 'mysql', true ), get_current_user_id() )
		);
		if ( $ok ) {
			$this->prune( $filterId );
		}
		return $ok;
	}

	/**
	 * Keep the last KEEP versions of a filter.
	 */
	public function prune( $filterId ) {
		$ids = WooBeWoo_PF_Db::get( 'SELECT id FROM `@__filter_versions` WHERE filter_id = %d ORDER BY id DESC LIMIT %d, 1000', 'col', ARRAY_A, array( (int) $filterId, self::KEEP ) );
		if ( ! empty( $ids ) ) {
			$ids = array_map( 'intval', $ids );
			WooBeWoo_PF_Db::query( 'DELETE FROM `@__filter_versions` WHERE id IN (' . implode( ',', $ids ) . ')' );
		}
	}

	/**
	 * Versions of a filter, newest first, with a short summary for the UI.
	 */
	public function listForFilter( $filterId ) {
		if ( ! WooBeWoo_PF_Db::exist( '@__filter_versions' ) ) {
			return array();
		}
		$rows = WooBeWoo_PF_Db::get( 'SELECT id, filter_id, title, created, user_id, setting_data FROM `@__filter_versions` WHERE filter_id = %d ORDER BY id ASC LIMIT %d', 'all', ARRAY_A, array( (int) $filterId, self::KEEP ) );
		$rows = is_array( $rows ) ? $rows : array();
		$out  = array();
		$prev = null;
		$me   = get_current_user_id();
		$n    = 0;
		foreach ( $rows as $row ) {
			++$n;
			$user    = $row['user_id'] ? get_userdata( (int) $row['user_id'] ) : false;
			$ts      = strtotime( $row['created'] . ' UTC' );
			$dayDiff = (int) floor( ( time() - $ts ) / DAY_IN_SECONDS );
			if ( 0 === $dayDiff ) {
				/* translators: %s: time */
				$when = sprintf( __( 'today %s', 'woo-product-filter' ), date_i18n( 'H:i', $ts ) );
			} elseif ( 1 === $dayDiff ) {
				/* translators: %s: time */
				$when = sprintf( __( 'yesterday %s', 'woo-product-filter' ), date_i18n( 'H:i', $ts ) );
			} else {
				$when = date_i18n( 'j M', $ts );
			}
			$out[] = array(
				'id'      => (int) $row['id'],
				'number'  => $n,
				'title'   => (string) $row['title'],
				'created' => $row['created'],
				'when'    => $when,
				'ago'     => human_time_diff( $ts, time() ),
				'user'    => $user ? ( (int) $user->ID === (int) $me ? __( 'you', 'woo-product-filter' ) : $user->display_name ) : __( 'smart setup', 'woo-product-filter' ),
				'blocks'  => $this->blocksSummary( $row['setting_data'] ),
				'summary' => $this->summarizeChange( $prev, $row ),
			);
			$prev = $row;
		}
		return array_reverse( $out );
	}

	/**
	 * Plain words for what changed between two versions ("switched On sale on",
	 * "changed Chips to Swatches", "drawer button text", "created").
	 *
	 * @since 4.0.0
	 */
	public function summarizeChange( $prevRow, $row ) {
		if ( ! $prevRow ) {
			return __( 'created', 'woo-product-filter' );
		}
		$a = woobewoo_pf_decode_settings( $prevRow['setting_data'] );
		$b = woobewoo_pf_decode_settings( $row['setting_data'] );
		$a = isset( $a['settings'] ) ? $a['settings'] : array();
		$b = isset( $b['settings'] ) ? $b['settings'] : array();
		$changes = array();
		if ( (string) $prevRow['title'] !== (string) $row['title'] ) {
			$changes[] = __( 'renamed', 'woo-product-filter' );
		}
		$blocksA = $this->blocksSummary( $prevRow['setting_data'] );
		$blocksB = $this->blocksSummary( $row['setting_data'] );
		foreach ( array_diff( $blocksB, $blocksA ) as $name ) {
			/* translators: %s: block name */
			$changes[] = sprintf( __( 'switched %s on', 'woo-product-filter' ), $name );
		}
		foreach ( array_diff( $blocksA, $blocksB ) as $name ) {
			/* translators: %s: block name */
			$changes[] = sprintf( __( 'switched %s off', 'woo-product-filter' ), $name );
		}
		$orderA = empty( $a['filters']['order'] ) ? array() : (array) WooBeWoo_PF_Utils::jsonDecode( $a['filters']['order'] );
		$orderB = empty( $b['filters']['order'] ) ? array() : (array) WooBeWoo_PF_Utils::jsonDecode( $b['filters']['order'] );
		$looks  = array( 'list' => __( 'Tick boxes', 'woo-product-filter' ), 'chips' => __( 'Chips', 'woo-product-filter' ), 'swatches' => __( 'Swatches', 'woo-product-filter' ), 'squares' => __( 'Squares', 'woo-product-filter' ), 'dropdown' => __( 'Dropdown', 'woo-product-filter' ), 'slider' => __( 'Slider', 'woo-product-filter' ), 'toggle' => __( 'Toggle', 'woo-product-filter' ), 'range_list' => __( 'Range list', 'woo-product-filter' ) );
		foreach ( $orderB as $i => $blk ) {
			if ( ! isset( $orderA[ $i ] ) || empty( $blk['settings'] ) || empty( $orderA[ $i ]['settings'] ) ) {
				continue;
			}
			$sa = $orderA[ $i ]['settings'];
			$sb = $blk['settings'];
			$ta = isset( $sa['display_type'] ) ? $sa['display_type'] : ( isset( $sa['f_frontend_type'] ) ? $sa['f_frontend_type'] : '' );
			$tb = isset( $sb['display_type'] ) ? $sb['display_type'] : ( isset( $sb['f_frontend_type'] ) ? $sb['f_frontend_type'] : '' );
			if ( $ta !== $tb && $tb ) {
				/* translators: 1: old look, 2: new look */
				$changes[] = sprintf( __( 'changed %1$s to %2$s', 'woo-product-filter' ), isset( $looks[ $ta ] ) ? $looks[ $ta ] : $ta, isset( $looks[ $tb ] ) ? $looks[ $tb ] : $tb );
			} elseif ( isset( $sa['f_title'], $sb['f_title'] ) && $sa['f_title'] !== $sb['f_title'] ) {
				$changes[] = __( 'block title', 'woo-product-filter' );
			}
		}
		if ( count( $orderA ) === count( $orderB ) && count( $orderA ) > 1 && wp_list_pluck( $orderA, 'uniqId' ) !== wp_list_pluck( $orderB, 'uniqId' ) && ! $changes ) {
			$changes[] = __( 'block order', 'woo-product-filter' );
		}
		$words = array(
			'mobile_drawer_text'   => __( 'drawer button text', 'woo-product-filter' ),
			'mobile_drawer'        => __( 'drawer on phones', 'woo-product-filter' ),
			'mobile_drawer_chips'  => __( 'chips in the drawer', 'woo-product-filter' ),
			'mobile_drawer_results' => __( 'results button', 'woo-product-filter' ),
			'remember_filters'     => __( 'remember last filters', 'woo-product-filter' ),
			'wbw_style'            => __( 'look', 'woo-product-filter' ),
			'wbw_accent'           => __( 'accent colour', 'woo-product-filter' ),
			'wbw_placement'        => __( 'where it shows', 'woo-product-filter' ),
			'display_on_page'      => __( 'which pages', 'woo-product-filter' ),
			'enable_ajax'          => __( 'update without reloading', 'woo-product-filter' ),
			'finder'               => __( 'step by step finder', 'woo-product-filter' ),
			'mobile_overrides'     => __( 'phone look', 'woo-product-filter' ),
		);
		foreach ( $words as $key => $label ) {
			$va = isset( $a[ $key ] ) ? $a[ $key ] : '';
			$vb = isset( $b[ $key ] ) ? $b[ $key ] : '';
			if ( $va !== $vb ) {
				$changes[] = $label;
			}
		}
		if ( ! $changes ) {
			$diff = 0;
			foreach ( array_unique( array_merge( array_keys( $a ), array_keys( $b ) ) ) as $k ) {
				if ( 'filters' === $k ) {
					continue;
				}
				if ( ( isset( $a[ $k ] ) ? $a[ $k ] : null ) !== ( isset( $b[ $k ] ) ? $b[ $k ] : null ) ) {
					++$diff;
				}
			}
			/* translators: %d: number of settings */
			$changes[] = $diff ? sprintf( _n( '%d setting changed', '%d settings changed', $diff, 'woo-product-filter' ), $diff ) : __( 'saved', 'woo-product-filter' );
		}
		return implode( ', ', array_slice( $changes, 0, 3 ) );
	}

	public function countForFilter( $filterId ) {
		if ( ! WooBeWoo_PF_Db::exist( '@__filter_versions' ) ) {
			return 0;
		}
		return (int) WooBeWoo_PF_Db::get( 'SELECT COUNT(*) FROM `@__filter_versions` WHERE filter_id = %d', 'one', ARRAY_A, array( (int) $filterId ) );
	}

	/**
	 * id => count, created of the latest version, for the filters list.
	 */
	public function statsForAll() {
		if ( ! WooBeWoo_PF_Db::exist( '@__filter_versions' ) ) {
			return array();
		}
		$rows = WooBeWoo_PF_Db::get( 'SELECT filter_id, COUNT(*) AS cnt, MAX(created) AS last, MAX(id) AS last_id FROM `@__filter_versions` GROUP BY filter_id' );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$user = (int) WooBeWoo_PF_Db::get( 'SELECT user_id FROM `@__filter_versions` WHERE id = %d', 'one', ARRAY_A, array( (int) $row['last_id'] ) );
			$out[ (int) $row['filter_id'] ] = array( 'count' => (int) $row['cnt'], 'last' => $row['last'], 'user' => $user );
		}
		return $out;
	}

	/**
	 * Restore a version: snapshot the current state, then write the old copy back.
	 */
	public function restore( $versionId ) {
		$version = WooBeWoo_PF_Db::get( 'SELECT * FROM `@__filter_versions` WHERE id = %d', 'row', ARRAY_A, array( (int) $versionId ) );
		if ( ! $version ) {
			$this->pushError( esc_html__( 'This version no longer exists.', 'woo-product-filter' ) );
			return false;
		}
		$filterId = (int) $version['filter_id'];
		if ( ! WooBeWoo_PF_Db::get( 'SELECT id FROM `@__filters` WHERE id = %d', 'one', ARRAY_A, array( $filterId ) ) ) {
			$this->pushError( esc_html__( 'The filter this version belongs to was deleted.', 'woo-product-filter' ) );
			return false;
		}
		$this->snapshot( $filterId );
		$ok = WooBeWoo_PF_Db::query(
			'UPDATE `@__filters` SET title = %s, setting_data = %s WHERE id = %d',
			false,
			array( (string) $version['title'], (string) $version['setting_data'], $filterId )
		);
		if ( ! $ok ) {
			$this->pushError( WooBeWoo_PF_Db::getError() );
			return false;
		}
		// The restored state is the newest version ("Live now"); the state it replaced stays one row below, so a
		// restore can itself be undone from History.
		$this->snapshot( $filterId );
		// Keep the meta_keys column in sync with the restored blocks.
		WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getFiltersMetaKeys( $filterId, true );
		do_action( 'woobewoo_pf_filter_restored', $filterId, (int) $versionId );
		return $filterId;
	}

	public function deleteForFilters( $ids ) {
		$ids = array_filter( array_map( 'intval', (array) $ids ) );
		if ( $ids && WooBeWoo_PF_Db::exist( '@__filter_versions' ) ) {
			WooBeWoo_PF_Db::query( 'DELETE FROM `@__filter_versions` WHERE filter_id IN (' . implode( ',', $ids ) . ')' );
		}
	}

	/**
	 * Block names from a serialized setting_data string.
	 */
	public function blocksSummary( $settingData ) {
		$names    = array();
		$settings = woobewoo_pf_decode_settings( $settingData );
		if ( empty( $settings['settings']['filters']['order'] ) ) {
			return $names;
		}
		$order = WooBeWoo_PF_Utils::jsonDecode( $settings['settings']['filters']['order'] );
		$all   = WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getAllFilters();
		foreach ( (array) $order as $block ) {
			if ( empty( $block['id'] ) ) {
				continue;
			}
			$name = ! empty( $block['settings']['f_title'] ) ? $block['settings']['f_title'] : '';
			// No own title: the name the shop shows ("Size", "Category"), not the 3.x block name ("Attribute", "Product categories").
			if ( '' === $name && class_exists( 'WooBeWoo_PF_Engine' ) && class_exists( 'WooBeWoo_PF_Frontend4' ) ) {
				$nb   = WooBeWoo_PF_Engine::normalize_block( $block );
				$name = $nb ? (string) WooBeWoo_PF_Frontend4::block_title( $block, $nb ) : '';
			}
			if ( '' === $name ) {
				$name = isset( $all[ $block['id'] ]['name'] ) ? $all[ $block['id'] ]['name'] : $block['id'];
			}
			$names[] = html_entity_decode( (string) $name, ENT_QUOTES );
		}
		return $names;
	}
}
