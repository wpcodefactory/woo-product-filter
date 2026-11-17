<?php
/**
 * Product Filter by WBW - WP-CLI command `wp wpf`
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_CLI_Command' ) ) {
	return;
}

/**
 * Manage Product Filter for WooCommerce by WBW.
 */
class WooBeWoo_PF_Cli_Command extends WP_CLI_Command {

	private function model() {
		return WooBeWoo_PF_Frame::_()->getModule( 'woofilters' )->getModel( 'woofilters' );
	}

	private function row( $id ) {
		$row = $this->model()->getById( (int) $id );
		if ( ! $row ) {
			WP_CLI::error( sprintf( 'Filter %d not found.', (int) $id ) );
		}
		$row['settings'] = woobewoo_pf_decode_settings( $row['setting_data'] );
		$row['settings'] = isset( $row['settings']['settings'] ) ? $row['settings']['settings'] : array();
		$row['blocks']   = array();
		if ( ! empty( $row['settings']['filters']['order'] ) ) {
			$order = WooBeWoo_PF_Utils::jsonDecode( $row['settings']['filters']['order'] );
			foreach ( (array) $order as $b ) {
				$row['blocks'][] = array(
					'id'    => isset( $b['id'] ) ? $b['id'] : '',
					'title' => isset( $b['settings']['f_title'] ) ? $b['settings']['f_title'] : '',
					'type'  => isset( $b['settings']['f_frontend_type'] ) ? $b['settings']['f_frontend_type'] : '',
					'list'  => isset( $b['settings']['f_list'] ) ? $b['settings']['f_list'] : '',
				);
			}
		}
		unset( $row['setting_data'] );
		return $row;
	}

	/**
	 * Manage filters.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : list | get <id> | create --title= --blocks= | update <id> --set key=value | delete <id> | export <id> --file= | import --file=
	 *
	 * [<id>]
	 * : Filter id (get, update, delete, export).
	 *
	 * [--title=<title>]
	 * : Title for create.
	 *
	 * [--blocks=<blocks>]
	 * : Comma list (category,size,color,price,stock,onsale,rating,sortby) or JSON array of block objects.
	 *
	 * [--set=<pairs>]
	 * : Comma list of key=value main settings for update (e.g. display_on_page=shop,enable_ajax=1).
	 *
	 * [--file=<file>]
	 * : JSON file path for export / import.
	 *
	 * [--format=<format>]
	 * : table | json | csv | yaml (list, get).
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpf filter list
	 *     wp wpf filter create --title="Shop" --blocks=category,size,color,price
	 *     wp wpf filter update 3 --set display_on_page=shop,enable_ajax=1
	 *     wp wpf filter export 3 --file=filter-3.json
	 *     wp wpf filter import --file=filter-3.json
	 *
	 * @subcommand filter
	 */
	public function filter( $args, $assoc ) {
		$action = isset( $args[0] ) ? $args[0] : 'list';
		$id     = isset( $args[1] ) ? (int) $args[1] : 0;
		$format = isset( $assoc['format'] ) ? $assoc['format'] : 'table';
		switch ( $action ) {
			case 'list':
				$rows = $this->model()->setSelectFields( 'id, title, setting_data' )->getFromTbl();
				$out  = array();
				foreach ( (array) $rows as $r ) {
					$s     = woobewoo_pf_decode_settings( $r['setting_data'] );
					$s     = isset( $s['settings'] ) ? $s['settings'] : array();
					$order = ! empty( $s['filters']['order'] ) ? WooBeWoo_PF_Utils::jsonDecode( $s['filters']['order'] ) : array();
					$out[] = array(
						'id'       => (int) $r['id'],
						'title'    => $r['title'],
						'blocks'   => implode( ',', array_map( function ( $b ) {
							return isset( $b['id'] ) ? str_replace( 'wpf', '', $b['id'] ) : '';
						}, (array) $order ) ),
						'shows_on' => isset( $s['display_on_page'] ) ? $s['display_on_page'] : 'shop',
						'ajax'     => ! empty( $s['enable_ajax'] ) ? 'yes' : 'no',
					);
				}
				WP_CLI\Utils\format_items( $format, $out, array( 'id', 'title', 'blocks', 'shows_on', 'ajax' ) );
				break;

			case 'get':
				if ( ! $id ) {
					WP_CLI::error( 'Give a filter id.' );
				}
				$row = $this->row( $id );
				if ( 'json' === $format ) {
					WP_CLI::line( wp_json_encode( $row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
				} else {
					WP_CLI::line( sprintf( '#%d %s', $row['id'], $row['title'] ) );
					WP_CLI\Utils\format_items( 'table', $row['blocks'], array( 'id', 'title', 'type', 'list' ) );
					$main = array();
					foreach ( $row['settings'] as $k => $v ) {
						if ( 'filters' !== $k && is_scalar( $v ) ) {
							$main[] = array( 'key' => $k, 'value' => (string) $v );
						}
					}
					WP_CLI\Utils\format_items( 'table', $main, array( 'key', 'value' ) );
				}
				break;

			case 'create':
				$title  = isset( $assoc['title'] ) ? $assoc['title'] : 'Filter ' . gmdate( 'Y-m-d H:i' );
				$blocks = isset( $assoc['blocks'] ) ? $assoc['blocks'] : 'category,price';
				$blocks = $this->parseBlocks( $blocks );
				$main   = isset( $assoc['set'] ) ? $this->parsePairs( $assoc['set'] ) : array();
				$result = WooBeWoo_PF_Cli::createFilter( $title, $blocks, $main );
				if ( is_wp_error( $result ) ) {
					WP_CLI::error( $result->get_error_message() );
				}
				WP_CLI::success( sprintf( 'Created filter %d. Shortcode: [wpf-filters id="%d"]', $result, $result ) );
				break;

			case 'update':
				if ( ! $id ) {
					WP_CLI::error( 'Give a filter id.' );
				}
				$row  = $this->model()->getById( $id );
				if ( ! $row ) {
					WP_CLI::error( sprintf( 'Filter %d not found.', $id ) );
				}
				$pairs = isset( $assoc['set'] ) ? $this->parsePairs( $assoc['set'] ) : array();
				if ( empty( $pairs ) && empty( $assoc['title'] ) && empty( $assoc['blocks'] ) ) {
					WP_CLI::error( 'Nothing to change. Use --set key=value, --title or --blocks.' );
				}
				$settings = woobewoo_pf_decode_settings( $row['setting_data'] );
				$settings = isset( $settings['settings'] ) ? $settings['settings'] : array();
				foreach ( WooBeWoo_PF_Cli::sanitizeMain( $pairs ) as $k => $v ) {
					$settings[ $k ] = $v;
				}
				if ( ! empty( $assoc['blocks'] ) ) {
					$order = WooBeWoo_PF_Cli::blocksToOrder( $this->parseBlocks( $assoc['blocks'] ) );
					if ( empty( $order ) ) {
						WP_CLI::error( 'No valid block in --blocks.' );
					}
					$settings['filters'] = array( 'order' => wp_json_encode( $order ) );
				}
				$saved = $this->model()->save(
					array(
						'id'       => $id,
						'title'    => ! empty( $assoc['title'] ) ? sanitize_text_field( $assoc['title'] ) : $row['title'],
						'settings' => $settings,
					)
				);
				if ( ! $saved ) {
					WP_CLI::error( implode( ' ', (array) $this->model()->getErrors() ) );
				}
				WP_CLI::success( sprintf( 'Updated filter %d (%d setting(s)).', $id, count( $pairs ) ) );
				break;

			case 'delete':
				if ( ! $id ) {
					WP_CLI::error( 'Give a filter id.' );
				}
				$row = $this->model()->getById( $id );
				if ( ! $row ) {
					WP_CLI::error( sprintf( 'Filter %d not found.', $id ) );
				}
				$this->model()->delete( array( 'id' => $id ) );
				if ( method_exists( $this->model(), 'getVersions' ) ) {
					$versions = $this->model()->getVersions();
					if ( $versions && method_exists( $versions, 'cleanup' ) ) {
						$versions->cleanup( $id );
					}
				}
				do_action( 'woobewoo_pf_filter_deleted', $id );
				WP_CLI::success( sprintf( 'Deleted filter %d.', $id ) );
				break;

			case 'export':
				if ( ! $id ) {
					WP_CLI::error( 'Give a filter id.' );
				}
				$row  = $this->model()->getById( $id );
				if ( ! $row ) {
					WP_CLI::error( sprintf( 'Filter %d not found.', $id ) );
				}
				$data = array(
					'plugin'   => 'woo-product-filter',
					'version'  => WPF_VERSION,
					'exported' => gmdate( 'c' ),
					'filters'  => array(
						array(
							'title'    => $row['title'],
							'settings' => woobewoo_pf_decode_settings( $row['setting_data'] ),
						),
					),
				);
				$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
				if ( ! empty( $assoc['file'] ) ) {
					if ( false === file_put_contents( $assoc['file'], $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
						WP_CLI::error( 'Could not write ' . $assoc['file'] );
					}
					WP_CLI::success( sprintf( 'Exported filter %d to %s', $id, $assoc['file'] ) );
				} else {
					WP_CLI::line( $json );
				}
				break;

			case 'import':
				if ( empty( $assoc['file'] ) || ! is_readable( $assoc['file'] ) ) {
					WP_CLI::error( 'Give a readable JSON file with --file=.' );
				}
				$data = json_decode( (string) file_get_contents( $assoc['file'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				if ( ! is_array( $data ) ) {
					WP_CLI::error( 'The file is not valid JSON.' );
				}
				$items = isset( $data['filters'] ) && is_array( $data['filters'] ) ? $data['filters'] : ( isset( $data['settings'] ) ? array( $data ) : array() );
				if ( empty( $items ) ) {
					WP_CLI::error( 'No filters found in the file (expected {"filters":[{"title":..,"settings":{..}}]}).' );
				}
				$ids = array();
				foreach ( $items as $item ) {
					$settings = isset( $item['settings']['settings'] ) ? $item['settings']['settings'] : ( isset( $item['settings'] ) ? $item['settings'] : null );
					if ( ! is_array( $settings ) || empty( $settings['filters']['order'] ) ) {
						WP_CLI::warning( 'Skipped an item without blocks.' );
						continue;
					}
					// Only known setting shapes are kept: scalars and the filters order (no raw SQL, model insert only).
					$clean = WooBeWoo_PF_Cli::sanitizeMain( array_diff_key( $settings, array( 'filters' => 1 ) ) );
					$order = WooBeWoo_PF_Utils::jsonDecode( $settings['filters']['order'] );
					if ( ! is_array( $order ) ) {
						WP_CLI::warning( 'Skipped an item with a broken block list.' );
						continue;
					}
					$clean['filters'] = array( 'order' => wp_json_encode( $order ) );
					$newId = $this->model()->save( array( 'title' => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : 'Imported filter', 'settings' => $clean ) );
					if ( $newId ) {
						$ids[] = (int) $newId;
					}
				}
				if ( empty( $ids ) ) {
					WP_CLI::error( 'Nothing imported.' );
				}
				WP_CLI::success( sprintf( 'Imported %d filter(s): %s', count( $ids ), implode( ', ', $ids ) ) );
				break;

			default:
				WP_CLI::error( 'Unknown action. Use list, get, create, update, delete, export or import.' );
		}
	}

	private function parseBlocks( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array();
		}
		if ( '[' === $raw[0] || '{' === $raw[0] ) {
			$d = json_decode( $raw, true );
			if ( ! is_array( $d ) ) {
				WP_CLI::error( '--blocks is not valid JSON.' );
			}
			return isset( $d['type'] ) ? array( $d ) : $d;
		}
		$known  = array( 'category', 'tags', 'tag', 'brand', 'price', 'price_range', 'stock', 'onsale', 'sale', 'featured', 'rating', 'sortby', 'sort', 'search', 'author' );
		if ( class_exists( 'WooBeWoo_PF_Blocks' ) ) {
			$known = array_merge( $known, array_keys( WooBeWoo_PF_Blocks::cli_words() ) ); // registered blocks' own words
		}
		$blocks = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $raw ) ) ) as $word ) {
			if ( in_array( $word, $known, true ) ) {
				$blocks[] = array( 'type' => $word );
			} else {
				$blocks[] = array( 'type' => 'attribute', 'taxonomy' => $word ); // size, color, pa_material...
			}
		}
		return $blocks;
	}

	private function parsePairs( $raw ) {
		$out = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) $raw ) ) ) as $pair ) {
			if ( false === strpos( $pair, '=' ) ) {
				continue;
			}
			list( $k, $v ) = array_map( 'trim', explode( '=', $pair, 2 ) );
			$out[ $k ]     = $v;
		}
		return $out;
	}

	/**
	 * Product index.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : rebuild | status
	 *
	 * ## EXAMPLES
	 *
	 *     wp wpf index status
	 *     wp wpf index rebuild
	 *
	 * @subcommand index
	 */
	public function index( $args, $assoc ) {
		$action = isset( $args[0] ) ? $args[0] : 'status';
		if ( 'rebuild' === $action ) {
			WP_CLI::line( 'Rebuilding the product index...' );
			$start = microtime( true );
			if ( class_exists( 'WooBeWoo_PF_Index' ) && method_exists( 'WooBeWoo_PF_Index', 'rebuild_now' ) ) {
				try {
					WooBeWoo_PF_Index::rebuild_now();
				} catch ( Throwable $e ) {
					WP_CLI::warning( 'Engine index rebuild failed: ' . $e->getMessage() );
				}
			}
			$meta = WooBeWoo_PF_Frame::_()->getModule( 'meta' );
			if ( $meta ) {
				$meta->getModel()->recalcMetaValues();
			}
			$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
			if ( $speed && method_exists( $speed, 'bumpCacheSalt' ) ) {
				$speed->bumpCacheSalt();
			}
			WP_CLI::success( sprintf( 'Index rebuilt in %.1fs.', microtime( true ) - $start ) );
		}
		$this->indexStatus();
	}

	private function indexStatus() {
		$rows  = array();
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		if ( $speed && method_exists( $speed, 'indexHealth' ) ) {
			foreach ( $speed->indexHealth() as $k => $v ) {
				$rows[] = array( 'key' => $k, 'value' => is_scalar( $v ) ? (string) $v : wp_json_encode( $v ) );
			}
		}
		if ( class_exists( 'WooBeWoo_PF_Index' ) && method_exists( 'WooBeWoo_PF_Index', 'health' ) ) {
			try {
				foreach ( (array) WooBeWoo_PF_Index::health() as $k => $v ) {
					$rows[] = array( 'key' => 'engine.' . $k, 'value' => is_scalar( $v ) ? (string) $v : wp_json_encode( $v ) );
				}
			} catch ( Throwable $e ) {
				$rows[] = array( 'key' => 'engine.error', 'value' => $e->getMessage() );
			}
		}
		$schema = WooBeWoo_PF_Frame::_()->getModule( 'schema' );
		if ( $schema ) {
			$rows[] = array( 'key' => 'version', 'value' => $schema->indexVersion() );
		}
		WP_CLI\Utils\format_items( 'table', $rows, array( 'key', 'value' ) );
	}

	/**
	 * Counts cache.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : clear
	 *
	 * @subcommand cache
	 */
	public function cache( $args, $assoc ) {
		global $wpdb;
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		if ( $speed && method_exists( $speed, 'bumpCacheSalt' ) ) {
			$speed->bumpCacheSalt();
		}
		$n = (int) $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpf_cnt_%' OR option_name LIKE '_transient_timeout_wpf_cnt_%' OR option_name LIKE '_transient_wpf_rl_%' OR option_name LIKE '_transient_timeout_wpf_rl_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( class_exists( 'WooBeWoo_PF_Index' ) && method_exists( 'WooBeWoo_PF_Index', 'bump_version' ) ) {
			try {
				WooBeWoo_PF_Index::bump_version();
			} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// The engine is optional here.
			}
		}
		do_action( 'woobewoo_pf_cache_cleared' );
		WP_CLI::success( sprintf( 'Cache cleared (%d transient rows removed, counts salt bumped).', $n ) );
	}

	/**
	 * Diagnostics as JSON (same data as the Help screen download).
	 *
	 * @subcommand diagnostics
	 */
	public function diagnostics( $args, $assoc ) {
		WP_CLI::line( wp_json_encode( WooBeWoo_PF_Cli::diagnostics(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	/**
	 * Pretty links.
	 *
	 * ## OPTIONS
	 *
	 * <action>
	 * : flush
	 *
	 * @subcommand links
	 */
	public function links( $args, $assoc ) {
		$links = WooBeWoo_PF_Frame::_()->getModule( 'links' );
		if ( $links && method_exists( $links, 'registerRewrites' ) ) {
			$links->registerRewrites();
		}
		flush_rewrite_rules( false );
		delete_option( 'wpf_links_flush_needed' );
		$rules = get_option( 'rewrite_rules', array() );
		$n     = 0;
		foreach ( (array) $rules as $target ) {
			if ( false !== strpos( $target, 'wpf_pretty=' ) || false !== strpos( $target, 'wpf_short=' ) ) {
				$n++;
			}
		}
		WP_CLI::success( sprintf( 'Rewrite rules flushed. %d Product Filter rules registered.', $n ) );
	}
}
