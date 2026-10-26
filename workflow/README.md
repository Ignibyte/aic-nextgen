# AIC workflow

`aic/workflow` is a development package. It supplies the app's four-phase engine, code index,
scaffolds, independent review and client guards. Runtime code does not depend on it.

## Install in an app

Require the package for development, then select the client explicitly:

```sh
composer require --dev aic/workflow:0.1.x-dev
bin/aic workflow:init --client=codex
```

Omitting `--client` selects Claude Code. `--client=claude` is the explicit equivalent. Codex
installs `.agents/skills/`, `.codex/agents/aic-seeker.toml`, `.codex/hooks.json` and the committed
`.codex/aic-workflow.lock`. Both clients install the app's workflow settings and managed
instructions. Init preserves unrelated hooks and edited owned files. A regular rerun upgrades
unchanged owned files. The operator can force a reviewed owned file from a terminal; an open
run, malformed settings or an obstructing symlink still refuses before any write.

Commit the installed instructions, role, hooks, settings and lock. This maintainer repository
holds the package source, so init refuses here; its own ticket phases remain manual.

## Review and launch Codex

The operator reviews the project folder and exact callback definitions in Codex's supported
`/hooks` interface. Trust is tied to the current definitions. Do not bypass that review or edit
the trust store. Before launching a fresh session, run this from the operator terminal:

```sh
bin/aic workflow:codex-check
```

The check compares installed instructions and ownership with the package, then asks the real
client for enabled, synchronous, trusted callbacks with matching source, event, matcher,
command and timeout. Missing responses, configuration errors and stale controls hold launch.
A successful process exit without the requested protocol response is insufficient.

The check starts a finite child Codex process. Run it before entering the tool sandbox: the
child may need client-state writes that a workspace sandbox refuses. Its failure inside that
sandbox does not establish readiness. Restore or review the actual controls and rerun the
operator check; do not manufacture a receipt.

Start Codex at the app root. The chosen model and sandbox policy also apply to the native
seeker. Apps whose gates need advisory downloads or local Postgres need network access in
that workspace policy. Keep database credentials in the environment or the app's supported
local configuration, outside committed source.

Keep writable tool state inside the workspace too. The native gates honor
`ELEPHC_NATIVE_CACHE`; Semgrep has separate settings, log and version-cache paths. For a
workspace sandbox, set these in the operator terminal before launching Codex:

```sh
mkdir -p .native-gate/.cache/semgrep
export ELEPHC_NATIVE_CACHE="$PWD/.native-gate/.cache/elephc-native"
export SEMGREP_SETTINGS_FILE="$PWD/.native-gate/.cache/semgrep/settings.yml"
export SEMGREP_LOG_FILE="$PWD/.native-gate/.cache/semgrep/semgrep.log"
export SEMGREP_VERSION_CACHE_PATH="$PWD/.native-gate/.cache/semgrep/version-cache"
```

These are disposable tool caches, outside committed source. A gate failure caused by a
read-only external cache still holds the run. Repair the tool environment and resume that
same run; do not waive the gate or broaden filesystem permissions to hide the failure.

Use `$aic-workflow-start` and the installed phase skills. One feature uses one ticket and one
engine run. The engine runs and records its own gates. At `inspection-due`, dispatch the native
`aic-seeker` role with `Inspect the open run.`. File its returned Seeker Inspection section
unchanged. A parent-written, changed, stale or duplicate ledger does not replace that return.

## Generate components and the first theme

`bin/aic command:list` includes `make:component`, `make:page`, `make:layout`, `make:entity` and `make:theme`. Components live
in the theme that `config/app.php` names (#153): `make:component <Class>` writes
`themes/<theme>/components/<Class>/<Class>.php` and its lower-kebab `.tpl.php`, and edits no
manifest. A default `make:page` writes its own view there and routes the page in its module's
manifest; `make:page --component=<Class>` reuses a theme component. The entity generator's list
page uses this shared writer when that generator is implemented.

`make:layout <name>` (#157) writes a named page layout into the theme's `layouts/`: `<Case>Page.php`,
page data with the content region only, and `page--<name>.tpl.php`, and edits no manifest. Its document
falls back to `html.tpl.php`. After `composer run build` the generated `ThemeLayoutName` has the case,
and `make:page <module> <Class> <path> --layout=<name>` passes it; the make refuses a layout the theme
does not ship, and `--layout` with `--post`. It writes no `html--<name>.tpl.php`: until templates stop
compiling to classes, a copy of the theme's document compiles to a phpcpd clone.

`make:entity <module> <Class> <table> --fields=<name>:<type>,… [--version=<v>]` (#123) writes the
entity, `<Class>Repository` (`latest(int)`), `Create<Table>Table` and `<Class>ListPage` (GET of the
table's path, the newest 50 rows as a heading and paragraphs in the framework's atoms), and adds the
route and the migration in one manifest edit. Types are `text`, `numeric`, `date` and `boolean`; a
field named `id` or `created_at`, a repeated field or an unknown type is a usage refusal. Each body
names its entity, so two made entities share no phpcpd run unless about five fields in a row match.

`bin/aic make:theme <name> --dry-run --json` previews the first theme: `layouts/Page.php` with
four regions, the HTML and page templates and real CSS. It prints the `config/app.php`, Composer
and PHPCS lines to add. Pages take the framework's `PageDocuments` (#154). Use lowercase words joined by hyphens, at
most 32 bytes. An existing file refuses before writes; reruns refuse instead of overwriting.

Composer must map `"App\\Theme\\Components\\": "themes/<theme>/components/"` and
`"App\\Generated\\Templates\\": "generated/templates/"`; run `composer dump-autoload` before
generation. Makes validate these mappings and do not edit tool configuration. Inspect the dry-run, declare every authored path and the module manifest,
and ground those paths with the code index. The ordinary path-based guard checks them all;
there is no special exemption for theme or template sources. The engine records `code.made`.
The build owns `generated/templates/`, public assets and wiki output. No generated class is
written by a make. Run `composer run build` after generation.

## Read stored gate diagnostics

`bin/aic workflow:logs gate:8` reads one stored gate result from the open run, or the latest
archived run. It selects the latest matching record unless selectors specify the phase and
attempt. Use `gate` for the aggregate suite output, including abnormal execution and timeouts.

```sh
bin/aic workflow:logs gate:8 --phase=code --attempt=1
bin/aic workflow:logs gate --run=run-ID --phase=code --attempt=1 --json
bin/aic workflow:logs gate:8 --from-line=1 --lines=100 --json
```

Full stored output is the default. JSON uses the existing `aic.console/1` envelope and keeps
exact transcript bytes when they are valid UTF-8. Plain text remains available for other byte
sequences. Explicit line windows preserve original endings and identify the returned range
and continuation. JSON also reports total lines and bytes. `output_complete` describes whether
the selection contains every stored byte. `transcript_complete` separately describes capture
provenance: new captures retain all observed output, while historical records report unknown
completeness. It does not claim a timed-out process finished or that discarded bytes were recovered.

Each returned record identifies its run, phase, attempt, gate, outcome, fault, subject hash and
adjudication time. Freshness is not re-evaluated by inspection. The fixed project database opens
with SQLite read-only flags. The command creates no database, migrates no schema, reruns no
gate and accepts no SQL or database path. Exit 0 returns stored evidence, exit 1 means unavailable
evidence or output, and exit 2 rejects malformed selectors.

A seeker pending beside failed prerequisites was not actionable. Environment-only recovery
permits its first inspection-due report. An unchanged repeat of that actionable report still
becomes an operator hold. Ordinary repeated faults and retry limits retain their existing behavior.

## What the native guards judge

The adapter reads actual tool and lifecycle fields. It checks every add, update, delete and
move destination in a native patch before allowing the tool. Known MCP path fields pass
through the same phase, protected-path, vendor and real-destination walls. Unsupported local
mutation routes hold. Supported MCP file mutations are `write_file` and `edit_file` with
explicit non-empty paths; an unknown tool cannot gain permission by supplying a path.
A separate native `PermissionRequest` callback verifies the caller and
rechecks supported MCP scope before approving that request. It refuses shell escalation and
unknown tools. This project callback does not change global approval or sandbox settings.

Native Bash hooks omit the requested workdir and tty options. After checking the original
command, AIC replaces it with an owned runner that starts at the project root and gives its
child closed input and no tty. Use an explicit `cd` in the inspected command for another
directory. Later `write_stdin` input cannot supply the child's commands.

The parent session and actual child id/type bind native review calls to the current run.
The seeker's supported edit, shell-write and MCP-write routes refuse. This is workflow policy;
the role inherits the parent's client permissions and does not acquire a separate OS sandbox.

## Browser checks in Codex

Configure a trusted Playwright MCP server in the app's Codex configuration. Use an isolated
headless browser and a project-local output directory for its automatic logs and downloads.
Review that server configuration before launch. AIC inspects tool arguments, not the server's
internal implementation or every side effect of a page.

Structured page actions, snapshots and diagnostics are supported for the primary agent at
Check, with readable run state and no pending hold. Direct navigation URLs must use the
configured app origin. The callback does not inspect redirects or destinations reached by
page actions. It is not a browser network sandbox.

A screenshot requires an explicit absolute `filename`. Declare that artifact in the spec's
File Manifest. Every explicit browser artifact filename passes the existing protected,
phase, vendor and actual-destination checks. Relative and implicit screenshot destinations
are refused. Snapshot and diagnostic results can return directly without a filename.
Arbitrary JavaScript evaluation, unsafe Playwright code and file-transfer routes are held.
The independent seeker cannot use browser actions.

Use the real browser to perform each spec item and record what you observed. Tick an item
only after that action. The engine requires actual allowed browser calls at Check as well
as the checked items. A copied screenshot or HTTP request alone does not satisfy it.

## Client limits

Enabled trusted hooks are required for this integration. Codex can continue a tool call when a
callback cannot start, times out or returns malformed output. An explicit supported denial
blocks the call when the callback runs. AIC emits that denial for its handled tool failures and
records review returns only from valid lifecycle events; it cannot turn a client hook-startup
failure into an unconditional client refusal. The readiness check and protected controls do not
remove this client limit. See the [Codex hooks documentation](https://learn.chatgpt.com/docs/hooks).

The supported client is the local Codex CLI with the native hook and subagent capabilities.
Verify another client or execution route before relying on equivalent coverage. A tool's name
or a copied event alone is not evidence that a real callback ran.
