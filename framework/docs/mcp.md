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
| `initialize` | the protocol version (the client's when it is 2025-06-18, 2025-03-26 or 2024-11-05, else the newest), the three capabilities, each with `listChanged: true` (#333), the app's name |
| `ping` | `{}` |
| `tools/list`, `resources/list`, `prompts/list` | the entries the caller has the permission for, each list whole |
| `tools/call`, `resources/read`, `prompts/get` | the entry's answer |
| a notification (no `id`) | `202` with no body (`AIC\Controller\Domain\ApiAccepted`) |

A body that is not JSON is `-32700`, and one that is not a single JSON-RPC 2.0 message (a batch
included) is `-32600`, both with a `400`. An unknown method is `-32601`. Arguments that do not fit
the entry are `-32602` naming the field, and a tool or prompt the caller cannot see is `-32602`
too; an unknown resource is `-32002`. Anything else a call throws is `-32603`. The server keeps
nothing between requests: no `Mcp-Session-Id`. The GET stream and a tool's progress are below.

## Access

A caller reaches the route through its `needs:`: a person's role, or a bearer token's scope
(#313), so an agent connects with a token from `bin/aic token:add <name> <secret> ledger.mcp
ledger.split`, or with one an OAuth client gets from the app's authorization server (#331): with
`AppConfig::$oauth` on, an anonymous call answers 401 with `resource_metadata`, which an MCP client
follows to the RFC 9728 and RFC 8414 documents, the consent page and the token endpoint
([security.md](security.md#oauth)). A bearer request skips the CSRF check, and so does a JSON body,
which a browser cannot send to another site without a preflight; a form post with a cookie still
needs its token, so a page on another site cannot call the endpoint for a signed-in person. Each entry names its own
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

## Capabilities: one call path, dry runs, call ids and the audit

Every tool call goes through `AIC\Mcp\Application\Capabilities` (#332), the registry an MCP client's
`tools/call` and an app's own assistant share, so neither gets around the other's rules:

- `visible(McpCatalog, Actor)` lists the tools the actor may call; `call(McpCatalog, CapabilityCall,
  Actor)` calls one and answers a `CapabilityResult`: how it ended (`CallOutcome`), the output as encoded
  JSON, or the refusal, and whether it ran dry or was replayed. A `CapabilityCall` names the tool, its
  arguments as a JSON object, a call id and the dry flag.
- **A permission ceiling.** An assistant acting for the signed-in person calls with
  `$actors->actor($request)->within(['ledger.split'], 'ledger-assistant')`: the person's permissions cut
  to the ceiling, and its own name for the audit. It can never do more than the person or the ceiling.
- **A dry run.** `#[ToolInfo(..., dryRun: true)]` lets a caller run the tool with its writes rolled back:
  the real call in a savepoint, so the preview cannot drift from the call. Declare it only when every
  effect is in the database (rows, queued jobs, broadcasts); an outbound request or a stored file is not
  rolled back. The default is false, and such a tool refuses a dry call. `tools/list` shows
  `_meta: {"aic/dryRun": true}`; an MCP client asks with `params._meta["aic/dryRun"]: true`, and the result
  carries the same `_meta`.
- **A call id is applied once.** `params._meta["aic/callId"]` (1 to 64 of `A-Z a-z 0-9 . _ : -`) is the
  caller's id for the call, scoped to the caller (its client and person). The first call with it runs; a
  repeat waits for the first under a transaction advisory lock and answers the stored result byte for
  byte, without running the tool. The same id with another tool is `-32602`. A dry run neither takes nor
  uses an id. A call that failed or did not fit its input keeps no id, so it can be retried.
- **Every call is a row** of `aic_mcp_calls`: when, the client, the person, the tool, the call id, whether
  it ran dry, the outcome (`done`, `refused`, `invalid`, `failed` or `replayed`), the arguments and the
  output or refusal. The row commits with the tool's own writes; a failure rolls the writes back and
  keeps the row, and the caller gets `-32603`. `bin/aic mcp:calls [<limit>]` lists the latest, newest
  first, without arguments or output.

The fixture's `/ledger/assistant` is an in-app assistant: it lists what the signed-in person's assistant
may call under the ceiling `ledger.split`, previews a split as a dry run, splits, and sends the same call
again, its call id sealed in the form's envelope.

## The GET stream and progress

A module routes `GET /mcp` to a Stream of its own, under the same `needs:` as its POST, which hands
the request to `AIC\Mcp\Application\McpStream` with the same catalog (#333):

```php
public function stream(ServerRequestInterface $serverRequest): StreamResponse
{
    return $this->mcpStream->stream($serverRequest, $this->ledgerMcp->catalog());
}
```

It is a broadcast stream (#314) on the channel `aic.mcp`, held, capped and resumed as one. Each
message is a JSON-RPC notification in an `event: message`. At the connection's first event, at each
keep-alive and at its end, it resolves the caller again and prints the three lists the caller would
be sent (the first 8 hex digits of each one's SHA-256); for each print that moved it sends
`notifications/tools/list_changed`, `resources/list_changed` or `prompts/list_changed`. Every id it
sends carries the prints, `<n>:<tools>-<resources>-<prompts>`, so a client that reconnects with
`Last-Event-ID` hears what changed while it was away. A role granted or revoked, a token's scopes or
a deploy that changes the catalog all show this way, at the keep-alive's latency.

A tool runs in steps when it has two methods beside `call()`:

```php
public function steps(LedgerTallyInput $ledgerTallyInput): int
public function step(LedgerTallyInput $ledgerTallyInput, int $step): string
```

The build writes them into the tool's adapter, an `AIC\Mcp\Domain\McpSteppedCall`, and `tools/list`
shows `_meta: {"aic/progress": true}`. A `tools/call` to such a tool with `params._meta.progressToken`,
from a client whose `Accept` names `text/event-stream`, is answered as an event stream
(`AIC\Controller\Domain\ApiEvents`): each step runs as the body is read and leaves as a
`notifications/progress` (the token, the step's number, the total and the step's message), then the
reply is the last event. The steps run inside the call's transaction and savepoint, so a dry run, a
call id and the audit hold as for any call: a repeated id answers the stored reply with no steps,
and a step that refuses or fails ends the call. Without a token, to a tool with no steps, or to a
client that reads only JSON, the answer is JSON. The fixture's `ledger.tally` adds amounts one step
at a time.

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
with no cursor. A list change is heard at the GET stream's next keep-alive, not when it happens, and
nothing publishes on `aic.mcp` yet. A call with progress holds a site worker for all its steps, and a
connection lost mid-call rolls the whole call back with the connection, leaving no audit row.
The audit keeps every row; there is no retention yet.
