<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Readiness_Model Class
 *
 * AI shopping readiness score out of 100 with a plain fix line per failed check, and the
 * llms.txt creator (physical file when writable, else served through a rewrite).
 *
 * Checks: attribute fill rates (setup scan transient wpf_setup_scan, else computed), product
 * structured data on, abilities registered, robots.txt allows GPTBot / ClaudeBot / PerplexityBot /
 * Google-Extended, llms.txt present, clean links on, canonical on.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

// Free (abilities) and Pro (agents) both ship this model under the same class name: whichever loads first serves both,
// the second file must not declare it again (that was a fatal error on every Pro store asking for /wpf/v1/readiness).
if ( class_exists( 'WooBeWoo_PF_Readiness_Model', false ) ) {
	return;
}

class WooBeWoo_PF_Readiness_Model extends WooBeWoo_PF_Model {

	const LLMS_OPTION = 'wpf_llms_txt';

	public function score() {
		$checks = array(
			$this->checkAttributeFill(),
			$this->checkProductSchema(),
			$this->checkAbilities(),
			$this->checkMcp(),
			$this->checkRobots(),
			$this->checkLlms(),
			$this->checkCleanLinks(),
			$this->checkCanonical(),
		);
		$checks = apply_filters( 'woobewoo_pf_readiness_checks', $checks );
		$total  = 0;
		$earned = 0;
		foreach ( $checks as $c ) {
			$total  += $c['weight'];
			$earned += $c['points'];
		}
		return array(
			'score'    => $total ? (int) round( $earned / $total * 100 ) : 0,
			'checks'   => array_values( $checks ),
			'mcp'      => WooBeWoo_PF_Abilities::mcp_endpoint(),
			'computed' => current_time( 'mysql' ),
		);
	}

	private function result( $id, $label, $weight, $ratio, $detail, $fix = '' ) {
		$ratio  = max( 0, min( 1, (float) $ratio ) );
		$status = $ratio >= 0.99 ? 'pass' : ( $ratio > 0 ? 'partial' : 'fail' );
		return array(
			'id'     => $id,
			'label'  => $label,
			'weight' => $weight,
			'points' => (int) round( $weight * $ratio ),
			'status' => $status,
			'detail' => $detail,
			'fix'    => 'pass' === $status ? '' : $fix,
		);
	}

	private function checkAttributeFill() {
		$scan  = get_transient( 'wpf_setup_scan' );
		$rates = array();
		if ( is_array( $scan ) && ! empty( $scan['attributes'] ) ) {
			foreach ( $scan['attributes'] as $a ) {
				if ( isset( $a['fill'] ) ) {
					$rates[] = (int) $a['fill'];
				}
			}
		}
		if ( empty( $rates ) ) {
			$rates = $this->computeFillRates();
		}
		if ( empty( $rates ) ) {
			return $this->result( 'fill', __( 'Products have attributes filled in', 'woo-product-filter' ), 20, 0, __( 'No product attributes found.', 'woo-product-filter' ), __( 'Add attributes like size, colour or material to your products. AI shoppers filter on them.', 'woo-product-filter' ) );
		}
		$avg = array_sum( $rates ) / count( $rates );
		return $this->result(
			'fill',
			__( 'Products have attributes filled in', 'woo-product-filter' ),
			20,
			$avg / 100,
			sprintf( /* translators: 1: average percent 2: count */ __( 'On average %1$d%% of products carry a value, across %2$d attributes.', 'woo-product-filter' ), (int) round( $avg ), count( $rates ) ),
			__( 'Fill the empty attributes on your products. Aim for 80% or more.', 'woo-product-filter' )
		);
	}

	public function computeFillRates() {
		global $wpdb;
		if ( ! function_exists( 'wc_get_attribute_taxonomies' ) ) {
			return array();
		}
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='product' AND post_status='publish'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! $total ) {
			return array();
		}
		$rates = array();
		foreach ( wc_get_attribute_taxonomies() as $attr ) {
			$tax     = wc_attribute_taxonomy_name( $attr->attribute_name );
			$n       = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT tr.object_id) FROM {$wpdb->term_relationships} tr INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id INNER JOIN {$wpdb->posts} p ON p.ID = tr.object_id WHERE tt.taxonomy = %s AND p.post_type = 'product' AND p.post_status = 'publish'", $tax ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rates[] = (int) round( $n / $total * 100 );
		}
		return $rates;
	}

	private function checkProductSchema() {
		$on = class_exists( 'WC_Structured_Data' ) && function_exists( 'WC' ) && isset( WC()->structured_data ) && false !== has_action( 'woocommerce_single_product_summary', array( WC()->structured_data, 'generate_product_data' ) );
		$seo = defined( 'WPSEO_VERSION' ) || class_exists( 'RankMath' );
		if ( ! $on && $seo ) {
			$on = true; // Yoast / Rank Math print Product schema themselves.
		}
		return $this->result(
			'schema',
			__( 'Product structured data is on', 'woo-product-filter' ),
			15,
			$on ? 1 : 0,
			$on ? __( 'Product schema is printed on product pages; filtered pages get CollectionPage and ItemList from this plugin.', 'woo-product-filter' ) : __( 'WooCommerce product schema is missing or removed by another plugin.', 'woo-product-filter' ),
			__( 'Make sure no plugin or theme removes WooCommerce structured data, or use Yoast / Rank Math product schema.', 'woo-product-filter' )
		);
	}

	private function checkAbilities() {
		$api  = function_exists( 'wp_register_ability' );
		$reg  = WooBeWoo_PF_Abilities::registry();
		$have = isset( $reg['facets-list'], $reg['products-filter'] );
		$registered = false;
		if ( $api ) {
			$registered = WooBeWoo_PF_Abilities::isRegistered( 'woobewoo/facets-list' ) && WooBeWoo_PF_Abilities::isRegistered( 'woobewoo/products-filter' );
		}
		$ratio = $have ? ( $api ? ( $registered ? 1 : 0.7 ) : 0.7 ) : 0;
		return $this->result(
			'abilities',
			__( 'Agent abilities are on', 'woo-product-filter' ),
			15,
			$ratio,
			$have
				? ( $api ? ( $registered ? __( 'facets-list and products-filter are registered with the WordPress Abilities API, REST and the built in MCP endpoint.', 'woo-product-filter' ) : __( 'facets-list and products-filter answer over REST and MCP; the Abilities API registration runs on the storefront.', 'woo-product-filter' ) ) : __( 'facets-list and products-filter answer over REST (wpf/v1) and the built in MCP endpoint. WordPress 6.9 or newer adds the Abilities API as well.', 'woo-product-filter' ) )
				: __( 'The read abilities are missing.', 'woo-product-filter' ),
			__( 'Update WordPress to 6.9 or newer so the abilities are also registered with the Abilities API. Agents can already use the MCP endpoint at /wp-json/wpf/v1/mcp.', 'woo-product-filter' )
		);
	}

	/**
	 * The built in MCP endpoint (wpf/v1/mcp) is registered. Cheap: it reads the route table, no HTTP call.
	 */
	private function checkMcp() {
		$mcp  = WooBeWoo_PF_Abilities::mcp_endpoint();
		$on   = class_exists( 'WooBeWoo_PF_MCP' );
		if ( $on && function_exists( 'rest_get_server' ) && did_action( 'rest_api_init' ) ) {
			$routes = rest_get_server()->get_routes();
			$on     = isset( $routes['/wpf/v1/mcp'] );
		}
		$extra = ! empty( $mcp['adapter']['active'] ) ? ' ' . sprintf( /* translators: %s: url */ __( 'The WordPress MCP Adapter also serves them at %s.', 'woo-product-filter' ), $mcp['adapter']['url'] ) : '';
		return $this->result(
			'mcp',
			__( 'MCP endpoint is on', 'woo-product-filter' ),
			5,
			$on ? 1 : 0,
			$on ? sprintf( /* translators: %s: url */ __( 'AI agents can connect to %s (tools: facets and product filter).', 'woo-product-filter' ), $mcp['url'] ) . $extra : __( 'The MCP route is not registered.', 'woo-product-filter' ),
			__( 'Make sure the abilities module is on and no plugin removes wpf/v1 REST routes.', 'woo-product-filter' )
		);
	}

	public function robotsTxt() {
		$file = ABSPATH . 'robots.txt';
		if ( file_exists( $file ) && is_readable( $file ) ) {
			return (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		$public  = (int) get_option( 'blog_public' );
		$default = "User-agent: *\n" . ( $public ? "Disallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n" : "Disallow: /\n" );
		return (string) apply_filters( 'robots_txt', $default, $public ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter, read to build the robots.txt preview
	}

	private function checkRobots() {
		$txt     = $this->robotsTxt();
		$bots    = array( 'GPTBot', 'ClaudeBot', 'PerplexityBot', 'Google-Extended' );
		$blocked = array();
		foreach ( preg_split( '/\n\s*\n/', str_replace( "\r", '', $txt ) ) as $block ) {
			$agents = array();
			$deny   = false;
			foreach ( explode( "\n", $block ) as $line ) {
				$line = trim( $line );
				if ( preg_match( '/^user-agent:\s*(.+)$/i', $line, $m ) ) {
					$agents[] = trim( $m[1] );
				} elseif ( preg_match( '/^disallow:\s*\/\s*$/i', $line ) ) {
					$deny = true;
				}
			}
			if ( $deny ) {
				foreach ( $bots as $bot ) {
					foreach ( $agents as $a ) {
						if ( 0 === strcasecmp( $a, $bot ) || '*' === $a ) {
							$blocked[] = $bot;
						}
					}
				}
			}
		}
		$blocked = array_values( array_unique( $blocked ) );
		return $this->result(
			'robots',
			__( 'robots.txt lets AI shoppers in', 'woo-product-filter' ),
			15,
			1 - count( $blocked ) / count( $bots ),
			$blocked ? sprintf( /* translators: %s: bot names */ __( 'Blocked: %s.', 'woo-product-filter' ), implode( ', ', $blocked ) ) : __( 'GPTBot, ClaudeBot, PerplexityBot and Google-Extended are allowed.', 'woo-product-filter' ),
			__( 'Remove the "Disallow: /" lines for these user agents in robots.txt (or your SEO plugin robots settings), and switch on "Allow AI shoppers" under Speed and SEO.', 'woo-product-filter' )
		);
	}

	private function checkLlms() {
		$exists = file_exists( ABSPATH . 'llms.txt' ) || '' !== (string) get_option( self::LLMS_OPTION, '' );
		return $this->result(
			'llms',
			__( 'llms.txt tells AI what the shop sells', 'woo-product-filter' ),
			10,
			$exists ? 1 : 0,
			$exists ? sprintf( /* translators: %s: url */ __( 'Present at %s', 'woo-product-filter' ), home_url( '/llms.txt' ) ) : __( 'No llms.txt at the site root.', 'woo-product-filter' ),
			__( 'Click "Create llms.txt". It lists the shop, the categories and the agent endpoints in one short file.', 'woo-product-filter' )
		);
	}

	private function opt( $key ) {
		$speed = WooBeWoo_PF_Frame::_()->getModule( 'speed' );
		if ( $speed && method_exists( $speed, 'isOn' ) ) {
			return $speed->isOn( $key );
		}
		return '1' === (string) WooBeWoo_PF_Frame::_()->getModule( 'options' )->getModel()->get( $key );
	}

	private function checkCleanLinks() {
		$on = $this->opt( 'wbw_clean_links' );
		return $this->result(
			'clean_links',
			__( 'Clean links are on', 'woo-product-filter' ),
			10,
			$on ? 1 : 0,
			$on ? __( 'Filtered pages use readable addresses like /shop/size-42/colour-red/.', 'woo-product-filter' ) : __( 'Filtered pages use long query strings.', 'woo-product-filter' ),
			__( 'Switch on "Clean links" under Speed and SEO.', 'woo-product-filter' )
		);
	}

	private function checkCanonical() {
		$on = $this->opt( 'canonical_links' );
		return $this->result(
			'canonical',
			__( 'Canonical links on filtered pages', 'woo-product-filter' ),
			15,
			$on ? 1 : 0,
			$on ? __( 'Filtered pages point search engines to the clean page (noindex, follow).', 'woo-product-filter' ) : __( 'Filtered pages have no canonical link from this plugin.', 'woo-product-filter' ),
			__( 'Switch on "Canonical and noindex on filtered pages" under Speed and SEO.', 'woo-product-filter' )
		);
	}

	/* ------------------------------------------------------------------ llms.txt */

	public function llmsContent() {
		$lines   = array();
		$lines[] = '# ' . wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$desc    = wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES );
		if ( $desc ) {
			$lines[] = '> ' . $desc;
		}
		$lines[] = '';
		$lines[] = '## Shop';
		$lines[] = '- [Shop](' . ( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ) . ')';
		$lines[] = '';
		$lines[] = '## Categories';
		$cats    = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => true,
				'number'     => 200,
				'orderby'    => 'count',
				'order'      => 'DESC',
			)
		);
		if ( ! is_wp_error( $cats ) ) {
			foreach ( $cats as $c ) {
				$link = get_term_link( $c );
				if ( ! is_wp_error( $link ) ) {
					$lines[] = '- [' . wp_specialchars_decode( $c->name, ENT_QUOTES ) . '](' . $link . '): ' . sprintf( /* translators: %d: count */ _n( '%d product', '%d products', (int) $c->count, 'woo-product-filter' ), (int) $c->count );
				}
			}
		}
		$lines[] = '';
		$lines[] = '## For agents';
		$lines[] = '- [Facets with counts](' . rest_url( 'wpf/v1/facets' ) . ')';
		$lines[] = '- [Product filter](' . rest_url( 'wpf/v1/products' ) . ')';
		$lines[] = '- [OpenAPI document](' . rest_url( 'wpf/v1/openapi' ) . ')';
		$mcp     = WooBeWoo_PF_Abilities::mcp_endpoint();
		$lines[] = '- [MCP endpoint (Streamable HTTP, JSON-RPC 2.0, tools: facets and product filter)](' . $mcp['url'] . ')';
		if ( ! empty( $mcp['adapter']['active'] ) ) {
			$lines[] = '- [MCP endpoint (WordPress MCP Adapter)](' . $mcp['adapter']['url'] . ')';
		}
		$lines = apply_filters( 'woobewoo_pf_llms_lines', $lines );
		return implode( "\n", $lines ) . "\n";
	}

	public function createLlms() {
		$content = $this->llmsContent();
		$written = false;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		global $wp_filesystem;
		if ( WP_Filesystem() && $wp_filesystem && 'direct' === $wp_filesystem->method && $wp_filesystem->is_writable( ABSPATH ) ) {
			$written = (bool) $wp_filesystem->put_contents( ABSPATH . 'llms.txt', $content, FS_CHMOD_FILE );
		}
		update_option( self::LLMS_OPTION, $content, false );
		if ( ! $written ) {
			// Served through the rewrite rule registered by the abilities module.
			update_option( 'wpf_links_flush_needed', '1', false );
		}
		return array(
			'written' => $written,
			'served'  => ! $written,
			'url'     => home_url( '/llms.txt' ),
			'content' => $content,
		);
	}
}
