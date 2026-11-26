# MCP: tools, resources and prompts for agents

An app offers agents its tools, resources and prompts over the Model Context Protocol (#316):
MCP 2025-06-18's JSON-RPC on one POST route, answered as JSON. `AIC\Mcp` does the protocol; each
module writes its entries as classes in `src/Mcp/`, and the build turns them into plain wiring,
with every tool's input and output JSON schema written from its PHP types. Nothing reflects at run
time, so the server compiles natively like the rest of the app.

## The endpoint

A module routes `POST /mcp` to its own Api, under a permission that admits a client, and hands
the request to `AIC\Mcp\Application\McpServer` with its catalog:

```php
->permission('ledger.mcp', 'Connect an agent to the MCP server at /mcp and read the ledger resources and prompts.')
->route(RouteMethod::Post, '/mcp', LedgerMcpApi::class, needs: 'ledger.mcp')
```

```php
final readonly class LedgerMcpApi implements Api
{
    public function __construct(
        private McpServer $mcpServer,
        private LedgerMcp $ledgerMcp,
    ) {
    }

    #[Override]
    public function api(ServerRequestInterface $serverRequest): ApiResponse
    {
        return $this->mcpServer->answer($serverRequest, $this->ledgerMcp->catalog());
    }
}
```

`App\Generated\LedgerMcp` is the module's catalog, written by the build from `modules/ledger/src/Mcp/`.
To serve several modules' entries from one route, join their catalogs:
`$this->ledgerMcp->catalog()->with($this->noteMcp->catalog())`. The other module's entries are built
in the endpoint's module, so it requires that module and that module exports them.

One POST carries one message. The server answers:

| Method | Answer |
|---|---|
| `initialize` | the protocol version (the client's when it is 2025-06-18, 2025-03-26 or 2024-11-05, else the newest), the three capabilities, the app's name |
| `ping` | `{}` |
| `tools/list`, `resources/list`, `prompts/list` | the entries the caller has the permission for, each list whole |
| `tools/call`, `resources/read`, `prompts/get` | the entry's answer |
| a notification (no `id`) | `202` with no body (`AIC\Controller\Domain\ApiAccepted`) |

A body that is not JSON is `-32700`, and one that is not a single JSON-RPC 2.0 message (a batch
included) is `-32600`, both with a `400`. An unknown method is `-32601`. Arguments that do not fit
the entry are `-32602` naming the field, and a tool or prompt the caller cannot see is `-32602`
too; an unknown resource is `-32002`. Anything else a call throws is `-32603`. The server keeps
nothing between requests: no `Mcp-Session-Id`, and no GET stream (the router answers `405`).

## Access

A caller reaches the route through its `needs:`: a person's role, or a bearer token's scope
(#313), so an agent connects with a token from `bin/aic token:add <name> <secret> ledger.mcp
ledger.split`. A bearer request skips the CSRF check; a cookie request still needs its token, so a
page on another site cannot call the endpoint for a signed-in person. Each entry names its own
permission in its attribute, the build checks that some module declares it, and the server shows and
runs only the entries whose permission the caller has. One the caller lacks is unknown to it.

## Tools

A tool is a readonly class in a module's `src/Mcp/` that implements `AIC\Mcp\Contracts\McpTool` and is
marked `#[ToolInfo]`. It is built with its services, like any module class, and has one public method,
`call()`, taking a readonly input class and, when it asks, the caller's `AIC\Access\Domain\Actor`:

```php
#[ToolInfo(
    name: 'ledger.split',
    description: 'Split an amount of money into equal parts in whole cents; the first parts take the remainder.',
    needs: 'ledger.split',
)]
final readonly class LedgerSplitTool implements McpTool
{
    public function call(LedgerSplitInput $ledgerSplitInput): LedgerSplit
    {
        // …
    }
}
```

The input and output are readonly classes whose constructors are their fields:

```php
/**
 * @param int            $amount   The amount to split, in cents.
 * @param int            $parts    How many parts to split it into, 1 to 100.
 * @param LedgerCurrency $currency The amount's currency.
 * @param list<string>   $labels   A label for each part, in order; a part with none is "part N".
 * @param string|null    $memo     A note the result repeats.
 */
public function __construct(
    public int $amount,
    public int $parts = 2,
    public LedgerCurrency $currency = LedgerCurrency::Usd,
    public array $labels = [],
    public ?string $memo = null,
) {
}
```

The build reads each parameter into the schema: its type, its `@param` description (required), and
its default, which makes the field optional. An input field is `string`, `int`, `float` or `bool`,
any of them nullable, a backed enum (its values the schema's `enum`), or `list<string>` or
`list<int>`. An output field may also be another readonly value class, a list of them, or a list of
enum cases. Anything else stops the build, naming the parameter.

For each tool the build writes `App\Generated\<Tool>Call`, which refuses a field the input does not
have, fills the defaults, reads each field as its type into the input and calls the tool, and
`App\Generated\<Tool>Result`, the output as the JSON its schema states. A tool's answer carries the
output twice, as `structuredContent` and as JSON text, for clients that read only text. A tool that
understands a call and will not do it throws `AIC\Mcp\Contracts\ToolRefusedException`: its message is
the result's text, with `isError`, so the agent can correct itself.

`bin/aic make:mcp-tool <module> <Name> [--needs=<permission>]` writes a tool, its input and its
output, and, with no `--needs`, declares `<module>.<name>` as the tool's permission.
`bin/aic make:mcp-resource <module> <Name>Resource [--needs=<permission>]` writes a Markdown resource at
`<module>://<name>`, and `bin/aic make:mcp-prompt <module> <Name>Prompt [--needs=<permission>]` a prompt
named `<module>.<name>` with one argument; each declares `<module>.<name>` as its permission with no
`--needs` (#334).

## Resources and prompts

A resource implements `AIC\Mcp\Contracts\McpResource`, is marked `#[ResourceInfo]` with its URI,
name, description, permission and media type, and returns its text from `read()`. A prompt implements
`AIC\Mcp\Contracts\McpPrompt`, is marked `#[PromptInfo]` with its name, description, permission and
arguments (each a string, by name with its description; `optional` names the ones a client may leave
out), and returns its `McpMessage` list from `messages()`. The server refuses a prompt call missing a
required argument, or carrying one the prompt does not name, before the prompt runs.

## What the build refuses

The build stops on an entry that is not a readonly class marked once with its attribute; a tool
whose `call()` is not `call(<Input>[, Actor]): <Output>`; a name that is not lower-case words joined
by `.`, or that another tool or prompt has; a resource URI with no scheme, or one another resource
has; a `needs` no module declares; and two entries whose generated classes would share a name. Each
refusal names the class and the rule.

## Limits

Past seven entries the build writes the catalog in parts (#334): `<Module>Mcp` holds six and takes
`<Module>McpPart2`, which holds the next six and so on, each part's entries joined after its own with
`with()`, so the endpoint still takes `<Module>Mcp`. When the endpoint's handler, with the server and
every entry's services, would pass PHPMD's coupling of 15, the build makes those services in the
module's `<Module>HandlerServices` and the handler calls one method for each. A module's `src/Mcp/`
still holds 15 files at most (gate:26): a tool takes three, a resource or a prompt one. Each list is whole,
with no cursor, and nothing announces a change to it; a tool reports no progress while it runs (#333).
