# MCP server (built in)

Product Filter by WBW 4.0 ships its own MCP (Model Context Protocol) server. No adapter plugin is needed. AI agents
connect to one URL and get the same operations the REST API and the WordPress Abilities API offer, as MCP tools.

- Endpoint: `https://example.com/wp-json/wpf/v1/mcp`
- Transport: Streamable HTTP (plain JSON responses, no server to client stream, no session id)
- Protocol versions: `2025-06-18` (default), `2025-03-26`, `2024-11-05`
- Methods: `initialize`, `notifications/initialized`, `ping`, `tools/list`, `tools/call` (plus empty `resources/list`,
  `resources/templates/list` and `prompts/list` for clients that ask). Unknown methods answer JSON-RPC error `-32601`.
- Batches: send a JSON array of requests, get an array of responses. Notifications alone answer `202` with an empty body.
- Headers: every response carries `MCP-Protocol-Version`. CORS is open (`Access-Control-Allow-Origin: *`) for GET and POST.
- `GET` on the endpoint returns a small JSON server card (name, version, protocol versions, endpoint, tool names, client
  config snippet), cached for 5 minutes, so people and crawlers can discover it. The endpoint is also listed in
  `llms.txt` and in the OpenAPI document (`x-mcp` and the `/mcp` path).

## Connect

Claude Desktop (`claude_desktop_config.json`), Claude Code (`.mcp.json`), Cursor (`.cursor/mcp.json`) and most other
clients take the same shape:

```json
{
  "mcpServers": {
    "product-filter": {
      "url": "https://example.com/wp-json/wpf/v1/mcp"
    }
  }
}
```

Claude Code from the command line:

```
claude mcp add --transport http product-filter https://example.com/wp-json/wpf/v1/mcp
```

A client that only speaks stdio can bridge with `npx mcp-remote https://example.com/wp-json/wpf/v1/mcp`.

The shortcode `[wpf-mcp-connect]` prints a small box on any page with the endpoint, a copy button and this snippet
(`name="shop"` changes the server key in the snippet).

## Tools

Every ability in the registry becomes a tool. The tool name is the ability name with `/` replaced by `_`; the tool's
`inputSchema` is the ability input schema and `outputSchema` its output schema. Annotations say whether the tool is
read only.

| Tool | What it does | Access |
| --- | --- | --- |
| `woobewoo_facets-list` | Attributes, categories, tags, brands, price range and stock values with product counts, optionally narrowed by a category or any selection. | Public |
| `woobewoo_products-filter` | Filter products by attributes, category, tag, brand, price, stock, sale and rating. Variation aware, paginated (`page`, `per_page` up to 50, `orderby`). | Public |
| `woobewoo_filter-create` (Pro) | Create a product filter from plain blocks. | Write |
| `woobewoo_filter-update` (Pro) | Rename a filter, replace its blocks or change allow listed settings. | Write |
| `woobewoo_filter-audit` (Pro) | Findings with plain fixes for one filter. | Write |

Pro tools are listed only when the Pro plugin is active and the ability is switched on. The `instructions` text sent
with `initialize` tells the agent to call `woobewoo_facets-list` first, then `woobewoo_products-filter`.

A successful `tools/call` returns:

```json
{
  "content": [ { "type": "text", "text": "{...json...}" } ],
  "structuredContent": { "...": "the same result as an object" },
  "isError": false
}
```

A tool that fails (a `WP_Error` or an exception) returns `isError: true` with a short message. PHP errors are never
echoed. Unknown tools and arguments that fail the input schema answer JSON-RPC error `-32602`.

## Auth for write tools

Read tools are public. A write tool runs when one of these holds:

1. The request is from a signed in user with the ability's capability (`manage_options`). Over REST that means a cookie
   plus `X-WP-Nonce`, or an application password (HTTP basic auth).
2. The `woobewoo_pf_mcp_authorize` filter returns true. It defaults to false; Pro hooks it to accept the headless API
   key sent as the `X-WPF-Key` header (made on the AI and agents screen, only its hash is stored):

```php
add_filter( 'woobewoo_pf_mcp_authorize', function ( $ok, $tool, $definition, $request ) {
	return $ok || ( isset( $_SERVER['HTTP_X_WPF_KEY'] ) && my_key_is_valid( $_SERVER['HTTP_X_WPF_KEY'] ) );
}, 10, 4 );
```

Anonymous requests without a valid key get JSON-RPC error `-32001` and HTTP `403`. Write tools never run for them.

## Rate limits and size

- Public tool calls share the REST limit: 120 a minute per IP (`-32029`, HTTP `429` when exceeded). Admins are not limited.
- The request body is capped at 256 KB (HTTP `413`). Invalid JSON answers JSON-RPC `-32700` with HTTP `400`.
- An unsupported `MCP-Protocol-Version` request header answers HTTP `400`.

## curl

Initialize:

```
curl -s -X POST https://example.com/wp-json/wpf/v1/mcp \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"curl","version":"1.0"}}}'
```

List tools and call one (a batch):

```
curl -s -X POST https://example.com/wp-json/wpf/v1/mcp \
  -H "Content-Type: application/json" -H "MCP-Protocol-Version: 2025-06-18" \
  -d '[
    {"jsonrpc":"2.0","id":2,"method":"tools/list"},
    {"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"woobewoo_products-filter","arguments":{"category":"shoes","attributes":{"color":["red"]},"in_stock":true,"per_page":5}}}
  ]'
```

A Pro write tool with the API key:

```
curl -s -X POST https://example.com/wp-json/wpf/v1/mcp \
  -H "Content-Type: application/json" -H "X-WPF-Key: wpfk_..." \
  -d '{"jsonrpc":"2.0","id":4,"method":"tools/call","params":{"name":"woobewoo_filter-create","arguments":{"title":"Shop filter","blocks":[{"type":"wpfPrice"}]}}}'
```

Server card:

```
curl -s https://example.com/wp-json/wpf/v1/mcp
```

## Hooks

- `woobewoo_pf_mcp_tools` (array of tool name => ability definition): add, remove or rename tools.
- `woobewoo_pf_mcp_authorize` (bool, tool name, definition, `WP_REST_Request`): allow a write tool without a login.
- `woobewoo_pf_mcp_server_card` (array): change the GET server card.
- `woobewoo_pf_abilities_registry`: the registry the tools are built from (Pro adds its write abilities here).

## WordPress MCP Adapter

The WordPress MCP Adapter plugin (WordPress 6.9+, Abilities API) is detected as an extra and shown next to the built in
endpoint in the readiness screen, llms.txt and the OpenAPI document. It is not needed: the built in endpoint works on any
WordPress the plugin supports.
