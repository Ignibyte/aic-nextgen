# AIC workflow

[![Latest version](https://img.shields.io/packagist/v/ignibyte/aic-workflow)](https://packagist.org/packages/ignibyte/aic-workflow)
[![PHP](https://img.shields.io/packagist/dependency-v/ignibyte/aic-workflow/php)](https://packagist.org/packages/ignibyte/aic-workflow)
[![License](https://img.shields.io/packagist/l/ignibyte/aic-workflow)](LICENSE)
[![Downloads](https://img.shields.io/packagist/dt/ignibyte/aic-workflow)](https://packagist.org/packages/ignibyte/aic-workflow/stats)

`ignibyte/aic-workflow` is a development package. It supplies the app's four-phase engine, code index,
scaffolds, independent review and client guards. Runtime code does not depend on it.

## Install in an app

Require the package for development, then select the client explicitly:

```sh
composer require --dev "ignibyte/aic-workflow:^0.1"
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
`themes/<theme>/components/<Class>/<Class>.php` and its lower-kebab `.phtml`, and edits no
manifest; `--script` gives it a JSON `data` string, a template whose element names a module script in
`data-mount`, and the script, `themes/<theme>/assets/components/<kebab>.js` (#315). A default `make:page` writes its own view there and routes the page in its module's
manifest; `make:page --component=<Class>` reuses a theme component. The entity generator's list
page uses this shared writer when that generator is implemented.

`make:layout <name>` (#157) writes a named page layout: `src/Layouts/<Case>Page.php`, page data with
the content region only, and `layouts/page--<name>.phtml`, and edits no manifest. Its document
falls back to `html.phtml`. After `composer run build` the generated `ThemeLayoutName` has the case,
and `make:page <module> <Class> <path> --layout=<name>` passes it; the make refuses a layout the theme
does not ship, and `--layout` with `--post`. It writes no `html--<name>.phtml`; write one by hand
when a layout needs a document of its own.

`make:mcp-tool <module> <Class>Tool [--needs=<permission>]` (#316) writes an MCP tool in the module's
`src/Mcp/` with its `<Stem>Input` and `<Stem>Output`, names it `<module>.<kebab stem>`, and, with no
`--needs`, declares that name as the permission it needs. `composer run build` then writes its schemas
and adapter into the module's `App\Generated\<Module>Mcp` ([mcp.md](../framework/docs/mcp.md)).

`make:enum <module> <Name> --cases=a,b [--backed=string|int]`, `make:exception <module>
<Name>Exception [--extends=<exception>]` and `make:interface <module> <Name>` (#319) write the
three plain shapes, each in the folder gate:26 gives its kind (`src/Enum/`, `src/Exception/`,
`src/Contracts/`), and edit no manifest. Enum cases are lower-case words (`on_hold` becomes
`OnHold`), a string backing is the word, an int backing the place from 1. A made exception is final
and extends `RuntimeException`, or `LogicException` or `InvalidArgumentException` when `--extends`
names it (the SPL roots gate:8 allows); a hierarchy of the module's own is hand work. An interface's name never ends in `Interface`.

`make:seed <module> <Name>Seed <table> --unique=<field> --fields=<name>:<type>,…` (#320) writes a
seed: the command `<name>:seed` with its rows as a constant, and its own repository, whose `save()`
upserts by the unique field, so a rerun updates the same rows. `--fields` takes make:entity's types
and `integer`; the manifest gains `->command()`. gate:8's `aic.seedWrites` keeps every `*Seed`
command off `AIC\Database` and `AIC\Entity`: it saves through repositories.

`make:webhook <module> <Name>Webhook <path> [--scheme=hmac-sha256]` (#321) writes a POST Api that
queues `<name>-webhook` with the delivery's body and answers 202, its `<Name>Payload` value and the
`<Name>WebhookJob`; the manifest gains the route and the job. It prints the `config/app.php` webhook
line, which the agent adds: the framework checks the signature at that path. `--scheme` takes only
`hmac-sha256`; a name not ending in `Webhook`, `Webhook` alone or a bad path is refused.

`make:page-action <module> <Name>Action --on=<path> [--region=content]` (#322) writes a POST Page at
`<path>/<kebab name>`, its `<Name>Step` (the one call, answering a refusal or nothing) and its
`<Name>View` (the region's form); the manifest gains the route. It prints where the view goes and the
gate:23 steps, and edits nothing else. `--on` must be a GET route some manifest declares, with no
parameters; a name not ending in `Action`, `Action` alone or a region not in lower case is refused.

`make:export <module> <Name>Export <path> [--days=7] [--format=csv] [--version=<v>]` (#323) writes the
POST Page that stores an export and queues `<name>-export`, its download Stream at `<path>/{id:uuid}`,
the write job, the prune job, `<Name>ExportRepository` and `Create<Name>ExportsTable` (the table and the
view saying which exports are past their days); the manifest gains both routes, both jobs, the prune's
daily schedule and the migration. It prints who may download and the gate:23 steps. A name not ending
in `Export`, `Export` alone, a path with a parameter, `--days` outside 1 to 365 or a format but `csv` is
refused.

`make:list-page <module> <Name>ListPage <table> <path> --fields=<name>:<type>,… --filters=<field>,…
[--sort=<field>,…] [--key=id] [--per-page=25]` (#324) writes a GET Page, its query value and reader in
`src/Form/`, its repository, its row and its view, over the framework's list atoms; the manifest gains
the route. It prints who may read the list and the gate:23 steps. A name not ending in `ListPage`, a
name before it longer than 20 characters, a path with a parameter, no filter, a filter or sort naming
no field, a filter that is not text, choice or boolean, more than 16 fields or 6 filters, or
`--per-page` outside 1 to 200 is refused.

`make:detail-page <module> <Name>DetailPage <table> <path> --fields=<name>:<type>,…
[--tabs=<tab>:<field>+<field>,…] [--key=id] [--uuid]` (#341) writes a GET Page at `<path>/{id:int}`
(`{id:uuid}` with `--uuid`), its repository and its view, over the framework's detail parts; the
manifest gains the route. The first field is the heading; without `--tabs` one tab, `details`, holds
every field. It prints who may read the page and the gate:23 steps. A name not ending in `DetailPage`,
a name before it longer than 20 characters, a path with a parameter or `/`, more than 16 fields or 8
tabs, a tab that is not a lower-case word or repeats, or a tab naming no field or one not in
`--fields` is refused.

`schema:import <dump.sql> --module=<m> [--tables=a,b] [--version=<v>] [--dry-run]` (#318) turns a
`pg_dump --schema-only` into baseline migrations: one `Create<Table>Table` per table, written after
the tables its foreign keys reference, with consecutive versions and the manifest's `->migration()`
calls, through the same make run. It reads the column types Schema has, identity, serial and uuid
keys, literal defaults, stored generated columns, a text column's `= ANY (ARRAY[…])` check as
`choice()`, checks, unique and primary keys, single-column foreign keys, and btree, partial, brin,
hash, gist and gin indexes, with every expression as `Schema::expressions()` calls. Anything else, a
function, trigger, view, another type, `ON UPDATE`, an expression index, a foreign key to a table
nothing creates, is listed one a line and nothing is written; no SQL is copied through. Settings,
owners and grants are dropped, and comments and other extensions are notes. `--tables` imports some
tables of a larger dump; a statement about no chosen table is then a note.

`make:entity <module> <Class> <table> --fields=<name>:<type>,… [--version=<v>]` (#123) writes the
entity, `<Class>Repository` (`latest(int)`), `Create<Table>Table` and `<Class>ListPage` (GET of the
table's path, the newest 50 rows as a heading and paragraphs in the framework's atoms), and adds the
route and the migration in one manifest edit. Types are `text`, `numeric`, `date` and `boolean`; a
field named `id` or `created_at`, a repeated field or an unknown type is a usage refusal. Each body
names its entity, so two made entities share no phpcpd run unless about five fields in a row match.

`bin/aic make:theme <name> --dry-run --json` previews the first theme: `src/Layouts/Page.php` with
four regions, the HTML and page templates in `layouts/` and real CSS. It prints the `config/app.php`, Composer
and PHPCS lines to add. Pages take the framework's `PageDocuments` (#154) and name their title (#187). Use lowercase words joined by hyphens, at
most 32 bytes. An existing file refuses before writes; reruns refuse instead of overwriting.

Composer must map `"App\\Theme\\Components\\": "themes/<theme>/src/Components/"`; run
`composer dump-autoload` before generation. Templates are `.phtml` and need no mapping: the build
escapes each into `build/generated/templates/`, where a lookup requires it (#155). Makes validate these mappings and do not edit tool configuration. Inspect the dry-run, declare every authored path and the module manifest,
and ground those paths with the code index. The ordinary path-based guard checks them all;
there is no special exemption for theme or template sources. The engine records `code.made`.
The build owns everything under `build/` and the wiki. No generated file is
written by a make. Run `composer run build` after generation.

## App scaffolds

An app adds its own make kinds as data (#298). A scaffold is a folder `scaffolds/<kind>/`:
`<kind>.scaffold.php` returns an `AIC\Workflow\Domain\Scaffold\ScaffoldManifest`, and `*.tpl`
templates sit beside it. AIC's root carries `scaffolds/poller/` as the fixture:

```php
return (new ScaffoldManifest('poller'))
    ->describe('A scheduled job that asks one service for what changed in an upstream system.')
    ->notFor('A one-off import someone runs by hand: use make:command.')
    ->from('job')
    ->arg('every', ArgType::Cron, '*/5 * * * *')
    ->file('src/Job/{name}Poll.php', 'job.php.tpl')
    ->file('src/Service/{name}Poller.php', 'service.php.tpl')
    ->job('{name|kebab}-poll', 'Job\\{name}Poll')
    ->schedule('{name|kebab}-poll', '{name|kebab}-poll', '{every}')
    ->probe(['name' => 'Probe']);
```

`bin/aic make poller site StockPrices --every='*/2 * * * *'` writes both classes under
`modules/site/` and adds the job and its schedule to the manifest, through the same run as the
built-in makes: the planner's collision checks, the guard's wall, the rollback and `code.made`.
`--dry-run` prints the files; the same kind and values always give the same bytes.

- **Arguments.** The module and the StudlyCaps `name` are positional; every other argument is
  `--<arg>=<value>`, typed by `ArgType`: `Pascal`, `Kebab`, `Cron`, `Choice` (with its choices) or
  `Int`. There is no path type, and no type admits a quote or a backslash. An argument without a
  default is required.
- **Templates.** Text with `{placeholder}` and `{placeholder|filter}` and nothing else: no loops,
  no conditions. The placeholders are `name`, `module`, `namespace` and the arguments; the filters
  are `camel`, `kebab`, `pascal`, `snake` and `upper`; `{{` writes a literal `{` (a route's
  `/notes/{{id}`). Path patterns and manifest values use the same grammar.
- **Manifest edits.** `route('Get'|'Post', <path>, <Class>)`, `job`, `schedule` (an empty
  payload), `command`, `block`, `migration`, `export` and `requires`, with classes relative to the
  module's namespace (`Job\{name}Poll`). A `requires` the manifest has already is skipped.
- **Load checks.** A scaffold is checked whole before anything is written: its kind (lower case
  joined by `-`, not a built-in's), its description, each argument, each path (inside the module
  folder, never twice), each template (present), every placeholder and filter, each edit's shape,
  and the probe values (the name and every argument without a default).

`bin/aic make:catalog [--json]` lists every kind from one list: the built-in makes, then each app
scaffold with its usage, the files it writes and its `notFor` lines. A scaffold that does not load
is listed with its refusal.

### A new kind: the scaffold run

An agent never writes a scaffold on its own (#299). When `make:catalog` has no kind that fits, it
asks (`bin/aic workflow:ask`), and the operator, from a terminal, either names a kind or approves
one: `bin/aic workflow:answer "scaffold <kind>"`. In that run only,
`bin/aic make:scaffold <kind> --from=<kind>` writes `scaffolds/<kind>/` from a kind that exists: an
app or AIC kind is copied with its kind renamed and `from()` naming the source; a built-in's
templates are copied (`{class}` read as `{name}`) beside a starting manifest that does not load
until the agent describes it. The agent declares the folder's files in the spec and edits them;
`make:catalog` says what is still missing.

- **The wall.** The guard refuses every write under `scaffolds/`, run or no run, at every lever,
  except in the folder of a kind the open run's operator approved.
- **The freeze.** The `scaffolds` row at code, check and complete holds every file under
  `scaffolds/` to `var/workflow/scaffolds.json`, but an approved kind's folder. Complete's row
  writes the record when it passes, so a change made between runs holds the next run until the
  operator approves a scaffold run for its kind. Before a first run completes nothing is frozen,
  and the row says so.

### The made ledger

A file a make creates in a run keeps its provenance in git (#302). Beside `code.made`, the make
appends a row per created file to `.aic/made.jsonl`: `path`, `kind` (the built-in's name, the app
kind's, or `scaffold`), `scaffold` (`sha256:` over the kind's template folder, the app's first, then
the package's; for make:scaffold the `--from` kind's), `content` (the `xxh128:` hash `code.made`
carries) and `run`. An edited manifest is not a made file. A make with no run open writes no row.

- **The wall.** The guard refuses every write under `.aic/`, at every lever; the declaration audit
  leaves the ledger out, as an engine write.
- **The `made` row.** At code, check and complete, the ledger must begin with the lines it had at
  the run's base (it only grows: a removed file keeps its row), and the lines after them must be
  exactly the rows this run's `code.made` events give. A hand-written row, or a made row taken out,
  holds the run.
- **`scaffold-only`**, a house rule AIC ships and an app opts into: every `.php` and `.phtml` file
  the run adds under `modules/` or `themes/` has a row. Edits to a made file stay free. It is not a
  default while most module kinds have no make (#295):

```php
new HouseRule(
    'scaffold-only',
    'Every class and template a run adds was made by a scaffold.',
    '{vendor}/ignibyte/aic-workflow/rules/scaffold-only.php',
),
```

`bin/aic code:coverage --made [--json]` reports, per module and theme, the files git lists, how
many a scaffold made and how many of those are unchanged since.

### Built-in templates and AIC's own kinds

Every built-in make writes its text from a template in the package's `scaffolds/<kind>/` (#301), in
the same grammar, filled by the same engine. The built-ins stay `make:<kind>` commands with their
own lines; a folder without a manifest is templates only, never a kind for `make`. What a built-in
computes stays PHP, as an engine step a template cannot express:

- `make:entity`: the fields (`--fields`) become properties, parameters, columns and reads.
- `make:migration` and `make:entity`: the version from the clock (`--version` overrides it).
- `make:page`: the component (`--component`) or its own view, the layout (`--layout`), or a POST
  (`--post`).
- `make:block`: the region, weight and prefix, and the sentence that names where it shows.
- `make:module`: the `->requires()` chain; every make: the manifest edit.

A folder in the package's `scaffolds/` that holds a manifest is one of AIC's own kinds: `make` and
`make:catalog` find it beside the app's, and an app kind of the same name is refused. AIC takes a
scaffold into core only when many apps would use it (Chad, 2026-11-08); promotion copies the app's
folder into the package unchanged, with its kind contract once #295 lands.

## Measure what the app uses

`bin/aic code:coverage` (#232) lists every framework surface an app can use (contracts, the kinds'
responses, the manifest, Schema and `EntityQuery` methods, the catalog atoms, the Tessera
vocabulary) with the app files that use each, or `unused`. `--missing` lists only the unused;
`--json` answers the `aic.console/1` envelope; `--made` reports the made ledger instead (#302). It
reads the code index, refreshed first, and exits
0 whatever is unused: it is a report, not a gate. What counts as a use is in
[the workflow docs](../framework/docs/workflow.md#code-index-and-scaffolds).

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

## Amending a frozen row

Planning freezes the spec's criteria, manifest, entry points, decisions and browser items, and
every later exit holds on a lost row. When a frozen row is wrong, such as an entry point's status,
the operator amends it from a terminal (#267):

```bash
bin/aic workflow:amend "<the row as the spec writes it>" "<the new row>" "<why>"
```

The row must be one whole line of the frozen spec, and the swap must change exactly one frozen
row of one section for another; the reason is required. The engine replaces the line in the
freeze, which keeps its time and gate list, and in the run's spec, and logs `spec.amended` with
both rows, the reason and the phase. Nothing is frozen at plan, so there it is refused. Like
`workflow:reset` and `workflow:answer`, it is refused without a terminal and walled from the
agent's shell.

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

## Browser looks

Front-end work is looked at with Playwright (#174). Plan refuses a File Manifest that touches a
theme (all but the data and text files in its `assets/`), `framework/templates/`, `tests/browser/`, `config/app.php`, a module's `Block/` or `Form/`,
or a `Controller/` class not named `*Api`, unless the spec says `browser_testable: Yes` and each
`## Browser Check` item names a look, a Playwright test in `tests/browser/`. At check the engine
runs every named look itself, with `vendor/bin/aic-gate browser --base-url=<site>` against
`config/workflow.php`'s site; the row holds while an item is unticked, a test is missing or a look
fails, and waits on the operator when no site is set. At complete each item ends, after `saw:`,
with `— keep` (the test stays, declared, and gate:23 runs it against the compiled site on every
gate run) or `— drop` (the test is deleted). The app's root `package.json` and `package-lock.json`
pin Playwright 1.62.1; `vendor/bin/aic-gate tools` runs `npm ci` and installs Chromium.

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

Use the browser to explore a page while writing its look. The browser row's evidence is the
look itself: the engine runs each named Playwright test at Check, so no tool call, copied
screenshot or HTTP request stands in for it.

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

## Source

The `ignibyte/aic-workflow` repository is a read-only split of `workflow/` in
[Ignibyte/aic-nextgen](https://github.com/Ignibyte/aic-nextgen), made on each release tag.
Issues and pull requests go there. MIT licensed.
