<?php
/**
 * Product Filter by WBW - WooBeWoo_PF_MCP Class
 *
 * Built in MCP (Model Context Protocol) server: a Streamable HTTP endpoint at wpf/v1/mcp speaking
 * JSON-RPC 2.0. Every ability in WooBeWoo_PF_Abilities::registry() is a tool (name = ability name with
 * "/" and "-" kept as "_"), the input schema is the tool's inputSchema and the callback runs tools/call.
 *
 * - initialize, notifications/initialized, ping, tools/list, tools/call; batches (JSON arrays) accepted.
 * - Protocol versions 2025-06-18 (default), 2025-03-26 and 2024-11-05.
 * - Read tools are public with the same 120 a minute per IP limit as the REST mirror.
 * - Write tools (Pro) run for a logged in user with the ability's capability, or when the
 *   `woobewoo_pf_mcp_authorize` filter (default false) says yes: Pro hooks it to accept X-WPF-Key.
 * - GET on the route is a small JSON "server card" for discovery (Cache-Control public, 5 minutes).
 * - Stateless: no session id, no server to client stream (plain JSON responses).
 *
 * @version 4.0.0
 * @since   4.0.0
 *
 * @author woobewoo
 */

defined( 'ABSPATH' ) || exit;

class WooBeWoo_PF_MCP {

	const ROUTE            = '/mcp';
	const PROTOCOL         = '2025-06-18';
	const MAX_BODY         = 262144; // 256 KB.
	const SERVER_NAME      = 'WooBeWoo Product Filter';
	const ERR_PARSE        = -32700;
	const ERR_REQUEST      = -32600;
	const ERR_METHOD       = -32601;
	const ERR_PARAMS       = -32602;
	const ERR_INTERNAL     = -32603;
	const ERR_UNAUTHORIZED = -32001;
	const ERR_RATE         = -32029;

	private static $hooked = false;

	public static function protocolVersions() {
		return array( '2025-06-18', '2025-03-26', '2024-11-05' );
	}

	/**
	 * Hook once (called from the abilities module init).
	 */
	public static function init() {
		if ( self::$hooked ) {
			return;
		}
		self::$hooked = true;
		add_action( 'rest_api_init', array( __CLASS__, 'registerRest' ) );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'preDispatch' ), 10, 3 );
		add_filter( 'rest_pre_serve_request', array( __CLASS__, 'serveHeaders' ), 100, 4 );
		add_shortcode( 'wpf-mcp-connect', array( __CLASS__, 'connectShortcode' ) );
	}

	public static function url() {
		return rest_url( 'wpf/v1' . self::ROUTE );
	}

	public static function registerRest() {
		register_rest_route(
			'wpf/v1',
			self::ROUTE,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'permission_callback' => '__return_true',
					'callback'            => array( __CLASS__, 'serverCard' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'permission_callback' => '__return_true',
					'callback'            => array( __CLASS__, 'handle' ),
				),
			)
		);
	}

	private static function isOurRoute( $request ) {
		return $request instanceof WP_REST_Request && '/wpf/v1' . self::ROUTE === $request->get_route();
	}

	/**
	 * rest_pre_dispatch: body size cap and a JSON-RPC shaped parse error before WordPress validates the
	 * JSON body itself (its own error shape would confuse MCP clients).
	 */
	public static function preDispatch( $result, $server, $request ) {
		if ( null !== $result || ! self::isOurRoute( $request ) || 'POST' !== $request->get_method() ) {
			return $result;
		}
		$body = (string) $request->get_body();
		if ( strlen( $body ) > self::MAX_BODY ) {
			return self::respond( self::error( null, self::ERR_REQUEST, __( 'Request body is too large (256 KB at most).', 'woo-product-filter' ) ), 413 );
		}
		if ( '' === trim( $body ) ) {
			return self::respond( self::error( null, self::ERR_REQUEST, __( 'Empty request body.', 'woo-product-filter' ) ), 400 );
		}
		$decoded = json_decode( $body, true, 64 );
		if ( null === $decoded && JSON_ERROR_NONE !== json_last_error() ) {
			return self::respond( self::error( null, self::ERR_PARSE, __( 'The body is not valid JSON.', 'woo-product-filter' ) ), 400 );
		}
		$version = isset( $_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ) ) : '';
		if ( '' !== $version && ! in_array( $version, self::protocolVersions(), true ) ) {
			return self::respond( self::error( null, self::ERR_REQUEST, sprintf( /* translators: %s: versions */ __( 'Unsupported MCP protocol version. Supported: %s.', 'woo-product-filter' ), implode( ', ', self::protocolVersions() ) ) ), 400 );
		}
		return $result;
	}

	/**
	 * rest_pre_serve_request: open CORS and the protocol header on this route (public read API).
	 */
	public static function serveHeaders( $served, $result, $request, $server ) {
		if ( ! self::isOurRoute( $request ) || headers_sent() ) {
			return $served;
		}
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, Accept, Authorization, X-WP-Nonce, X-WPF-Key, MCP-Protocol-Version, Mcp-Session-Id' );
		header( 'Access-Control-Expose-Headers: MCP-Protocol-Version' );
		header( 'Access-Control-Max-Age: 600' );
		header( 'MCP-Protocol-Version: ' . self::PROTOCOL );
		header_remove( 'Access-Control-Allow-Credentials' );
		if ( $result instanceof WP_HTTP_Response && 202 === $result->get_status() ) {
			return true; // Notifications only: 202 with an empty body.
		}
		return $served;
	}

	/* ------------------------------------------------------------------ tools */

	/**
	 * Tool name from an ability name: "woobewoo/facets-list" => "woobewoo_facets-list".
	 */
	public static function toolName( $ability ) {
		return str_replace( '/', '_', WooBeWoo_PF_Abilities::NS . '/' . $ability );
	}

	/**
	 * name => definition for the tools this request may see. Write tools exist only when Pro adds them.
	 */
	public static function tools() {
		$tools = array();
		foreach ( WooBeWoo_PF_Abilities::registry() as $name => $d ) {
			if ( empty( $d['callback'] ) || ! is_callable( $d['callback'] ) ) {
				continue;
			}
			$tools[ self::toolName( $name ) ] = array_merge( $d, array( 'ability' => $name ) );
		}
		return apply_filters( 'woobewoo_pf_mcp_tools', $tools );
	}

	private static function toolEntry( $toolName, $d ) {
		$input = ! empty( $d['input'] ) && is_array( $d['input'] ) ? $d['input'] : array( 'type' => 'object', 'properties' => new stdClass() );
		if ( empty( $input['type'] ) ) {
			$input['type'] = 'object';
		}
		if ( empty( $input['properties'] ) ) {
			$input['properties'] = new stdClass();
		}
		$entry = array(
			'name'        => $toolName,
			'title'       => isset( $d['label'] ) ? (string) $d['label'] : $toolName,
			'description' => isset( $d['description'] ) ? (string) $d['description'] : '',
			'inputSchema' => $input,
			'annotations' => array(
				'title'           => isset( $d['label'] ) ? (string) $d['label'] : $toolName,
				'readOnlyHint'    => ! empty( $d['readonly'] ),
				'destructiveHint' => false,
				'idempotentHint'  => ! empty( $d['readonly'] ),
				'openWorldHint'   => false,
			),
		);
		if ( ! empty( $d['output'] ) && is_array( $d['output'] ) && isset( $d['output']['type'] ) && 'object' === $d['output']['type'] ) {
			$entry['outputSchema'] = $d['output'];
		}
		if ( 'public' !== $d['permission'] ) {
			$entry['description'] .= ' ' . __( 'Needs a signed in user with the right capability or a valid X-WPF-Key header.', 'woo-product-filter' );
		}
		return $entry;
	}

	/**
	 * Whether this request may run the tool: public, a user with the capability, or Pro's key through
	 * `woobewoo_pf_mcp_authorize` (default false).
	 */
	public static function authorized( $toolName, $d, $request ) {
		if ( 'public' === $d['permission'] ) {
			return true;
		}
		$cap = is_string( $d['permission'] ) && '' !== $d['permission'] ? $d['permission'] : 'manage_options';
		if ( is_user_logged_in() && current_user_can( $cap ) ) {
			return true;
		}
		return (bool) apply_filters( 'woobewoo_pf_mcp_authorize', false, $toolName, $d, $request );
	}

	/* ------------------------------------------------------------------ JSON-RPC */

	private static function error( $id, $code, $message, $data = null ) {
		$err = array( 'code' => (int) $code, 'message' => (string) $message );
		if ( null !== $data ) {
			$err['data'] = $data;
		}
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => $err );
	}

	private static function result( $id, $result ) {
		return array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result );
	}

	private static function respond( $body, $status = 200 ) {
		$response = new WP_REST_Response( $body, $status );
		$response->header( 'MCP-Protocol-Version', self::PROTOCOL );
		$response->header( 'Content-Type', 'application/json; charset=' . get_option( 'blog_charset' ) );
		return $response;
	}

	/**
	 * POST: one JSON-RPC message or a batch.
	 */
	public static function handle( $request ) {
		$body    = (string) $request->get_body();
		$decoded = json_decode( $body, true, 64 );
		if ( ! is_array( $decoded ) ) {
			return self::respond( self::error( null, self::ERR_REQUEST, __( 'Expected a JSON-RPC request object or an array of them.', 'woo-product-filter' ) ), 400 );
		}
		$batch    = array_keys( $decoded ) === range( 0, count( $decoded ) - 1 ) && ! isset( $decoded['jsonrpc'] );
		$messages = $batch ? $decoded : array( $decoded );
		if ( empty( $messages ) ) {
			return self::respond( self::error( null, self::ERR_REQUEST, __( 'Empty batch.', 'woo-product-filter' ) ), 400 );
		}
		$out    = array();
		$status = 200;
		foreach ( $messages as $msg ) {
			$res = self::dispatch( $msg, $request );
			if ( null === $res ) {
				continue; // Notification: nothing to send back.
			}
			$out[] = $res['body'];
			if ( ! $batch ) {
				$status = $res['status'];
			}
		}
		if ( empty( $out ) ) {
			return self::respond( null, 202 );
		}
		return self::respond( $batch ? $out : $out[0], $status );
	}

	/**
	 * One message => array( body, status ), or null for a notification.
	 */
	private static function dispatch( $msg, $request ) {
		if ( ! is_array( $msg ) || ! isset( $msg['jsonrpc'] ) || '2.0' !== $msg['jsonrpc'] || empty( $msg['method'] ) || ! is_string( $msg['method'] ) ) {
			return array( 'body' => self::error( isset( $msg['id'] ) ? $msg['id'] : null, self::ERR_REQUEST, __( 'Not a JSON-RPC 2.0 request.', 'woo-product-filter' ) ), 'status' => 400 );
		}
		$hasId  = array_key_exists( 'id', $msg ) && null !== $msg['id'];
		$id     = $hasId ? $msg['id'] : null;
		$method = $msg['method'];
		$params = isset( $msg['params'] ) && is_array( $msg['params'] ) ? $msg['params'] : array();

		if ( 0 === strpos( $method, 'notifications/' ) ) {
			return null; // initialized, cancelled, progress...: acknowledged, nothing to do.
		}
		if ( ! $hasId ) {
			return null; // A notification we do not know: ignore it per JSON-RPC.
		}
		switch ( $method ) {
			case 'initialize':
				return array( 'body' => self::result( $id, self::initialize( $params ) ), 'status' => 200 );
			case 'ping':
				return array( 'body' => self::result( $id, new stdClass() ), 'status' => 200 );
			case 'tools/list':
				$list = array();
				foreach ( self::tools() as $toolName => $d ) {
					$list[] = self::toolEntry( $toolName, $d );
				}
				return array( 'body' => self::result( $id, array( 'tools' => $list ) ), 'status' => 200 );
			case 'tools/call':
				return self::callTool( $id, $params, $request );
			case 'resources/list':
				return array( 'body' => self::result( $id, array( 'resources' => array() ) ), 'status' => 200 );
			case 'resources/templates/list':
				return array( 'body' => self::result( $id, array( 'resourceTemplates' => array() ) ), 'status' => 200 );
			case 'prompts/list':
				return array( 'body' => self::result( $id, array( 'prompts' => array() ) ), 'status' => 200 );
		}
		return array( 'body' => self::error( $id, self::ERR_METHOD, sprintf( /* translators: %s: method */ __( 'Method not found: %s', 'woo-product-filter' ), $method ) ), 'status' => 200 );
	}

	private static function initialize( $params ) {
		$asked   = isset( $params['protocolVersion'] ) && is_string( $params['protocolVersion'] ) ? $params['protocolVersion'] : '';
		$version = in_array( $asked, self::protocolVersions(), true ) ? $asked : self::PROTOCOL;
		$names   = array_keys( self::tools() );
		return array(
			'protocolVersion' => $version,
			'capabilities'    => array(
				'tools' => array( 'listChanged' => false ),
			),
			'serverInfo'      => array(
				'name'    => self::SERVER_NAME,
				'title'   => self::SERVER_NAME,
				'version' => WPF_VERSION,
			),
			'instructions'    => sprintf(
				/* translators: 1: shop name 2: tool names */
				__( 'Product filter for the WooCommerce shop "%1$s". Call woobewoo_facets-list first to learn the categories, attributes, brands, price range and stock values with product counts, then woobewoo_products-filter with the chosen selection to get products (variation aware, paginated with page and per_page). Read tools are public and limited to 120 calls a minute per IP. Tools: %2$s.', 'woo-product-filter' ),
				wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				implode( ', ', $names )
			),
		);
	}

	private static function callTool( $id, $params, $request ) {
		$name = isset( $params['name'] ) && is_string( $params['name'] ) ? $params['name'] : '';
		$args = isset( $params['arguments'] ) ? $params['arguments'] : array();
		if ( is_object( $args ) ) {
			$args = (array) $args;
		}
		if ( ! is_array( $args ) ) {
			return array( 'body' => self::error( $id, self::ERR_PARAMS, __( 'arguments must be an object.', 'woo-product-filter' ) ), 'status' => 200 );
		}
		$tools = self::tools();
		if ( '' === $name || ! isset( $tools[ $name ] ) ) {
			return array( 'body' => self::error( $id, self::ERR_PARAMS, sprintf( /* translators: %s: tool */ __( 'Unknown tool: %s', 'woo-product-filter' ), $name ) ), 'status' => 200 );
		}
		$d = $tools[ $name ];

		if ( ! self::authorized( $name, $d, $request ) ) {
			return array( 'body' => self::error( $id, self::ERR_UNAUTHORIZED, __( 'This tool needs a signed in user with the right capability or a valid X-WPF-Key header.', 'woo-product-filter' ) ), 'status' => 403 );
		}
		if ( 'public' === $d['permission'] ) {
			$abilities = WooBeWoo_PF_Abilities::instance();
			$ok        = $abilities ? $abilities->rateLimit( WooBeWoo_PF_Abilities::RATE_LIMIT, true ) : true;
			if ( is_wp_error( $ok ) ) {
				return array( 'body' => self::error( $id, self::ERR_RATE, $ok->get_error_message() ), 'status' => 429 );
			}
		}

		// Validate and sanitize through the ability input schema (the same schema the REST mirror and the
		// Abilities API use).
		$schema = ! empty( $d['input'] ) && is_array( $d['input'] ) ? $d['input'] : array( 'type' => 'object' );
		if ( empty( $schema['type'] ) ) {
			$schema['type'] = 'object';
		}
		$valid = rest_validate_value_from_schema( $args, $schema, 'arguments' );
		if ( is_wp_error( $valid ) ) {
			return array( 'body' => self::error( $id, self::ERR_PARAMS, $valid->get_error_message() ), 'status' => 200 );
		}
		$clean = rest_sanitize_value_from_schema( $args, $schema, 'arguments' );
		if ( is_wp_error( $clean ) ) {
			return array( 'body' => self::error( $id, self::ERR_PARAMS, $clean->get_error_message() ), 'status' => 200 );
		}
		$clean = (array) $clean;

		try {
			$out = call_user_func( $d['callback'], $clean );
		} catch ( Throwable $e ) { // phpcs:ignore PHPCompatibility.Classes.NewClasses.throwableFound -- PHP 7.4+.
			// Never echo PHP errors to the client.
			return array( 'body' => self::result( $id, self::toolResult( __( 'The tool failed. Check the site error log.', 'woo-product-filter' ), null, true ) ), 'status' => 200 );
		}
		if ( is_wp_error( $out ) ) {
			return array( 'body' => self::result( $id, self::toolResult( $out->get_error_message(), null, true ) ), 'status' => 200 );
		}
		$json = wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json ) {
			return array( 'body' => self::result( $id, self::toolResult( __( 'The tool returned data that cannot be encoded.', 'woo-product-filter' ), null, true ) ), 'status' => 200 );
		}
		return array( 'body' => self::result( $id, self::toolResult( $json, is_array( $out ) || is_object( $out ) ? $out : null, false ) ), 'status' => 200 );
	}

	private static function toolResult( $text, $structured, $isError ) {
		$res = array(
			'content' => array( array( 'type' => 'text', 'text' => (string) $text ) ),
			'isError' => (bool) $isError,
		);
		if ( null !== $structured ) {
			$res['structuredContent'] = $structured;
		}
		return $res;
	}

	/* ------------------------------------------------------------------ discovery */

	/**
	 * GET: the server card (what the endpoint is and which tools it has).
	 */
	public static function serverCard() {
		$abilities = WooBeWoo_PF_Abilities::mcp_endpoint();
		$card      = array(
			'name'             => self::SERVER_NAME,
			'version'          => WPF_VERSION,
			'transport'        => 'streamable-http',
			'protocolVersions' => self::protocolVersions(),
			'endpoint'         => self::url(),
			'methods'          => array( 'initialize', 'notifications/initialized', 'ping', 'tools/list', 'tools/call' ),
			'tools'            => array_keys( self::tools() ),
			'auth'             => array(
				'read'  => sprintf( /* translators: %d: limit */ __( 'Public, %d requests a minute per IP.', 'woo-product-filter' ), WooBeWoo_PF_Abilities::RATE_LIMIT ),
				'write' => __( 'A signed in user with the capability (cookie + X-WP-Nonce or an application password) or the X-WPF-Key header (Pro).', 'woo-product-filter' ),
			),
			'openapi'          => rest_url( 'wpf/v1/openapi' ),
			'docs'             => 'https://wordpress.org/plugins/woo-product-filter/',
			'config'           => array( 'mcpServers' => array( 'product-filter' => array( 'url' => self::url() ) ) ),
		);
		if ( ! empty( $abilities['adapter']['active'] ) ) {
			$card['alsoServedBy'] = $abilities['adapter']['url'];
		}
		$response = self::respond( apply_filters( 'woobewoo_pf_mcp_server_card', $card ), 200 );
		$response->header( 'Cache-Control', 'public, max-age=300' );
		return $response;
	}

	/**
	 * [wpf-mcp-connect name="product-filter"]: endpoint, copy button and a client config snippet.
	 */
	public static function connectShortcode( $atts ) {
		$atts   = shortcode_atts( array( 'name' => 'product-filter', 'title' => __( 'Connect an AI agent', 'woo-product-filter' ) ), $atts, 'wpf-mcp-connect' );
		$key    = sanitize_key( $atts['name'] );
		$key    = '' !== $key ? $key : 'product-filter';
		$url    = self::url();
		$config = wp_json_encode( array( 'mcpServers' => array( $key => array( 'url' => $url ) ) ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		$uid    = 'wpf-mcp-' . wp_unique_id();
		$css    = '.wpf-mcp-connect{border:1px solid #d9d9e3;border-radius:8px;padding:16px 18px;margin:16px 0;font:14px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#fafafc;color:#1e1e2a;max-width:720px}'
			. '.wpf-mcp-connect h4{margin:0 0 8px;font-size:16px}'
			. '.wpf-mcp-connect p{margin:0 0 10px}'
			. '.wpf-mcp-connect .wpf-mcp-row{display:flex;gap:8px;align-items:center;flex-wrap:wrap}'
			. '.wpf-mcp-connect code,.wpf-mcp-connect pre{background:#fff;border:1px solid #e3e3ea;border-radius:6px;font:13px/1.5 ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#1e1e2a}'
			. '.wpf-mcp-connect code{padding:4px 8px;word-break:break-all}'
			. '.wpf-mcp-connect pre{padding:10px 12px;overflow:auto;margin:0;white-space:pre}'
			. '.wpf-mcp-connect button{cursor:pointer;border:1px solid #2271b1;background:#2271b1;color:#fff;border-radius:6px;padding:5px 12px;font:inherit;font-size:13px}'
			. '.wpf-mcp-connect button:hover{background:#135e96}'
			. '.wpf-mcp-connect small{color:#50575e}';
		$js     = '(function(){var b=document.getElementById(' . wp_json_encode( $uid . '-btn' ) . ');if(!b){return;}b.addEventListener("click",function(){var t=b.getAttribute("data-copy")||"";var done=function(){var o=b.textContent;b.textContent=b.getAttribute("data-done")||o;setTimeout(function(){b.textContent=o;},1500);};if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(t).then(done);}else{var i=document.createElement("textarea");i.value=t;document.body.appendChild(i);i.select();try{document.execCommand("copy");}catch(e){}document.body.removeChild(i);done();}});})();';

		$html  = '<style>' . $css . '</style>';
		$html .= '<div class="wpf-mcp-connect" id="' . esc_attr( $uid ) . '">';
		$html .= '<h4>' . esc_html( $atts['title'] ) . '</h4>';
		$html .= '<p>' . esc_html__( 'This shop has an MCP endpoint. Add it to your AI client to list facets and filter products.', 'woo-product-filter' ) . '</p>';
		$html .= '<div class="wpf-mcp-row"><code>' . esc_html( $url ) . '</code>';
		$html .= '<button type="button" id="' . esc_attr( $uid . '-btn' ) . '" data-copy="' . esc_attr( $url ) . '" data-done="' . esc_attr__( 'Copied', 'woo-product-filter' ) . '">' . esc_html__( 'Copy', 'woo-product-filter' ) . '</button></div>';
		$html .= '<p style="margin-top:12px"><small>' . esc_html__( 'Client config (Claude Desktop, Cursor and others):', 'woo-product-filter' ) . '</small></p>';
		$html .= '<pre>' . esc_html( $config ) . '</pre>';
		$html .= '</div>';
		$html .= function_exists( 'wp_get_inline_script_tag' ) ? wp_get_inline_script_tag( $js ) : '<script>' . $js . '</script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- tiny copy handler for the shortcode box, no asset file.
		return $html;
	}
}
