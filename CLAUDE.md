# CLAUDE.md — AIC nextgen

## What this is

The fresh start of **AIC** (Absolute Insanity, AI; AIAI), Ignibyte's AI-first, CMS-slim PHP
framework. Old AIC (`/srv/stacks/aic`, `Ignibyte/aic`) grew to 43 wards; on 2026-10-18 Chad
decided to start over with only what earns its place and move it across by copying and
trimming. This repo, `Ignibyte/aic-nextgen` (private), is where AIC lives: there is no
cutover (Chad, 2026-10-20; #11 closed). Old AIC (`Ignibyte/aic`) is the archive and the reference,
pinned at main `e0778896`.

**Read `docs/planning/handoff.md` first**, then `roadmap.md` (the standing rules, the five kinds,
the ten systems, the milestones) and `plan.md` (the decisions and the port map from old wards).
The architecture set an app's agent reads, shipped in `aic/framework`, is
[framework/docs/README.md](framework/docs/README.md); gate:29 holds it to the code.

## Status

Current order (Chad, 2026-10-23): upstream elephc tickets are parked. Codex integration
(#136) and workflow recovery (#144, #145) are delivered. Public assets (#55) are delivered;
the operator accepted the unresolved native-session defect separately under #150.
Continue framework fixes and declared themes (#146–#149) before further Lotline work
(#128, #132). The Lotline bootstrap is
held and unmerged in `/srv/stacks/aic-demo`; its resident agent stays open in the same tmux
conversation. Restore it only after actual process loss. The separate demo rebuild (#129)
is superseded. Old `/srv/stacks/lotline` is product and design reference; its source is not copied.

Approved theme direction: declarations live in module manifests. The site module declares the
active theme, HTML/page templates, named regions and assets. Feature modules declare their
component-to-template mappings. Author `themes/<name>/layouts/html.tpl.php` and `page.tpl.php`,
with module-owned component `.tpl.php` files. Compile templates into `generated/templates/`
and public assets into `public/assets/`. No separate theme registry or runtime discovery.
146/147 implement the declared app theme and Greetings pilot, verified by all32 gates, both full
Psalm passes,446 native classes,84 site comparisons,186 workflow expectations and compiled
desktop/mobile checks. make:component/make:page declare .tpl.php sources; make:theme scaffolds
the first selected site theme through the normal guard and manifest editor.148 verifies actual
installed-app generation, native/browser behavior and the complete186-expectation workflow replay.
149A migrates all reference inputs and root CSS into declarations and retires the unused AppLayout.
Its final32 gates and both full Psalm passes pass, with446 runtime classes and86 fresh site
comparisons, complete186-expectation workflow replay and22 compiled desktop/mobile checks.
The updated installed app also passes30 gates, all446 classes freshly probed and86 site steps.
An earlier native upload403 remains preserved and unexplained; these passes do not establish
a repair or its relationship to150.
149B completes required template binding APIs and declared-only lookup/wiki/scaffold behavior,
with all six architecture pages from120/121 and source-backed inventories across12 required pages.
Final AIC32/bothfullPsalm/446runtimeclasses/freshsite86 and complete186-expectation replay pass.
The installed-workflow source fixture passes30 gates/all446classes freshly; a separate normal
package-only App profile passes28 gates/bothfullPsalm/all163appclasses freshly/site86 without
changing any of14343 vendor/package files. Both compiled desktop/mobile flows pass22 checks.
Keep these profile scopes distinct. The native/session issue150 stays open; no repair is claimed.

#114 moves all 15 catalog atoms into fixed `framework/templates/components/*.tpl.php` declarations,
compiled by the same strict reader/compiler as module templates. The source-package build owns 20
runtime outputs (15 templates, 4 lookup parts and the head); installed Apps consume them without
package writes. Tag and quoted-attribute control flow emits no block comments. Body blocks retain
markers, while the fixed inline atom role preserves existing atom bytes and child paths. Branches
join compatible contexts and loops/exits preserve their entry; incomplete literal names and unsafe
output positions are refused before publication. Form callers pass the issued token's ready string.
All 3,282 original atom cases match. Each compiled browser flow passes 23 checks. The source fixture
passes 30 gates covering all 463 runtime classes; the normal App passes 28 covering its 163 classes.
The App build preserves 14,384 vendor/package files. Final Quality passes all 32 gates and both full
Psalm passes, with 463 valid native runtime closures and 86 fresh site comparisons. The complete
workflow replay passes all 186 expectations.

#133 runtime, #134 gate tooling, #141/#142/#143 workflow source repairs, #135 dual-pass enforcement
and #137 bounded teardown are delivered.
Complete approved ordinary analysis has zero findings. Gate:13 now runs ordinary analysis
then explicit taint analysis over the same full project, reporting both outcomes and all
output; either failure fails the gate. Method exception and unused-code checks remain on.
PHPStan level 10 holds return types; the approved `restrictReturnTypes=false` reason is in
root/shipped XML. Typed config failures stay handled at boot, with the approved global-scope
exception policy documented. Environment access stays config-only under PHPStan and semgrep.
Both clients use the installed workflow. Native Codex usage and measured limits are in `workflow/README.md`.

M0, M1 and M2 are done (2026-10-19).
- **M0:** the workspace (#1), the gate suite (#2), the four-phase workflow (#3) and the native gate (#14).
- **M1:** typed config, PSR-7/17 with typed input, routing and the PSR-15 stack, the kernel and the composition root, the kinds, and plugins (#4 as #18 to #22, then #5).
- **M2:** the database, as #28 to #31 under epic #6:
  - `AIC\Database` over PDO pgsql;
  - the Command kind, `AIC\Console` and `bin/aic`;
  - `AIC\Schema`, migrations with a checksummed ledger;
  - `AIC\Entity`, CRUD on `notes` from the demo app's pages.

**#33 made the compiled site the runtime.** FrankenPHP is set aside (Chad, 2026-10-19).
- `bootstrap/serve.php` serves one request: under php-fpm (DDEV's reference, `public/index.php`), under `php -S`, and compiled with `elephc --web`.
- gate:23 builds the whole app natively, serves it beside PHP, and compares86 configured steps.
- `composer run native:serve` serves the compiled site on 127.0.0.1:8090.

#26: `build/surface.json` lists every route and command with its kind, written by `composer run
build`; gate:24 keeps it fresh. #25: byte ranges (206/416) on downloads and Server-Sent Events
(`EventStream` pulling from an `EventSource`, streamed natively). #7 (M3) runs as #35 to #39;
#35: `AIC\Component` (the catalog atoms with a caller; data since #98). #36/#149: a
component's declared `.tpl.php` compiles under `generated/templates/` (`composer run build`, kept fresh by
gate:24); the generated class passes every gate unexcluded. #37: a Page returns
`PageDocument(Layout)`; regions render as `data-region` elements, components and blocks between
`<!--aic:...-->` markers. #38: blocks (`AIC\Block`) place themselves in a region by weight and
route prefix, as tagged plugins. #39 introduced bundled theme CSS (gate:24; `assets/` since #95).
#55 now publishes fingerprinted assets and exact compatibility aliases; the compiled framework
serves them before dynamic application assembly. #7 and M3
are done. #8 runs as #41 to #44; #41: `AIC\Session` (a Postgres session behind
`__Host-aic_session`, on the request as `aic.session`). #42: `FormGuardMiddleware` checks the CSRF
token and the honeypot on every unsafe request; the `Form` atom takes the issued `FormToken::value()`
string prepared by its caller. #43: the app secret comes only from `AIC_APP_SECRET`
(DDEV's ignored `config.local.yaml` here); `EnvelopeCodec` signs form values, `SameOriginPath` keeps
redirects on the site. #44: multipart posts become PSR-7 uploads; `UploadCheck` accepts a file by
its content. #8 is done. #9: `AIC\Auth` (pgcrypto bcrypt, `Authenticator`, protected prefixes);
M4 is done. #13 runs as #46 to #48; #46: the Job kind and a Postgres queue (`bin/aic
queue:work`). #47: schedules in code, `bin/aic schedule:run` (once per minute, by a ledger). #48:
notifications (our SMTP client to Mailpit, and the database), each delivery a job; #13 is done.
#10 runs as #49 and #50; #49: the app in feature folders, a home page that links every demo,
`/health`. #50: content pages by slug (`App\Content`). #10 is done. There is no cutover (#11
closed). The order from here (Chad, 2026-10-20): the compiler carries (done: #17, #24, #45, #51 and
part of #23), compiler polish with workaround removal (#53 done; #23 and #34 open), Tessera (#12,
`Ignibyte/tessera`), then a real site. WebSockets are a research ticket (#52) that #32 waits on.
#12's server half runs as #70 to #74; #70: Tessera 0.1.0 pinned in `config/app.php` and built into
`public/assets/tessera-0.1.0/`, `<!--t:c-->`/`<!--t:b-->` markers, fragment answers (`AIC\Tessera`),
`Vary`. #71: redirects as `Tessera-Location` (`TesseraRedirectMiddleware`), `422`/`409` regions in
place, `419` for a stale token, `PageFullVisit`; the demo is enhanced throughout. #72: a write is
applied once per `Tessera-Write-Id` (`TesseraWriteMiddleware`, `aic_tessera_writes`). #73: streams
speak Tessera (`TesseraEvents`, ids, `tessera-close`, `Last-Event-ID`); `/ticks` streams live.
#74: signed props tokens on `KeyedComponent`, verified by `TesseraProps`. #12's server half is done.
M8a (the app shape) runs as #92 to #109; #92: modules, a manifest per `modules/<name>/` read by
`bin/aic-build`, which writes the wiring into `generated/` (pilot: `modules/faq/`). #93: the demo
is ten modules and `app/` is gone; `AppKernel` and `ConsoleKernel` compose the framework's own
wiring around the generated pieces. #94: gate:26 holds the module boundaries (a class's kind
decides its `src/` folder, 15 files per folder, exports and requires, nothing outside `modules/`
but the shell). #95: module assets: the theme moves to `assets/`, each module's
`assets/` joins the build, and the site module's nav styles and favicon live in `modules/site/assets/`.
#96/#149: gate:25 accepts only declared `*.tpl.php` templates, auditing unsupported and unclaimed
authored sources for refusal. Class tools skip authored module, theme layout and fixed framework
atom sources; all compiled classes remain checked.
#97 to #99 (#87, #88): templates compile from plain PHP, one Renderer makes HTML, and gate:27 counts
`RawHtml` against its grants. #97: `composer run build` compiles each
declared `*.tpl.php` into its explicit generated class, escaping every `<?= ?>` by its static type; loops are real
loops (`break`, `continue`) and `empty()` lowers by type; the old grammar is gone. A component
is data: its constructor takes the ready values as promoted public props, computed by whoever
builds it, and a class-typed loop variable is named for its class (`$keyedComponent`). #98: one
Renderer makes HTML. `Component` declares no method; `AIC\Component\Application\Renderer` is the
only `new Html`, and `Psr7ResponseWriter` the only reader of `markup()`. A page answers a layout of
regions holding components (`HtmlLayout::content(list<Component>, int)`), never a string. A
template writes children through a `RenderPass` (`child()` with markers, `region()`, `inline()` in a
layout), and a Tessera fragment is the same tree rendered by its path. The build generates the
lookup (`generated/ModuleTemplates` and one `<M>Templates` per module), ending at the compiled
framework atom lookup; a module component with no template is refused. #91, the agent workflow,
runs as #100 to #106; #100: the run engine and its state, the dev-only `aic/workflow` package.
#101: the engine runs the gates itself and keeps each verdict in `var/workflow/evidence.sqlite`
beside the subject hash of the tree it judged. #102: the guard hook holds the agent to the open run.
#104: house rules judge each run's change, frozen at begin; the probe sweep is #119.
#106: the code index (`bin/aic code:index|tree|search|refs`); the plan check reading it is #118.
#109 (part 1): the inspect commands, dev-only in `aic/workflow`: `bin/aic route:list`,
`command:list`, `job:list`, `schedule:list`, `module:list`, `module:show <name>` and `kind:list`,
answered from `build/surface.json` (now with each command's, job's and schedule's module, the
modules' bindings, middleware, prefixes and factories, and a `kinds` table), text or `--json`
(`aic.console/1`, `workflow/schema/console.v1.json`), each lookup recorded as `code.queried`;
make:* is #122. #118: plan's grounding check (`GroundingCheck`, after the contract): on an index it
rebuilds in full, every File Manifest row and Entry Points class must be real, and the run's own
`code.queried` events at plan must show `code:tree`, a lookup of each modified or deleted file
and existing class (`code:refs` counts its subject only), the area of each created file, and a
`code:search` before each new class; `var/index/` is walled. #108: the code wiki, `docs/wiki/`
(`README.md` and `modules/<m>.md`), written by `composer run build` as its last step from the
surface and the code index, held by gate:24, read live with `bin/aic code:wiki [<module>|<Class>]`;
a module's notes go in `modules/<m>/README.md`. After any change under `modules/`, run
`composer run build` and commit `docs/wiki/` with it. #125: the guard judges a write by where it
really lands and refuses, at every lever, one outside the project (symlinked `vendor/aic/*`
included) or under `vendor/`; only `/dev/` is open outside.
#105: `bin/aic workflow:init` installs the workflow in an app (`workflow/pack/`); it refuses here.
#103: the seeker, an `aic-seeker` subagent that reads each run's change against the AIC checklist;
#127 made it one advisory pass at code, on the diff plus one hop. #126 (part A): the root is a
three-module fixture app; a module's handlers build in parts that fit (`HandlerParts`). #116 (part 1):
the gate suite is the dev package `aic/gates`, run by `vendor/bin/aic-gate`. #130: the app side,
profiles, stubs, `aic-gate init`, gate:30 and `config/native-site.json`. #117: modes and holds (agentic or interactive, `workflow:ask` and the
operator's `workflow:answer`, the stall rule, the shell's read forms, the php wall).
#122: `bin/aic make:*` (module, page, api, component, block, command, job, migration; dev-only in
`aic/workflow`) write gate-clean classes and add the manifest call, held by the guard while a run
is open and recorded as `code.made`; gate:31 holds `modules/scaffold/` to the ten make lines.
#119: `bin/aic workflow:probe [--scratch=<dir>] [--keep] [<scenario>…]` replays the engine's planted
cases (`workflow/probes/sweep/`) in throwaway apps made by `workflow/probes/scratch-app.sh`, the
builder the seeker probe shares; it ends `probe-summary: P passed, F failed, N not run.`, exits 0
only with F = N = 0, runs here only and never beside a gate run, and is not a gate. **Run it in
Code of every ticket that changes `workflow/`, the gate runner, the gate list or a tool config
naming `workflow/`, and paste its summary in the notes** (about 25 minutes; while coding, run the
scenarios the change touches by name).

## Five kinds

Every entry point is one of five kinds, each one interface with one method and one sealed
response type: **Page** (HTML document, region fragments or a redirect; form controllers are
Pages), **Api** (JSON, XML, SOAP), **Stream** (SSE, file downloads), **Command** (CLI, exit code)
and **Job** (queued or scheduled work: done, retry, fail). A static guard makes every entry-point
class implement exactly one, and the build writes a manifest of every entry point with its kind.
Background work is a job; the HTTP server's processes are its workers.

## Stack

- PHP **8.5**, strict types everywhere.
- **The compiled site is the runtime** (Chad, 2026-10-19, #33). `elephc --web --web-isolation=pool` compiles the class bundle and `bootstrap/serve.php` into one binary, a prefork HTTP server.
  - It keeps N long-lived workers, each with persistent PHP handler processes; there is no process per request.
  - Each request runs the entry with fresh PHP state, as under PHP-FPM, so nothing carries over between requests and no request scope is needed.
  - Pool isolation is the model elephc's PDO drivers run under: worker isolation panics in PDO pgsql (a compiler carry).
  - Front it with Caddy or nginx for TLS and static files.
  - No FrankenPHP, no Swoole, no coroutines.
- **The interpreted reference** is the same entry under PHP: DDEV serves `public/index.php` through nginx-fpm at `https://aic-nextgen.ddev.site:8843`, and gate:23 runs it with `php -S`.
- Postgres 17 through DDEV.
- PSR at every seam (PSR-3 is gone with logging; PSR-4, 7, 11, 14 if events survive, 15, 17).

## Layout (working decision, ticket #1)

- One package, **`aic/framework`**, in `framework/`, namespace `AIC\` with one namespace per
  system (`AIC\Http`, `AIC\Routing`, `AIC\Entity`, ...). It reaches the root through a composer
  path repository and moves to its own repo when published.
- The repo root is AIC's fixture app, `aic/app` (#126): four modules under `modules/`, three cut
  from the ten-module demo (the tag `demo-reference`; rebuilt in `Ignibyte/aic-demo` by #128 and
  #129). `site` holds home, greeting and job; `note` holds stock; `account` holds auth and
  session; content and faq were dropped. `scaffold` (#122) is what the make commands make, held
  by gate:31: regenerate it, never edit it by hand. It is AIC's test bed, not a showcase: a feature the
  gates need is added here with its framework change. Each module has its classes
  in `modules/<name>/src/<Kind>/` as `App\<Name>\<Kind>\` (`Controller`, `Component`, `Block`,
  `Form`, `Entity`, `Repository`, `Service`, `Command`, `Job`, `Migration`). There is no `app/`
  and no hand-written composition class (#93). Selected HTML/page sources live in
  `themes/<name>/layouts/*.tpl.php`; explicit module component templates compile into
  `generated/templates/` with the dedicated `App\Generated\Templates` PSR-4 prefix. Every
  reference input is explicitly declared; the old directory selector is removed. The reader, compiler and lookup require actual bindings without basename or companion guesses.
- Module assets are explicit `ModuleAsset` declarations through the manifest's existing `with()`
  seam (#55). Nested owner-relative sources and typed Style, Script or File roles are validated
  at build time. Declared styles join the combined CSS in dependency/declaration order, followed
  by declared theme styles in declaration order. The root CSS adapter is removed; the same
  source bytes now live in the selected theme's assets.
- `composer run build` owns `public/assets/`, the typed `PublicAssets` lookup and private
  `build/assets.json`. A full-set SHA-256 directory supplies canonical URLs; exact compatibility
  aliases preserve bytes, including adjacent pinned Tessera script/map. Resolve logical names
  through the catalog; publishing a script does not automatically select it for a page.
- The early framework asset handler bypasses module factories, sessions and database work.
  Canonical assets cache for one year with `immutable`; aliases use `public, no-cache`.
  Strong ETags, strict HTTP dates and ordered preconditions/ranges use verified bytes and
  modification time from the same opened handle. Dynamic responses default to `private, no-store`.
  Deploy binary, catalog and public files together in an isolated release root and retain prior
  releases for old URLs; the build's current-set cleanup is not atomic in-place deployment.
  See [the asset contract](framework/docs/modules.md#assets). Gate:24 rejects stale outputs.
- **`aic/workflow`** (#100), in `workflow/`, namespace `AIC\Workflow\`: the agent workflow's
  engine, a second package that is dev-only (`require-dev`, a path repository like the
  framework's; Chad, 2026-10-22). `bin/aic` lists its commands when it is installed:
  `workflow:run <ticket>` (one phase per call, plan → code → check → complete),
  `workflow:status`, `workflow:logs gate[:N]` (#144), `workflow:ask "<question>"` (the agent's, #117),
  and `workflow:reset` and
  `workflow:answer advance|stay|"<answer>"` (the operator's, from a terminal only). An app opts in
  with `config/workflow.php`. The engine alone writes `var/workflow/` (gitignored): `run.json`,
  the append-only `events.jsonl` (`workflow/schema/run-event.v1.json`) and `history/`. phpat
  keeps runtime code off it and it off everything but the Command kind; gate:21 does not probe
  it, since it runs interpreted.
  - **Stored diagnostics** (#144): `workflow:logs gate:N` reads full stored output for the
    open/latest archived run. Select `gate` for the aggregate, including abnormal execution.
    Optional run/phase/attempt selectors and explicit line windows work with `--json`.
    Read-only lookup never creates or migrates evidence, reruns gates or establishes freshness.
    Selection completeness and capture provenance are separate; old capped output stays unknown.
    After prerequisite recovery the seeker's first actionable inspection is due (#145); an
    unchanged repeat still holds. See `workflow/README.md` for command and exit semantics.
  - **Its checks** (#101), each a `PhaseCheck` registered in `WorkflowChecks`: at plan the spec
    must read as a contract and is frozen with the gate list; at code, check and complete the
    engine runs `composer run gate`, the freeze, the declaration audit (exact paths from the
    spec's `### File Manifest`) and the composition check (the spec's `## Entry Points` against
    `build/surface.json`); check and complete add the rendered check against `site` in
    `config/workflow.php` (with no site, declared GET routes hold the run for the operator,
    #105); complete adds the criteria, each `Met` cell citing a fresh green check.
  - A verdict is fresh only on the tree it judged; a deferral is never a pass, and a deferred
    gate:21 or gate:23 holds complete. `maxGateRetries` failures of one check at a phase leave
    the phase to the operator.
  - **The guard** (#102): `workflow/bin/aic-guard.sh <edit|shell|record|stop|returned>`, wired by
    `workflow/pack/hooks.json` (#105 installs it into an app); the shim hands the guard the
    project's autoloader, which a path-repository link hides from `__DIR__`. It refuses writes outside the
    phase's scope (no run: the ticket directory; plan: the spec; code, check and complete: the
    spec and the File Manifest), any write to `var/workflow/`, `config/workflow.php`, the hook
    wiring or the package (MCP tools with a path field included), and the operator's verbs in the
    agent's shell. It holds Stop mid-phase. A row only the operator can clear (`Held`) leaves the
    run `awaiting` the operator, whose `workflow:run` from a terminal resumes it. Every call is a
    line in `var/workflow/guard-calls.jsonl`, which `workflow:run` ingests into `tool_call`.
    Levers: `enforcement` (frozen at begin) and `requireRun`, each hard, soft or off.
  - **House rules** (#104): `WorkflowSettings::$houseRules` names `HouseRule`s, PHP files run as
    their own process on the run's change (JSON on stdin; exit 0 holds, 1 breaks, 4 defers, any
    other did not run). The engine hands a rule base copies (`var/workflow/rule-base/<path>.base`),
    so a rule runs no git. `rule_files` (config/workflow.php, each rule, each `<rule>.probe/`) is
    frozen by sha256 at begin and walled by the guard; a change holds the run (`rules`). Plan runs
    every rule's probe (`probes`; held for the operator when one does not bite); code, check and
    complete run `rules` and one `rule:<id>` each. AIC ships `gate-suite` and `raw-html-grants`
    in `workflow/rules/`; gate:28 runs their probes.
  - **The code index** (#106): php-parser (in `AIC\Workflow\Parsing\`, the only Deptrac layer
    that reaches it) over every file git lists (and an app's `vendor/aic/framework/src`), plus the
    build's surface (routes, commands, jobs, modules), in `var/index/code.sqlite`. A refresh
    re-parses only what changed, and every query refreshes first. `bin/aic code:index [--full]`,
    `code:tree [<module|System|path>]`, `code:search <text>` and `code:refs <name>` need no
    `config/workflow.php`. While a run is open, each lookup appends `code.queried` (the files and
    scopes it showed) to the run's event log, which the guard walls. gate:2 admits
    `nikic/php-parser` for `workflow/composer.json` only.
  - **The seeker** (#103, one advisory pass since #127): with every other row green, code answers
    `inspection-due` (`CheckStatus::Due`, exit 1, no retry budget spent) until one counted ledger
    is filed; complete runs no seeker. The coding agent dispatches the `aic-seeker` subagent
    (`workflow/pack/claude/agents/aic-seeker.md`); it reads `bin/aic workflow:seeker-brief` (the
    spec, the changed paths and `one_hop`, the files one code-index edge from each), the diff and
    `workflow/pack/seeker/checklist.md`, and answers each item `pass` or `fail <file>:<line>` before
    a ledger the agent pipes to `bin/aic workflow:seeker-report`. The ledger names the subject it
    read and cites a checklist id per finding; it counts only when the guard's `returned` row
    (PostToolUse `Agent`) holds its digest. No finding holds the run at any severity: the row
    passes and names each open one, and `workflow:status` shows it. `seeker-report` opens at code
    only and refuses a second counted ledger. `seeker_inspection` and `seeker_finding` (evidence
    schema v3) and `seeker.filed` keep the record. The guard refuses every write made inside an
    `aic-seeker`. `workflow/probes/seeker-checklist.sh` counts what the seeker catches per item.
  - **The installer** (#105): `bin/aic workflow:init [--client=claude|codex] [--force[=<path>,...]]` needs no config and
    refuses in this repo (the package's own source). `PackManifest` is the one list of what it
    writes from `workflow/pack/` (`claude/` stands for `.claude/`, since gate:19 skips `.claude`):
    the `aic-workflow-*` skills and `/aic:workflow:*` commands, `config/workflow.php` once,
    `<tickets>/README.md`, marker regions in `AGENTS.md` and `CLAUDE.md`, and `hooks.json`
    merged into `.claude/settings.json` (fail-closed commands, `CLAUDE_CODE_STOP_HOOK_BLOCK_CAP`).
    `.claude/aic-workflow.lock` (committed) hashes what init wrote, so edits are kept;
    `--force` is the operator's, from a terminal. The guard walls the pack's files and the lock.
    The pack is written for an app: semgrep keeps AIC's own process and `claude/` links out of it.
    Claude remains the default. Explicit Codex installation exports the shared skills to `.agents/skills`,
    installs `.codex/agents/aic-seeker.toml`, merges six native callbacks into `.codex/hooks.json`,
    and owns its instructions through `.codex/aic-workflow.lock`. The operator reviews the exact
    definitions in Codex `/hooks` and runs `bin/aic workflow:codex-check` before launch, outside
    the tool sandbox. Native hooks bind actual parent/child identity, judge every patch path and
    run approved shells at the project root without interactive child input. Client startup
    failures can fail open; [workflow/README.md](workflow/README.md) states the coverage and limits.
    These app hooks do not enforce this maintainer checkout's manual phases.
- No `aic/ward-*` packages, no metapackage, no ward manifests.
- **Modules** (#90, #92, #93): `modules/<name>/<name>.module.php` returns a fluent
  `AIC\Module\Contracts\ModuleManifest`, naming only what the module has:
  `(new ModuleManifest('job'))->requires('site')->route(…)->job(…)->schedule(…)->migration(…)`.
  The other declarations are `binding`, `block`, `plugin`, `export`, `command`, `middleware` (with
  a `MiddlewarePosition`), `protectedPrefix` and `factory`.
  - `#[Tagged(Type::class)]` on a constructor's list parameter takes the plugins of that type.
  - A factory is a readonly module class whose methods return services built from values; a
    constructor asking for a method's return type gets its product.
  - `composer run build` checks every manifest and writes the wiring into `generated/`
    (`App\Generated\`). Per module, `<M>Routes`, `<M>Handlers`, `<M>Middleware`, `<M>Commands`,
    `<M>Jobs`, `<M>Provider`, `<M>Migrations`, `<M>Schedules` and `<M>Prefixes`. Then the
    aggregators, chained in `…Part2` and on, and the facades `WebModules` and `ConsoleModules`
    that `AppKernel` and `ConsoleKernel` take.
  - A generated class takes the context only when it reads a root (the `Rootless…Wiring`
    interfaces). Never edit those files.
  - A new module needs one PSR-4 line in composer.json (then `composer dump-autoload`) and one
    PHPCS root in `phpcs.xml.dist`; the build prints both when missing.

## Tickets and planning

`docs/planning/` is the private repo **`Ignibyte/aic-planning`**, a git checkout of its own that
this repo ignores. It holds `plan.md` and `tickets/` (one `NNNN-slug.md` per ticket, numbered from
#1). **`bin/ticket.sh`** is the interface (`list`, `show`, `next`, `create`, `update`,
`comment`, `close`); format in `docs/planning/tickets/README.md`.

Ticket changes are committed and pushed in `docs/planning`, not here. Old AIC's tickets are
"AIC #N".

## Workflow

**Read `CONSTITUTION.md`**: its rules bind, §0 (gates) and §14 (code standards) above all. Start
work with `/work <ticket>`; a trivial change takes `/work-lite`.

Four phases: **Plan** (spec, design, file list, browser checks for UI work; Chad confirms),
**Code** (no tests; browser-testable work is checked live in a browser on this box, see On this box), **Quality
checks** (`composer run gate` until green, the only gate run), **Documentation** (docs, plan
updates, changelog, then delivery). AIC writes **no unit, mutation, coverage or regression
tests** for now (Chad may bring them back once the framework is done); an invariant that must hold ships as a static guard (PHPStan, phpat or semgrep rule)
proven to bite by planting a violation.

**Native from the first class.** A ticket that adds runtime code is done only when every runtime
class compiles, links, runs and matches PHP under elephc: gate:21, `composer run gate:21`. Restructure AIC
code into a compilable form first; legal PHP that elephc rejects or miscompiles is a compiler
carry (the `elephc-carry` workflow, a ticket in `docs/planning`). The handoff lists the friction
to expect and the toolchain rules.

The commands are `/pipeline:plan`, `/pipeline:implement`, `/pipeline:check` and
`/pipeline:complete`, then `/commit`. Each pipeline is a spec and notes pair in
`docs/planning/pipeline/active/`, from `_templates/`, archived to `completed/` at delivery. The
hooks act while a workflow command runs: the phase gate, each phase's write scope, docs before
code, and each phase's evidence before Stop. "Auto approve" or "back to back" means run the
phases through without stopping for confirmation.

Delivery: a branch named for the ticket (`chore/skeleton-1`), a commit and a PR here, the ticket
update committed and pushed in `docs/planning`. Every commit is pushed with a PR. **Claude merges
its own PRs** once the work is done and its checks pass (Chad, 2026-10-19: he does not review PRs),
then deletes the branch locally and on the remote. Reports are terse: what was done, what failed
or was split off, what to check by hand.

## Porting from old AIC

- Copy from `/srv/stacks/aic` at `e0778896` and trim. Never require an old `aic/ward-*` package.
- Cut every edge to a removed system on the way in (the list is in `plan.md`). A class that only
  served a removed system stays behind.
- Keep native-compilation discipline from the first commit: build-time manifests, no
  runtime-dynamic `require`/`include`, the build-time reflection registry, no static
  cross-request state.

## Quality gates (binding)

`composer run gate` runs every gate on the host (no DDEV), about 15 seconds, and ends with one
`gate-summary:` line, after one `gate-result: gate:N passed|deferred|failed` line per gate, which
the workflow engine reads (#101). AIC's own tickets still run the suite in Phase 3. **No baselines. No suppressions. Source fixes only.** The runner
(aic/gates' `gate-runner.sh`, #116) runs every gate of the suite (`vendor/bin/aic-gate --numbers`,
from `Gates\Application\GateSuite`), each as `aic-gate N`, and keeps going after a failure, so one
run names every failing gate. Each `gate:N` script in `composer.json` is the one-line alias
`aic-gate N`, and `vendor/bin/aic-gate --list` names each gate with its scope. A gate that cannot run in full yet *defers* and
says why; the summary names each deferral, and a deferral is not coverage.

The settings of each gate are in `CONSTITUTION.md` §0.

| # | Gate | # | Gate |
|---|---|---|---|
| 0 | composer audit (installed packages) | 11 | PHPCS, PSR-12 + slevomat |
| 1 | composer-normalize | 12 | Deptrac, the layer ladder per system |
| 2 | runtime require allow-list | 13 | Psalm full ordinary and taint passes |
| 3 | require-checker | 14 | phpcpd (copy-paste) |
| 4 | composer-unused | 15 | semgrep, `gates/config/semgrep.yml` |
| 5 | no baselines, no suppressions | 16 | no runtime-dynamic require |
| 6 | Pint | 17 | gitleaks |
| 7 | Rector (dry run) | 18 | shellcheck |
| 8 | PHPStan level 10, bleeding edge | 19 | docs: no `(TODO)`, no dead links |
| 9 | phpat, `gates/architecture/` | 20 | captainhook hooks installed |
| 10 | PHPMD, every ruleset | 21 | native: elephc compile, link, run, match PHP |
| | | 22 | pipeline docs: frontmatter, line caps, links |
| | | 23 | native site: the compiled app answers as PHP does |
| | | 24 | build manifests: `build/surface.json` is fresh |
| | | 25 | templates: the plain PHP grammar, deny by default |
| | | 26 | module boundaries: kind folders, the 15-file cap, exports and requires, the shell |
| | | 27 | raw html: every RawHtml use listed and held to its grant |
| | | 28 | house rules: every shipped rule bites on its probe |
| | | 29 | architecture docs: `framework/docs/` states what the code has |
| | | 30 | suite files: the root configs are aic/gates' stubs, composer.json's suite lines as shipped |
| | | 31 | make scaffold: `modules/scaffold/` is what the make lines make |

- The gate code is the dev-only package `aic/gates` in `gates/` (#116; namespace `Gates\`,
  outside `AIC\` and `App\`), required by path like `aic/workflow`; a phpat rule keeps runtime
  code from depending on it. It holds the code (`src/`, `architecture/`), the entries, the build,
  the runner and the tool installer (`bin/`, every entry rooted at the working directory), the
  base configs (`config/`) and the elephc pins (`native/`). AIC's root `phpstan.neon`,
  `phpstan-architecture.neon`, `psalm.xml`, `deptrac.yaml`, `phpcs.xml.dist` and `rector.php`
  include their bases and name AIC's paths; `pint.json` and `captainhook.json` stay whole at the
  root (CaptainHook resolves paths against its config's directory).
- **Profiles** (#130): `ProjectProfile` decides `Aic` when `gates/composer.json` names
  `aic/gates`, `App` otherwise, and gives `GateRoots` (class, source, runtime and site roots, the
  manifests, the script folders, gate:26's shell). An app runs every gate but 22, 28 and 29;
  gate:21 probes its `modules/` and `generated/`, and gate:23 compiles them with the framework from
  `vendor/`. gate:23's steps are `config/native-site.json` (`aic.native-site/1`, one step a
  line; with none the gate defers).
- **An app's suite files** (#130): `gates/stubs/` holds the eight root files an app keeps.
  `vendor/bin/aic-gate init` writes each that is missing or differs (`phpcs.xml.dist` with one
  root per module), appends the ignore lines, prints composer.json's lines and never writes it;
  it refuses here. gate:30 (`SuiteFilesGate`) holds the stubs and composer.json's suite lines; in
  AIC it holds `pint.json`, `captainhook.json` and the `gate:N` aliases. In both profiles it also
  compares `config/phpcs-custom.xml` and `config/phpmd-custom.xml` inside the installed
  `aic/gates` package with their writers. The AIC source build writes these two files; app builds leave the package alone.
- The base NEON files include `phpstan-rules.php` and `phpstan-architecture-rules.php` in
  `gates/config/`. These PHP configs register the actual rule classes and constructor factories;
  PHPat services keep the `phpat.test` tag, and their attributed callables return every named rule.
  A class that is not registered never runs. XML registration writers reference the actual custom
  PHPCS/PHPMD classes; changing a writer requires `composer run build` and fresh gate:30 output.
- Root and shipped Rector configs call `RectorSetup` with their own root and source paths.
  Psalm's configured bootstrap loads the installed PHPCS autoloader and token definitions so
  analysis reads the real declarations.
- PHPMD holds old AIC's Constitution limits (method <= 30 lines, class <= 300, <= 15 methods,
  coupling <= 15). Raise one only when code needs it, with the reason beside the value.
- A new gate or rule is done only when it has been seen to fail on a planted violation.
- Before a final run: `rm -f .phpcs-cache` (a stale cache passes PHPCS falsely).
- gate:21 probes each class under `framework/src`, `modules` and `generated` on its own: its class-graph closure,
  compiled with the fork's elephc in place (`/srv/stacks/elephc/target/release/elephc`;
  `ELEPHC_BIN` overrides), run, and compared with host PHP. Work files land in `.native-gate/`
  (gitignored); pcre2 and oniguruma are pinned in aic/gates' `native/elephc.{toml,lock}`. Everything
  outside those roots runs interpreted by design.
- gate:25 (`gates/bin/gate-templates.php`, #96/#149) audits authored candidates under modules/,
  themes/ and the unsupported old templates/ root. It parses only actual declared `.tpl.php`
  sources with php-parser (`Gates\Templates\`), deny by default.
  - **Allowed:** inline HTML; `<?= one expression ?>`; `if`, `switch`, `for` and `foreach` in
    the alternative syntax; `match`; `break` and `continue`; the component variable (from the
    first line's `<?php /** @var \Fqcn\Name $name */ ?>`) and loop variables; property and
    array reads, `?->`; literals, comparisons, `&& || !`, `?:`, `??`, `isset()`, `empty()`,
    `.` and arithmetic; enum cases and class constants (`Status::Sold`).
  - **Refused:** every function (`TemplateGrammar::functions()` is empty), method and static
    call, `::class`, `new`, closures, assignment outside a `for` header, `while` and
    `do`/`while`, `<?php echo`, declarations, superglobals, and anything else.
  - **Placement:** declared portable `.tpl.php` under `modules/<m>/templates/components/` or
    `themes/<theme>/layouts/`; no manifest-free or old-input mode.
  - **The class tools skip templates,** by Chad's decision, and so do the Stop hooks. A tool
    handed a template by explicit path ignores its excludes (Pint and phpcbf then rewrite it),
    so never pass a template to a fixer.
- gate:26 (`gates/bin/gate-modules.php`, #94) reads the manifests and `git ls-files -co
  --exclude-standard`. A class's kind (`Gates\Domain\ClassKinds`, shared with the surface
  manifest) decides its folder: Page, Api and Stream in `src/Controller/`, then `Command/`,
  `Job/`, `Block/`, `Component/` (authored data; compiled templates under generated/), `Notification/`, `Middleware/`,
  `Migration/`, `Repository/` and `Form/` by name suffix. A plain class goes in `Entity/`,
  `Service/` or `Form/`. Its message names the folder, the `->export(...)` or the
  `->requires(...)` to add; the shell list is a constant in `ModuleShellRule`.
- Components only (#99), as gate:8 rules (`gates/src/PhpStan`) and a gate:9 phpat test:
  - `aic.htmlConstruction`: only `Renderer` makes an `Html`.
  - `aic.htmlMarkupRead`: only `Psr7ResponseWriter` reads one, a first-class callable included.
  - In a module's `src/Controller/`: `aic.controllerMarkupLiteral` refuses any string holding
    `<`, and `aic.controllerReturnType` refuses a method returning anything but its kind's
    response, so helpers go to `src/Service/`.
  - `aic.renderTreeInput` and `RenderingArchitectureTest`: `Region`, `Regions` and every
    `Layout` take components, blocks, regions, enums and scalars, never `Html`.
- gate:27 (`gates/bin/gate-raw-html.php`, `Gates\Rendering`, #99) lists every `new RawHtml` and holds
  each module class to its grant in `config/raw-html.php` (`RawHtmlGrant`: class, exact uses,
  reason). `RawHtml::` may appear only in `Renderer` and `RenderPass`. `GATE_RAW_HTML_ROOT`
  takes a plant root.
- gate:28 (`gates/bin/gate-house-rules.php`, #104) runs every `workflow/rules/*.php` rule on its
  `.probe/` cases in a temporary directory: each `held-*` must exit 0, each `broken-*` 1.
- gate:21 caches (#110): a class whose closure files, compiler, bridge libraries, PHP, pinned
  packages and probe program are unchanged since its last green probe reports `CACHED`
  (`.native-gate/cache.txt`); a change reprobes only the classes whose closure holds it. Probes
  run 8 at a time under `nice -n 10`; `AIC_NATIVE_JOBS` sets the cap. A cold run takes under a
  minute, an unchanged tree under a second.
- `vendor/bin/aic-gate tools` installs gitleaks (pinned, into `tools/`), semgrep and shellcheck;
  `composer install` installs the git hooks (captainhook, conventional-commit subjects).

## Code conventions (binding)

Every file:

- `declare(strict_types=1);`
- One class, interface, enum or trait per file.
- A class docblock, and a docblock with `@param`/`@return`/`@throws` on every public and
  protected method.
- All properties typed; `readonly` unless mutation is essential.
- All parameters and returns typed, including `void` and `never`.
- No `mixed`, except on a `#[Override]` method whose PSR interface dictates it. No untyped arrays:
  `array<TKey, TValue>` in phpdoc.
- `final` by default; `abstract` only when designed for extension.
- No `static` caches, no globals, no singletons: state lives in the objects the composition root
  wires.
- No `new` in business logic; the generated wiring and the framework kernels compose the app
  (`generated/`, `AIC\Module\Application\AppKernel`, `ConsoleKernel`).
- PSR interfaces at every seam.

## On this box

- Browser checks run on this box, never through the Mac mini's Playwright. Use Marley's Browser
  tab (the `marley` `browser_*` tools) when Chad needs to watch, and a local Playwright MCP
  (headless, the Chromium in `~/.cache/ms-playwright`) when he doesn't. DDEV's default
  `aic-nextgen.ddev.site` resolves locally, so no extra hostname is needed.
- `.mcp.json` is owner-managed (the Playwright MCP server). Never write or overwrite it; it is
  gitignored here.
- Never `git stash -u`: it takes the untracked `.mcp.json` with it.
- `grep` here is ugrep: BRE `\|` alternation silently matches nothing. Use `grep -E` or the Grep
  tool, and never conclude "unused" from a grep alone.
- Never grep or walk the whole filesystem or `$HOME`; scope searches to the project.
- The compiled site is a build: after a PHP change, rerun `composer run native:serve` (or
  gate:23) before a browser check on it, or it still runs the old binary. The php-fpm reference in
  DDEV picks changes up per request.
- gate:23 and `native:serve` find Postgres through `PG*` or `ddev describe` (its published port
  moves on every restart). Every route opens a Postgres session, so when the app's `bin/aic
  db:ping` does not answer (DDEV down; a host Postgres on 5432 refuses the app), gate:23 defers
  as a whole, before building (#110).
- Edit `composer.json` with targeted edits, never a JSON dump and rewrite (it reindents and
  escapes unicode).
- The elephc compiler is `/srv/stacks/elephc` (carry workflow in its `FORK.md`). Run its binary
  in place, one cargo command at a time, never kill a running cargo, and never run `cargo fmt`
  there: it reformats the whole repo.
