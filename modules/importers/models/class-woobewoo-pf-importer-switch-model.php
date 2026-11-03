<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Importer_Switch_Model Class
 *
 * The placement swap of "Switch from another filter": finds where the other plugin's filter is shown
 * (sidebar widgets, shortcodes and blocks in posts, pages, templates and Elementor data, its own
 * automatic shop placement), puts our filter in those places, can turn the other plugin off, and
 * keeps a snapshot of everything it changed so the whole switch can be undone in one click.
 *
 * @version 4.0.1
 * @since   4.0.1
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Importer_Switch_Model extends WooBeWoo_PF_Model {

	const OPT_LOG   = 'wpf_switch_log';
	const OUR_WIDGET = 'wpfwoofilterswidget';
	const MAX_LOG   = 20;

	/** Where the placement being checked sits: array( 'post' => id ) or array( 'sidebar' => id ), passed to setOfPlacement(). */
	private $ctx = array();

	/* ------------------------------------------------------------------ scan */

	/**
	 * Every place the set is shown. Rows: array( 'key', 'kind' => widget|post|elementor|auto, 'label', 'where', 'view', 'edit', 'count', 'note' ).
	 */
	public function scan( $mapper, $setId ) {
		$sig  = wp_parse_args( (array) $mapper->signatures(), array( 'widgets' => array(), 'shortcodes' => array(), 'blocks' => array(), 'products' => array(), 'productBlocks' => array() ) );
		$rows = array();
		foreach ( $this->widgetHits( $mapper, $sig, $setId ) as $sidebar => $ids ) {
			$rows[] = array(
				'key'   => 'widget:' . $sidebar,
				'kind'  => 'widget',
				'label' => $this->sidebarName( $sidebar ),
				'where' => __( 'Widget area', 'woo-product-filter' ),
				'count' => count( $ids ),
				'view'  => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
				'edit'  => admin_url( wp_is_block_theme() ? 'site-editor.php' : 'widgets.php' ),
				'note'  => '',
			);
		}
		foreach ( $this->siblingHits( $mapper, $sig, $setId ) as $sidebar => $hit ) {
			$rows[] = array(
				'key'   => 'widget:' . $sidebar,
				'kind'  => 'widget',
				'label' => $this->sidebarName( $sidebar ),
				'where' => __( 'Widget area', 'woo-product-filter' ),
				'count' => 1,
				'view'  => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
				'edit'  => admin_url( wp_is_block_theme() ? 'site-editor.php' : 'widgets.php' ),
				'note'  => __( 'Their widget here also showed this filter on other pages. Ours is added right after the filter that already replaced it; each shows on its own pages.', 'woo-product-filter' ),
			);
		}
		foreach ( $this->postHits( $mapper, $sig, $setId ) as $postId => $hit ) {
			$post   = get_post( $postId );
			$rows[] = array(
				'key'   => ( $hit['elementor'] ? 'elementor:' : 'post:' ) . $postId,
				'kind'  => $hit['elementor'] ? 'elementor' : 'post',
				'label' => $post && '' !== $post->post_title ? $post->post_title : '#' . $postId,
				'where' => $this->postTypeLabel( $post ),
				'count' => $hit['count'],
				'view'  => $post && in_array( $post->post_type, array( 'wp_template', 'wp_template_part' ), true ) ? '' : get_permalink( $postId ),
				'edit'  => get_edit_post_link( $postId, 'raw' ),
				'note'  => $hit['products'] ? __( 'Their product grid on this page is replaced with ours too, so it keeps working when their plugin is off.', 'woo-product-filter' ) : '',
			);
		}
		$auto = $mapper->autoPlacement( $setId );
		if ( $auto ) {
			$rows[] = array(
				'key'   => 'auto',
				'kind'  => 'auto',
				'label' => isset( $auto['label'] ) ? $auto['label'] : __( 'Shown automatically on the shop', 'woo-product-filter' ),
				'where' => __( 'Automatic placement', 'woo-product-filter' ),
				'count' => 1,
				'view'  => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
				'edit'  => '',
				'note'  => '',
			);
		}
		return $rows;
	}

	private function sidebarName( $id ) {
		global $wp_registered_sidebars;
		return isset( $wp_registered_sidebars[ $id ]['name'] ) ? $wp_registered_sidebars[ $id ]['name'] : $id;
	}

	private function postTypeLabel( $post ) {
		if ( ! $post ) {
			return '';
		}
		$o = get_post_type_object( $post->post_type );
		return $o ? $o->labels->singular_name : $post->post_type;
	}

	private static function idBase( $widgetId ) {
		return preg_match( '/^(.+)-(\d+)$/', $widgetId, $m ) ? array( $m[1], (int) $m[2] ) : array( $widgetId, 0 );
	}

	private function matchesSet( $mapper, $kind, $name, $data, $setId ) {
		// 4th argument: mappers that need it declare setOfPlacement( $kind, $name, $data, $ctx = array() ).
		// A mapper may answer one set id, '' (cannot tell: every set) or an array of set ids (a widget that shows a
		// different set of theirs on each page).
		$s = $mapper->setOfPlacement( $kind, $name, $data, $this->ctx );
		if ( is_array( $s ) ) {
			$s = array_map( 'strval', $s );
			return ! $s || '' === (string) $setId || in_array( (string) $setId, $s, true );
		}
		$s = (string) $s;
		return '' === $s || '' === (string) $setId || (string) $s === (string) $setId;
	}

	/**
	 * The placement names this set explicitly (one id, or one of an array of ids); '' (unknown) does not count.
	 */
	private function namesSet( $mapper, $kind, $name, $data, $setId ) {
		$s = $mapper->setOfPlacement( $kind, $name, $data, $this->ctx );
		if ( is_array( $s ) ) {
			return in_array( (string) $setId, array_map( 'strval', $s ), true );
		}
		return '' !== (string) $s && (string) $s === (string) $setId;
	}

	/**
	 * Widget operations of the live (not undone) switches of this rival, each with its 'set'.
	 */
	private function liveWidgetOps( $slug, $exceptId = '' ) {
		$out = array();
		foreach ( $this->log() as $snap ) {
			if ( ! empty( $snap['undone'] ) || $snap['rival'] !== $slug || $snap['id'] === $exceptId || empty( $snap['widgetOps'] ) ) {
				continue;
			}
			foreach ( (array) $snap['widgetOps'] as $op ) {
				$op['set'] = $snap['set'];
				$out[]     = $op;
			}
		}
		return $out;
	}

	/**
	 * Sidebars where an earlier live switch of the same rival already put our widget in place of theirs, and their
	 * (now inactive) widget also showed this set: array( sidebar => array( 'after' => our widget id, 'op' => that op ) ).
	 * A second widget of ours goes right after the first one, each with its own page rule.
	 */
	private function siblingHits( $mapper, $sig, $setId ) {
		$out = array();
		if ( empty( $sig['widgets'] ) || '' === (string) $setId ) {
			return $out;
		}
		$sidebars = get_option( 'sidebars_widgets', array() );
		$inactive = isset( $sidebars['wp_inactive_widgets'] ) ? (array) $sidebars['wp_inactive_widgets'] : array();
		$ops      = $this->liveWidgetOps( $mapper->slug() );
		foreach ( $ops as $op ) {
			$sb = $op['sidebar'];
			if ( isset( $out[ $sb ] ) || empty( $sidebars[ $sb ] ) || ! is_array( $sidebars[ $sb ] ) || ! in_array( $op['ours'], $sidebars[ $sb ], true ) || ! in_array( $op['group'], $inactive, true ) ) {
				continue;
			}
			$served = false;
			$last   = $op['ours'];
			foreach ( $ops as $o ) {
				if ( $o['sidebar'] !== $sb || $o['group'] !== $op['group'] || ! in_array( $o['ours'], $sidebars[ $sb ], true ) ) {
					continue;
				}
				if ( (string) $o['set'] === (string) $setId ) {
					$served = true;
				}
				if ( array_search( $o['ours'], $sidebars[ $sb ], true ) > array_search( $last, $sidebars[ $sb ], true ) ) {
					$last = $o['ours'];
				}
			}
			if ( $served ) {
				continue;
			}
			list( $base, $num ) = self::idBase( $op['group'] );
			$all       = get_option( 'widget_' . $base, array() );
			$inst      = isset( $all[ $num ] ) ? (array) $all[ $num ] : array();
			$this->ctx = array( 'sidebar' => $sb );
			if ( $this->namesSet( $mapper, 'widget', $base, $inst, $setId ) ) {
				$out[ $sb ] = array( 'after' => $last, 'op' => $op );
			}
		}
		$this->ctx = array();
		return $out;
	}

	/**
	 * array( sidebar => array( widget ids ) ) of their widgets for this set (inactive widgets left out).
	 */
	private function widgetHits( $mapper, $sig, $setId ) {
		$out = array();
		if ( empty( $sig['widgets'] ) ) {
			return $out;
		}
		$sidebars = get_option( 'sidebars_widgets', array() );
		foreach ( (array) $sidebars as $sidebar => $ids ) {
			if ( 'wp_inactive_widgets' === $sidebar || 'array_version' === $sidebar || ! is_array( $ids ) ) {
				continue;
			}
			foreach ( $ids as $wid ) {
				list( $base, $num ) = self::idBase( $wid );
				if ( ! in_array( $base, $sig['widgets'], true ) ) {
					continue;
				}
				$all       = get_option( 'widget_' . $base, array() );
				$inst      = isset( $all[ $num ] ) ? (array) $all[ $num ] : array();
				$this->ctx = array( 'sidebar' => $sidebar );
				if ( $this->matchesSet( $mapper, 'widget', $base, $inst, $setId ) ) {
					$out[ $sidebar ][] = $wid;
				}
			}
		}
		$this->ctx = array();
		return $out;
	}

	/**
	 * array( post id => array( 'count', 'elementor' => bool, 'products' => bool ) ).
	 */
	private function postHits( $mapper, $sig, $setId ) {
		global $wpdb;
		$out    = array();
		$needle = array();
		foreach ( $sig['shortcodes'] as $tag ) {
			$needle[] = $wpdb->prepare( 'post_content LIKE %s', '%[' . $wpdb->esc_like( $tag ) . '%' );
		}
		foreach ( $sig['blocks'] as $name ) {
			$needle[] = $wpdb->prepare( 'post_content LIKE %s', '%<!-- wp:' . $wpdb->esc_like( preg_replace( '#^core/#', '', $name ) ) . '%' );
		}
		if ( $needle ) {
			$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type NOT IN ('revision','nav_menu_item','customize_changeset','oembed_cache') AND post_status IN ('publish','draft','private','future','pending') AND (" . implode( ' OR ', $needle ) . ') LIMIT 500' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- parts prepared above
			foreach ( $ids as $id ) {
				$post      = get_post( $id );
				$this->ctx = array( 'post' => (int) $id );
				$n         = $this->countIn( $mapper, $sig, $setId, $post->post_content );
				if ( $n ) {
					$out[ (int) $id ] = array( 'count' => $n, 'elementor' => false, 'products' => $this->hasProducts( $sig, $post->post_content ) );
				}
			}
		}
		if ( $sig['shortcodes'] ) {
			$like = array();
			foreach ( $sig['shortcodes'] as $tag ) {
				$like[] = $wpdb->prepare( 'meta_value LIKE %s', '%[' . $wpdb->esc_like( $tag ) . '%' );
			}
			$ids = $wpdb->get_col( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND (" . implode( ' OR ', $like ) . ') LIMIT 500' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- parts prepared above
			foreach ( $ids as $id ) {
				$p = get_post( $id );
				if ( ! $p || 'revision' === $p->post_type || 'trash' === $p->post_status ) {
					continue;
				}
				$raw       = (string) get_post_meta( $id, '_elementor_data', true );
				$n         = 0;
				$this->ctx = array( 'post' => (int) $id );
				$this->walkStrings(
					json_decode( $raw, true ),
					function ( $s ) use ( &$n, $mapper, $sig, $setId ) {
						$n += $this->countShortcodes( $mapper, $sig, $setId, $s );
						return $s;
					}
				);
				if ( $n ) {
					$out[ (int) $id ] = array( 'count' => $n, 'elementor' => true, 'products' => $this->hasProducts( $sig, $raw ) );
				}
			}
		}
		$this->ctx = array();
		return $out;
	}

	private function hasProducts( $sig, $content ) {
		foreach ( (array) $sig['products'] as $tag ) {
			if ( false !== strpos( $content, '[' . $tag ) ) {
				return true;
			}
		}
		foreach ( (array) $sig['productBlocks'] as $name ) {
			if ( false !== strpos( $content, '<!-- wp:' . preg_replace( '#^core/#', '', $name ) . ' ' ) ) {
				return true;
			}
		}
		return false;
	}

	private function countIn( $mapper, $sig, $setId, $content ) {
		$n = $this->countShortcodes( $mapper, $sig, $setId, $content );
		if ( $sig['blocks'] && false !== strpos( $content, '<!-- wp:' ) ) {
			$this->walkBlocks(
				parse_blocks( $content ),
				function ( $b ) use ( &$n, $mapper, $sig, $setId ) {
					if ( in_array( $b['blockName'], $sig['blocks'], true ) && $this->matchesSet( $mapper, 'block', $b['blockName'], (array) $b['attrs'], $setId ) ) {
						// One per matched block: a shortcode in its saved HTML is the same filter, not a second place.
						$n += 1 - $this->countShortcodes( $mapper, $sig, $setId, serialize_block( $b ) );
						return true; // do not count blocks inside a matched one
					}
					return false;
				}
			);
		}
		return $n;
	}

	private function countShortcodes( $mapper, $sig, $setId, $content ) {
		$n = 0;
		if ( empty( $sig['shortcodes'] ) || ! is_string( $content ) || false === strpos( $content, '[' ) ) {
			return 0;
		}
		preg_match_all( '/' . get_shortcode_regex( $sig['shortcodes'] ) . '/', $content, $all, PREG_SET_ORDER );
		foreach ( $all as $m ) {
			if ( '[' === $m[1] && ']' === $m[6] ) {
				continue; // escaped [[shortcode]]
			}
			if ( $this->matchesSet( $mapper, 'shortcode', $m[2], (array) shortcode_parse_atts( $m[3] ), $setId ) ) {
				$n++;
			}
		}
		return $n;
	}

	private function walkBlocks( $blocks, $fn ) {
		foreach ( (array) $blocks as $b ) {
			if ( ! $fn( $b ) && ! empty( $b['innerBlocks'] ) ) {
				$this->walkBlocks( $b['innerBlocks'], $fn );
			}
		}
	}

	private function walkStrings( $data, $fn ) {
		if ( is_string( $data ) ) {
			return $fn( $data );
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$data[ $k ] = $this->walkStrings( $v, $fn );
			}
		}
		return $data;
	}

	/* ------------------------------------------------------------------ apply */

	/**
	 * Put filter $filterId where the set was. $keys = the scan rows the owner ticked; $deactivate = turn their plugin off.
	 * Returns the switch log entry (with 'id') or WP_Error.
	 */
	public function apply( $mapper, $setId, $filterId, $keys, $deactivate ) {
		$filterId = (int) $filterId;
		if ( ! $filterId ) {
			return new WP_Error( 'wpf_no_filter', __( 'Create the filter first.', 'woo-product-filter' ) );
		}
		$sig  = wp_parse_args( (array) $mapper->signatures(), array( 'widgets' => array(), 'shortcodes' => array(), 'blocks' => array(), 'products' => array(), 'productBlocks' => array() ) );
		$keys = array_map( 'strval', (array) $keys );
		$snap = array(
			'id'        => wp_generate_password( 10, false ),
			'time'      => time(),
			'user'      => get_current_user_id(),
			'rival'     => $mapper->slug(),
			'rivalName' => $mapper->name(),
			'set'       => (string) $setId,
			'filter'    => $filterId,
			'undone'    => false,
			'widgets'   => null,
			'posts'     => array(),
			'auto'      => array(),
			'plugins'   => array(),
			'main'      => $this->filterMain( $filterId ),
			'done'      => array(),
			'skipped'   => array(),
		);

		// Widgets: the first of their widgets in an area becomes ours, the others move to "Inactive widgets". A sibling set
		// whose rival widget an earlier live switch already replaced gets a second widget of ours right after that one.
		// Each change is recorded as an operation, so switches can be undone in any order.
		$picked = array_flip( array_map( function ( $k ) {
			return substr( $k, 7 );
		}, array_filter( $keys, function ( $k ) {
			return 0 === strpos( $k, 'widget:' );
		} ) ) );
		$hits = array_intersect_key( $this->widgetHits( $mapper, $sig, $setId ), $picked );
		$sibs = array_diff_key( array_intersect_key( $this->siblingHits( $mapper, $sig, $setId ), $picked ), $hits );
		if ( $hits || $sibs ) {
			$sidebars          = get_option( 'sidebars_widgets', array() );
			$oursRaw           = get_option( 'widget_' . self::OUR_WIDGET, null );
			$ours              = is_array( $oursRaw ) ? $oursRaw : array();
			$multiAdded        = ! isset( $ours['_multiwidget'] );
			$snap['widgetOps'] = array();
			foreach ( $hits as $sidebar => $ids ) {
				$new     = array();
				$num     = 0;
				$index   = array();
				$created = ! isset( $sidebars['wp_inactive_widgets'] );
				foreach ( (array) $sidebars[ $sidebar ] as $pos => $wid ) {
					if ( ! in_array( $wid, $ids, true ) ) {
						$new[] = $wid;
						continue;
					}
					if ( ! $num ) {
						$num          = self::nextNum( $ours );
						$ours[ $num ] = array( 'id' => (string) $filterId );
						$new[]        = self::OUR_WIDGET . '-' . $num;
					}
					$index[]                           = $pos;
					$sidebars['wp_inactive_widgets'][] = $wid;
				}
				$sidebars[ $sidebar ] = $new;
				$snap['widgetOps'][]  = array(
					'sidebar'         => $sidebar,
					'ours'            => self::OUR_WIDGET . '-' . $num,
					'num'             => $num,
					'group'           => $ids[0],
					'rivals'          => array_values( $ids ),
					'index'           => $index,
					'sibling'         => false,
					'oursAbsent'      => null === $oursRaw,
					'multiAdded'      => $multiAdded,
					'inactiveCreated' => $created,
				);
				$snap['done'][]       = 'widget:' . $sidebar;
			}
			foreach ( $sibs as $sidebar => $hit ) {
				$num          = self::nextNum( $ours );
				$ours[ $num ] = array( 'id' => (string) $filterId );
				$list         = array_values( (array) $sidebars[ $sidebar ] );
				$at           = array_search( $hit['after'], $list, true );
				array_splice( $list, false === $at ? count( $list ) : $at + 1, 0, array( self::OUR_WIDGET . '-' . $num ) );
				$sidebars[ $sidebar ] = $list;
				$op                   = $hit['op'];
				$snap['widgetOps'][]  = array(
					'sidebar'         => $sidebar,
					'ours'            => self::OUR_WIDGET . '-' . $num,
					'num'             => $num,
					'group'           => $op['group'],
					'rivals'          => $op['rivals'],
					'index'           => $op['index'],
					'sibling'         => true,
					'oursAbsent'      => $op['oursAbsent'],
					'multiAdded'      => $op['multiAdded'],
					'inactiveCreated' => $op['inactiveCreated'],
				);
				$snap['done'][]       = 'widget:' . $sidebar;
			}
			if ( ! isset( $ours['_multiwidget'] ) ) {
				$ours['_multiwidget'] = 1;
			}
			update_option( 'widget_' . self::OUR_WIDGET, $ours );
			update_option( 'sidebars_widgets', $sidebars );
		}

		// Posts, pages, templates and Elementor data.
		foreach ( $keys as $k ) {
			if ( ! preg_match( '/^(post|elementor):(\d+)$/', $k, $m ) ) {
				continue;
			}
			$postId    = (int) $m[2];
			$post      = get_post( $postId );
			$this->ctx = array( 'post' => $postId );
			if ( ! $post || ! current_user_can( 'edit_post', $postId ) ) {
				$snap['skipped'][] = array( 'key' => $k, 'why' => __( 'You cannot edit this page.', 'woo-product-filter' ) );
				continue;
			}
			if ( 'elementor' === $m[1] ) {
				$raw  = (string) get_post_meta( $postId, '_elementor_data', true );
				$data = json_decode( $raw, true );
				if ( ! is_array( $data ) ) {
					$snap['skipped'][] = array( 'key' => $k, 'why' => __( 'Elementor data could not be read.', 'woo-product-filter' ) );
					continue;
				}
				$first = true;
				$data  = $this->walkStrings(
					$data,
					function ( $s ) use ( $mapper, $sig, $setId, $filterId, &$first ) {
						return $this->replaceProducts( $mapper, $sig, $this->replaceShortcodes( $mapper, $sig, $setId, $filterId, $s, $first ) );
					}
				);
				$new = wp_json_encode( $data );
				if ( $new && $new !== $raw ) {
					$snap['posts'][ $postId ] = array( 'elementor' => $raw, 'elementorNew' => $new );
					update_post_meta( $postId, '_elementor_data', wp_slash( $new ) );
					delete_post_meta( $postId, '_elementor_element_cache' );
					if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
						\Elementor\Plugin::$instance->files_manager->clear_cache();
					}
					$snap['done'][] = $k;
				}
				continue;
			}
			$old     = $post->post_content;
			$first   = true;
			$content = $old;
			if ( ( $sig['blocks'] || $sig['productBlocks'] ) && false !== strpos( $content, '<!-- wp:' ) ) {
				$changed = false;
				$blocks  = $this->replaceBlocks( $mapper, $sig, $setId, $filterId, parse_blocks( $content ), $first, $changed );
				if ( $changed ) {
					$content = serialize_blocks( $blocks );
				}
			}
			$content = $this->replaceProducts( $mapper, $sig, $this->replaceShortcodes( $mapper, $sig, $setId, $filterId, $content, $first ) );
			if ( $content !== $old ) {
				$snap['posts'][ $postId ] = array( 'content' => $old, 'contentNew' => $content );
				wp_update_post( wp_slash( array( 'ID' => $postId, 'post_content' => $content ) ) );
				$snap['done'][] = $k;
			}
		}

		$this->ctx = array();

		// Our filter goes live where theirs was; their automatic shop placement becomes ours.
		$pages = $this->filterSetting( $filterId, 'wbw_import_pages' );
		$main  = array( 'display_on_page' => '' !== $pages ? $pages : 'both' );
		if ( 'both' !== $main['display_on_page'] ) {
			// A narrower rule (Shop only, some categories) must also hold for the widget and shortcode we put in place.
			$main['display_on_page_shortcode'] = '1';
		}
		if ( in_array( 'auto', $keys, true ) ) {
			$auto = $mapper->autoPlacement( $setId );
			if ( $auto ) {
				$main['wbw_placement']   = isset( $auto['place'] ) ? $auto['place'] : 'above';
				$main['display_on_page'] = isset( $auto['pages'] ) ? $auto['pages'] : 'both';
				$snap['auto']            = (array) $mapper->disableAutoPlacement( $setId );
				$snap['done'][]          = 'auto';
			}
		}
		if ( in_array( 'ourAuto', $keys, true ) && ! isset( $main['wbw_placement'] ) ) {
			// Their filter was nowhere we could find: ours goes above the products on the shop and categories.
			$main['wbw_placement'] = 'above';
			$snap['done'][]        = 'ourAuto';
		}
		$this->setFilterMain( $filterId, $main );

		if ( $deactivate ) {
			if ( ! function_exists( 'deactivate_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			foreach ( (array) $mapper->pluginFiles() as $file ) {
				if ( is_plugin_active( $file ) ) {
					deactivate_plugins( $file, false, false );
					$snap['plugins'][] = $file;
				}
			}
		}

		$this->markLive( $mapper->slug(), true );
		$log = $this->log();
		array_unshift( $log, $snap );
		update_option( self::OPT_LOG, array_slice( $log, 0, self::MAX_LOG ), false );
		return $snap;
	}

	/**
	 * Replace their shortcodes: the first one shown becomes ours, later ones of the same set are removed.
	 */
	private function replaceShortcodes( $mapper, $sig, $setId, $filterId, $content, &$first ) {
		if ( empty( $sig['shortcodes'] ) || ! is_string( $content ) || false === strpos( $content, '[' ) ) {
			return $content;
		}
		return preg_replace_callback(
			'/' . get_shortcode_regex( $sig['shortcodes'] ) . '/',
			function ( $m ) use ( $mapper, $setId, $filterId, &$first ) {
				if ( '[' === $m[1] && ']' === $m[6] ) {
					return $m[0];
				}
				if ( ! $this->matchesSet( $mapper, 'shortcode', $m[2], (array) shortcode_parse_atts( $m[3] ), $setId ) ) {
					return $m[0];
				}
				if ( $first ) {
					$first = false;
					return '[' . WPF_SHORTCODE . ' id=' . (int) $filterId . ']';
				}
				return '';
			},
			$content
		);
	}

	/**
	 * Their product grid shortcodes become ours (mapper decides the replacement).
	 */
	private function replaceProducts( $mapper, $sig, $content ) {
		if ( empty( $sig['products'] ) || ! is_string( $content ) || false === strpos( $content, '[' ) ) {
			return $content;
		}
		return preg_replace_callback(
			'/' . get_shortcode_regex( $sig['products'] ) . '/',
			function ( $m ) use ( $mapper ) {
				if ( '[' === $m[1] && ']' === $m[6] ) {
					return $m[0];
				}
				return $mapper->productsReplacement( $m[2], (array) shortcode_parse_atts( $m[3] ) );
			},
			$content
		);
	}

	private function replaceBlocks( $mapper, $sig, $setId, $filterId, $blocks, &$first, &$changed ) {
		$out = array();
		foreach ( (array) $blocks as $b ) {
			if ( in_array( $b['blockName'], $sig['productBlocks'], true ) ) {
				// Their product grid block: the mapper's replacement (a block array or a shortcode string).
				$new     = $mapper->blocksReplacement( $b['blockName'], (array) $b['attrs'] );
				$changed = true;
				if ( is_string( $new ) && '' !== $new ) {
					$new = array(
						'blockName'    => 'core/shortcode',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => $new,
						'innerContent' => array( $new ),
					);
				}
				if ( is_array( $new ) && ! empty( $new['blockName'] ) ) {
					$out[] = $new;
				}
				continue;
			}
			if ( in_array( $b['blockName'], $sig['blocks'], true ) && $this->matchesSet( $mapper, 'block', $b['blockName'], (array) $b['attrs'], $setId ) ) {
				$changed = true;
				if ( $first ) {
					$first = false;
					$sc    = '[' . WPF_SHORTCODE . ' id=' . (int) $filterId . ']';
					$out[] = array(
						'blockName'    => 'core/shortcode',
						'attrs'        => array(),
						'innerBlocks'  => array(),
						'innerHTML'    => $sc,
						'innerContent' => array( $sc ),
					);
				}
				continue;
			}
			if ( ! empty( $b['innerBlocks'] ) ) {
				$before           = count( $b['innerBlocks'] );
				$innerChanged     = false;
				$b['innerBlocks'] = $this->replaceBlocks( $mapper, $sig, $setId, $filterId, $b['innerBlocks'], $first, $innerChanged );
				if ( $innerChanged ) {
					$changed = true;
					$b       = self::fixInnerContent( $b, $before );
				}
			}
			$out[] = $b;
		}
		return $out;
	}

	/**
	 * innerContent holds one null per inner block; keep it in step when inner blocks were removed.
	 */
	private static function fixInnerContent( $b, $before ) {
		$after = count( $b['innerBlocks'] );
		if ( $after === $before ) {
			return $b;
		}
		$ic    = array();
		$nulls = 0;
		foreach ( (array) $b['innerContent'] as $c ) {
			if ( null === $c ) {
				if ( $nulls < $after ) {
					$ic[] = null;
				}
				$nulls++;
				continue;
			}
			$ic[] = $c;
		}
		$b['innerContent'] = $ic;
		return $b;
	}

	/* ------------------------------------------------------------------ undo */

	public function log() {
		$log = get_option( self::OPT_LOG, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Put everything back as it was before switch $id. Pages edited since the switch are left alone and reported.
	 */
	public function undo( $id ) {
		$log = $this->log();
		foreach ( $log as $i => $snap ) {
			if ( $snap['id'] !== $id ) {
				continue;
			}
			if ( ! empty( $snap['undone'] ) ) {
				return new WP_Error( 'wpf_undone', __( 'This switch was already undone.', 'woo-product-filter' ) );
			}
			$kept = array();
			if ( ! empty( $snap['widgetOps'] ) ) {
				$this->undoWidgetOps( $snap );
			} elseif ( isset( $snap['widgets'] ) && is_array( $snap['widgets'] ) ) {
				// Switches logged before widget operations were recorded.
				update_option( 'sidebars_widgets', $snap['widgets']['sidebars'] );
				update_option( 'widget_' . self::OUR_WIDGET, $snap['widgets']['ours'] );
			}
			foreach ( (array) $snap['posts'] as $postId => $p ) {
				$post = get_post( $postId );
				if ( ! $post ) {
					continue;
				}
				if ( isset( $p['elementor'] ) ) {
					$cur = (string) get_post_meta( $postId, '_elementor_data', true );
					if ( $cur !== $p['elementorNew'] ) {
						$kept[] = $post->post_title;
						continue;
					}
					update_post_meta( $postId, '_elementor_data', wp_slash( $p['elementor'] ) );
					delete_post_meta( $postId, '_elementor_element_cache' );
					continue;
				}
				if ( $post->post_content !== $p['contentNew'] ) {
					$kept[] = $post->post_title;
					continue;
				}
				wp_update_post( wp_slash( array( 'ID' => $postId, 'post_content' => $p['content'] ) ) );
			}
			foreach ( (array) $snap['auto'] as $opt => $val ) {
				if ( null === $val ) {
					delete_option( $opt );
				} else {
					update_option( $opt, $val );
				}
			}
			if ( $snap['plugins'] ) {
				if ( ! function_exists( 'activate_plugin' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				foreach ( $snap['plugins'] as $file ) {
					activate_plugin( $file, '', false, true );
				}
			}
			$this->setFilterMain( (int) $snap['filter'], ! empty( $snap['main'] ) ? $snap['main'] : array( 'display_on_page' => 'none', 'wbw_placement' => '' ) );
			$still = false;
			foreach ( $log as $j => $other ) {
				if ( $j !== $i && empty( $other['undone'] ) && $other['rival'] === $snap['rival'] ) {
					$still = true;
				}
			}
			if ( ! $still ) {
				$this->markLive( $snap['rival'], false );
			}
			$log[ $i ]['undone']     = time();
			$log[ $i ]['undoneKept'] = $kept;
			update_option( self::OPT_LOG, $log, false );
			return $log[ $i ];
		}
		return new WP_Error( 'wpf_no_switch', __( 'Switch not found.', 'woo-product-filter' ) );
	}

	/**
	 * Next free instance number of our widget.
	 */
	private static function nextNum( $ours ) {
		$ints = array_filter( array_keys( (array) $ours ), 'is_int' );
		return $ints ? max( 1, max( $ints ) + 1 ) : 2;
	}

	/**
	 * Take this switch's widgets of ours out again. Their widget comes back (where ours was, and out of "Inactive
	 * widgets") only when no other live switch still has a widget of ours in its place.
	 */
	private function undoWidgetOps( $snap ) {
		$sidebars = get_option( 'sidebars_widgets', array() );
		$oursRaw  = get_option( 'widget_' . self::OUR_WIDGET, null );
		$ours     = is_array( $oursRaw ) ? $oursRaw : array();
		$others   = $this->liveWidgetOps( $snap['rival'], $snap['id'] );
		foreach ( array_reverse( (array) $snap['widgetOps'] ) as $op ) {
			$sb   = $op['sidebar'];
			$list = isset( $sidebars[ $sb ] ) ? array_values( (array) $sidebars[ $sb ] ) : array();
			$pos  = array_search( $op['ours'], $list, true );
			if ( false !== $pos ) {
				array_splice( $list, $pos, 1 );
			}
			unset( $ours[ (int) $op['num'] ] );
			$held = false;
			foreach ( $others as $o ) {
				if ( $o['sidebar'] === $sb && $o['group'] === $op['group'] && in_array( $o['ours'], $list, true ) ) {
					$held = true;
				}
			}
			if ( ! $held ) {
				$rivals = array_values( (array) $op['rivals'] );
				$index  = array_values( (array) $op['index'] );
				$first  = false !== $pos ? $pos : ( isset( $index[0] ) ? min( (int) $index[0], count( $list ) ) : count( $list ) );
				array_splice( $list, $first, 0, array( $rivals[0] ) );
				$n = count( $rivals );
				for ( $k = 1; $k < $n; $k++ ) {
					$at = isset( $index[ $k ] ) ? min( (int) $index[ $k ], count( $list ) ) : count( $list );
					array_splice( $list, $at, 0, array( $rivals[ $k ] ) );
				}
				if ( isset( $sidebars['wp_inactive_widgets'] ) ) {
					$inactive = (array) $sidebars['wp_inactive_widgets'];
					foreach ( $rivals as $wid ) {
						$at = array_search( $wid, array_reverse( $inactive, true ), true );
						if ( false !== $at ) {
							unset( $inactive[ $at ] );
						}
					}
					$sidebars['wp_inactive_widgets'] = array_values( $inactive );
					if ( ! empty( $op['inactiveCreated'] ) && ! $sidebars['wp_inactive_widgets'] ) {
						unset( $sidebars['wp_inactive_widgets'] );
					}
				}
			}
			$sidebars[ $sb ] = $list;
		}
		$ints = array_filter( array_keys( $ours ), 'is_int' );
		$last = end( $snap['widgetOps'] );
		if ( ! $ints && ! empty( $last['oursAbsent'] ) ) {
			delete_option( 'widget_' . self::OUR_WIDGET );
		} else {
			if ( ! $ints && ! empty( $last['multiAdded'] ) ) {
				unset( $ours['_multiwidget'] );
			}
			update_option( 'widget_' . self::OUR_WIDGET, $ours );
		}
		update_option( 'sidebars_widgets', $sidebars );
	}

	/**
	 * Plugins switched from (their old filter links are redirected while listed). Autoloaded: read on every front request.
	 */
	private function markLive( $slug, $on ) {
		$live = (array) get_option( 'wpf_switch_live', array() );
		$live = $on ? array_values( array_unique( array_merge( $live, array( $slug ) ) ) ) : array_values( array_diff( $live, array( $slug ) ) );
		if ( $live ) {
			update_option( 'wpf_switch_live', $live, true );
		} else {
			delete_option( 'wpf_switch_live' );
		}
	}

	/* ------------------------------------------------------------------ our filter row */

	private function filterRow( $filterId ) {
		return WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->getById( (int) $filterId );
	}

	private function filterSetting( $filterId, $key ) {
		$row = $this->filterRow( $filterId );
		if ( ! $row ) {
			return '';
		}
		$s = woobewoo_pf_decode_settings( $row['setting_data'] );
		return isset( $s['settings'][ $key ] ) && is_scalar( $s['settings'][ $key ] ) ? (string) $s['settings'][ $key ] : '';
	}

	public function filterMain( $filterId ) {
		$row = $this->filterRow( $filterId );
		if ( ! $row ) {
			return array();
		}
		$s = woobewoo_pf_decode_settings( $row['setting_data'] );
		$m = isset( $s['settings'] ) && is_array( $s['settings'] ) ? $s['settings'] : array();
		return array(
			'display_on_page' => isset( $m['display_on_page'] ) ? $m['display_on_page'] : '',
			'wbw_placement'   => isset( $m['wbw_placement'] ) ? $m['wbw_placement'] : '',
			'display_on_page_shortcode' => isset( $m['display_on_page_shortcode'] ) ? $m['display_on_page_shortcode'] : '',
		);
	}

	/**
	 * Change main settings of a filter through the model (version snapshot kept).
	 */
	public function setFilterMain( $filterId, $changes ) {
		$row = $this->filterRow( $filterId );
		if ( ! $row ) {
			return false;
		}
		$s        = woobewoo_pf_decode_settings( $row['setting_data'] );
		$settings = isset( $s['settings'] ) && is_array( $s['settings'] ) ? $s['settings'] : array();
		foreach ( $changes as $k => $v ) {
			$settings[ $k ] = $v;
		}
		return WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' )->save(
			array(
				'id'       => (int) $filterId,
				'title'    => $row['title'],
				'settings' => $settings,
			)
		);
	}
}
