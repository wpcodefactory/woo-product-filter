<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_Abilities Class
 *
 * AI shopping abilities in Free: registers woobewoo/facets-list and woobewoo/products-filter with
 * the WordPress Abilities API (wp_register_ability on wp_abilities_api_init), mirrors them as REST
 * wpf/v1/facets and wpf/v1/products, serves an OpenAPI 3.1 document (wpf/v1/openapi) built from a
 * registry that Pro extends through `woobewoo_pf_abilities_registry`, the AI shopping readiness score
 * (wpf/v1/readiness) and a one click llms.txt creator.
 *
 * Pro reads `woobewoo_pf_free_abilities` (the ability names Free registers) and skips them.
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_Abilities extends WooBeWoo_PF_Module {

	const NS         = 'woobewoo';
	const RATE_LIMIT = 120;

	private static $instance = null;

	public function init() {
		parent::init();
		self::$instance = $this;
		add_filter( 'woobewoo_pf_free_abilities', array( $this, 'freeAbilityNames' ) );
		add_action( 'rest_api_init', array( $this, 'registerRest' ) );
		// Built in MCP server (wpf/v1/mcp) and the [wpf-mcp-connect] shortcode.
		require_once __DIR__ . '/class-woobewoo-pf-mcp.php';
		WooBeWoo_PF_MCP::init();
		if ( function_exists( 'wp_register_ability' ) ) {
			add_action( 'wp_abilities_api_categories_init', array( $this, 'registerCategory' ) );
			if ( did_action( 'wp_abilities_api_init' ) ) {
				$this->registerAbilities();
			} else {
				add_action( 'wp_abilities_api_init', array( $this, 'registerAbilities' ) );
			}
		}
		// llms.txt served through a rewrite when the root file could not be written.
		add_action( 'init', array( $this, 'llmsRewrite' ), 20 );
		add_filter( 'query_vars', array( $this, 'queryVars' ) );
		if ( ! is_admin() ) {
			add_action( 'template_redirect', array( $this, 'maybeServeLlms' ), 0 );
		}
		add_filter( 'woobewoo_pf_selftest_rows', array( $this, 'selfTestRows' ) );
	}

	public static function instance() {
		return self::$instance ? self::$instance : WooBeWoo_PF_Frame::_()->getModule( 'abilities' );
	}

	/* ------------------------------------------------------------------ registry */

	/**
	 * Ability names Free registers (Pro skips them).
	 */
	public function freeAbilityNames( $names ) {
		return array_values( array_unique( array_merge( (array) $names, array( 'facets-list', 'products-filter' ) ) ) );
	}

	/**
	 * The registry: name => definition. Each definition has label, description, input, output
	 * (JSON schemas), callback, permission ('public' or a capability), readonly, rest (method, path).
	 * Pro adds its write abilities through `woobewoo_pf_abilities_registry`.
	 */
	public static function registry() {
		$self    = self::instance();
		$model   = $self ? $self->getModel() : null;
		$schemas = $model ? $model->schemas() : array();
		$reg     = array();
		if ( $model ) {
			$reg['facets-list'] = array(
				'label'       => __( 'List facets', 'woo-product-filter' ),
				'description' => __( 'Attributes, categories, tags, brands, price range and stock values with product counts, optionally narrowed by a category or any filter selection.', 'woo-product-filter' ),
				'input'       => $schemas['facets-list']['input'],
				'output'      => $schemas['facets-list']['output'],
				'callback'    => array( $model, 'facetsList' ),
				'permission'  => 'public',
				'readonly'    => true,
				'rest'        => array( 'methods' => array( 'GET' ), 'path' => '/facets' ),
			);
			$reg['products-filter'] = array(
				'label'       => __( 'Filter products', 'woo-product-filter' ),
				'description' => __( 'Filter products by attributes, category, tag, brand, price range, stock, sale and rating. Variation aware: returns the matching variation and its stock when attributes are given.', 'woo-product-filter' ),
				'input'       => $schemas['products-filter']['input'],
				'output'      => $schemas['products-filter']['output'],
				'callback'    => array( $model, 'productsFilter' ),
				'permission'  => 'public',
				'readonly'    => true,
				'rest'        => array( 'methods' => array( 'GET', 'POST' ), 'path' => '/products' ),
			);
		}
		return apply_filters( 'woobewoo_pf_abilities_registry', $reg );
	}

	public function registerCategory() {
		if ( function_exists( 'wp_register_ability_category' ) ) {
			wp_register_ability_category(
				self::NS,
				array(
					'label'       => __( 'Product Filter by WBW', 'woo-product-filter' ),
					'description' => __( 'Facets and product filtering for WooCommerce shops.', 'woo-product-filter' ),
				)
			);
		}
	}

	public function registerAbilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}
		$free = apply_filters( 'woobewoo_pf_free_abilities', array() );
		foreach ( self::registry() as $name => $d ) {
			if ( ! in_array( $name, $free, true ) ) {
				continue; // Pro registers its own.
			}
			if ( self::isRegistered( self::NS . '/' . $name ) ) {
				continue;
			}
			wp_register_ability(
				self::NS . '/' . $name,
				array(
					'label'               => $d['label'],
					'description'         => $d['description'],
					'category'            => self::NS,
					'input_schema'        => $d['input'],
					'output_schema'       => $d['output'],
					'execute_callback'    => 'public' === $d['permission'] ? $this->counted( $d['callback'] ) : $d['callback'],
					'permission_callback' => 'public' === $d['permission'] ? array( $this, 'abilityPermission' ) : array( $this, 'canManage' ),
					'meta'                => array(
						'annotations'  => array(
							'readonly'    => ! empty( $d['readonly'] ),
							'destructive' => false,
							'idempotent'  => ! empty( $d['readonly'] ),
						),
						'show_in_rest' => true,
					),
				)
			);
		}
	}

	/**
	 * Whether an ability name is registered (without the "not found" notice wp_get_ability() raises).
	 */
	public static function isRegistered( $name ) {
		if ( ! class_exists( 'WP_Abilities_Registry' ) ) {
			return false;
		}
		$registry = WP_Abilities_Registry::get_instance();
		if ( method_exists( $registry, 'is_registered' ) ) {
			return (bool) $registry->is_registered( $name );
		}
		if ( function_exists( 'wp_get_abilities' ) ) {
			return array_key_exists( $name, (array) wp_get_abilities() );
		}
		return false;
	}

	/**
	 * Public read with a per IP rate limit (120 a minute). Admins are never limited.
	 */
	public function abilityPermission() {
		return $this->rateLimit();
	}

	/**
	 * Check the limit (permission callbacks run more than once per request, for example for the
	 * Allow header), count only when $count is true (done once in the callback itself).
	 */
	public function rateLimit( $limit = self::RATE_LIMIT, $count = false ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
		$key = 'wpf_rl_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= $limit ) {
			return new WP_Error( 'wpf_rate', __( 'Too many requests. Try again in a minute.', 'woo-product-filter' ), array( 'status' => 429 ) );
		}
		if ( $count ) {
			set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
		}
		return true;
	}

	/**
	 * Wrap a read callback so each real execution counts once against the limit.
	 */
	private function counted( $cb ) {
		return function ( $input ) use ( $cb ) {
			$ok = $this->rateLimit( self::RATE_LIMIT, true );
			if ( is_wp_error( $ok ) ) {
				return $ok;
			}
			return call_user_func( $cb, $input );
		};
	}

	public function canManage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * The MCP endpoint: the plugin serves its own (wpf/v1/mcp, always active). The WordPress MCP Adapter
	 * (or a compatible plugin) is detected as an extra under 'adapter', it is not needed.
	 */
	public static function mcp_endpoint() {
		static $served = null;
		$adapter = class_exists( 'WP\MCP\Core\McpAdapter' ) || class_exists( 'WP_MCP_Adapter' ) || defined( 'WP_MCP_ADAPTER_VERSION' ) || function_exists( 'wp_mcp_adapter' ) || class_exists( 'Automattic\WordpressMcp\Core\WpMcp' );
		// The adapter library can be loaded (WooCommerce bundles it) without serving anything: only call it active when
		// the endpoint is really registered, so the OpenAPI, llms.txt and the screens never point at a 404.
		if ( $adapter && function_exists( 'rest_get_server' ) && ( did_action( 'rest_api_init' ) || is_admin() ) ) {
			if ( null === $served ) {
				$routes = rest_get_server()->get_routes();
				$served = isset( $routes['/mcp/mcp-adapter-default-server'] );
			}
			$adapter = $served;
		}
		$own = class_exists( 'WooBeWoo_PF_MCP' ) ? WooBeWoo_PF_MCP::url() : rest_url( 'wpf/v1/mcp' );
		return array(
			'active'    => true,
			'builtin'   => true,
			'path'      => '/wp-json/wpf/v1/mcp',
			'url'       => $own,
			'versions'  => class_exists( 'WooBeWoo_PF_MCP' ) ? WooBeWoo_PF_MCP::protocolVersions() : array( '2025-06-18' ),
			'abilities' => function_exists( 'wp_register_ability' ),
			'adapter'   => array(
				'active' => $adapter,
				'path'   => '/wp-json/mcp/mcp-adapter-default-server',
				'url'    => rest_url( 'mcp/mcp-adapter-default-server' ),
				'plugin' => 'https://wordpress.org/plugins/mcp-adapter/',
			),
		);
	}

	/* ------------------------------------------------------------------ REST */

	private function argsFrom( $schema ) {
		$args = array();
		foreach ( (array) ( isset( $schema['properties'] ) ? $schema['properties'] : array() ) as $k => $p ) {
			$args[ $k ] = $p;
			if ( ! empty( $schema['required'] ) && in_array( $k, $schema['required'], true ) ) {
				$args[ $k ]['required'] = true;
			}
		}
		return $args;
	}

	public function registerRest() {
		$free = apply_filters( 'woobewoo_pf_free_abilities', array() );
		foreach ( self::registry() as $name => $d ) {
			if ( ! in_array( $name, $free, true ) || empty( $d['rest']['path'] ) ) {
				continue;
			}
			$cb      = 'public' === $d['permission'] ? $this->counted( $d['callback'] ) : $d['callback'];
			$methods = array();
			foreach ( (array) $d['rest']['methods'] as $m ) {
				$methods[] = 'GET' === $m ? WP_REST_Server::READABLE : ( 'POST' === $m ? WP_REST_Server::CREATABLE : $m );
			}
			register_rest_route(
				'wpf/v1',
				$d['rest']['path'],
				array(
					'methods'             => $methods,
					'permission_callback' => 'public' === $d['permission'] ? array( $this, 'abilityPermission' ) : array( $this, 'canManage' ),
					'args'                => $this->argsFrom( $d['input'] ),
					'callback'            => function ( $request ) use ( $cb ) {
						$params = $request->get_params();
						unset( $params['rest_route'], $params['_locale'] );
						return rest_ensure_response( call_user_func( $cb, $params ) );
					},
					'schema'              => function () use ( $d ) {
						return $d['output'];
					},
				)
			);
		}
		register_rest_route(
			'wpf/v1',
			'/openapi',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => function () {
					return rest_ensure_response( $this->getModel()->openapi( rest_url( 'wpf/v1' ) ) );
				},
			)
		);
		register_rest_route(
			'wpf/v1',
			'/readiness',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => array( $this, 'canManage' ),
				'callback'            => function () {
					return rest_ensure_response( $this->getModel( 'readiness' )->score() );
				},
			)
		);
		register_rest_route(
			'wpf/v1',
			'/llms',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'permission_callback' => array( $this, 'canManage' ),
				'callback'            => function () {
					return rest_ensure_response( $this->getModel( 'readiness' )->createLlms() );
				},
			)
		);
	}

	/* ------------------------------------------------------------------ llms.txt */

	public function queryVars( $vars ) {
		$vars[] = 'wpf_llms';
		return $vars;
	}

	public function llmsRewrite() {
		add_rewrite_rule( '^llms\.txt$', 'index.php?wpf_llms=1', 'top' );
	}

	public function maybeServeLlms() {
		if ( ! get_query_var( 'wpf_llms' ) ) {
			return;
		}
		if ( file_exists( ABSPATH . 'llms.txt' ) ) {
			return; // The web server serves the real file.
		}
		$content = (string) get_option( WooBeWoo_PF_Readiness_Model::LLMS_OPTION, '' );
		if ( '' === $content ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}
		status_header( 200 );
		header( 'Content-Type: text/plain; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text built by this plugin from escaped names and URLs.
		exit;
	}

	/* ------------------------------------------------------------------ self test */

	public function selfTestRows( $rows ) {
		$api    = function_exists( 'wp_register_ability' );
		$names  = array_keys( self::registry() );
		$rows[] = array(
			'state'  => $api ? 'pass' : 'partial',
			'label'  => __( 'AI shopper abilities are registered', 'woo-product-filter' ),
			'detail' => $api ? implode( ', ', $names ) : __( 'WordPress 6.9 or newer adds the Abilities API; the same operations work over REST (wpf/v1).', 'woo-product-filter' ),
		);
		$rows[] = array(
			'state'  => 'pass',
			'label'  => __( 'MCP endpoint is on', 'woo-product-filter' ),
			'detail' => rest_url( 'wpf/v1/mcp' ),
		);
		return $rows;
	}
}
