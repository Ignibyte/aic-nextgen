# CLAUDE.md — AIC nextgen

## What this is

The fresh start of **AIC** (Absolute Insanity, AI; AIAI), Ignibyte's AI-first, CMS-slim PHP
framework. Old AIC (`Ignibyte/aic`, checked out beside this repo as `../aic`) grew to 43 wards; on 2026-10-18 Chad
decided to start over with only what earns its place and move it across by copying and
trimming. This repo, `Ignibyte/aic-nextgen` (private), is where AIC lives: there is no
cutover (Chad, 2026-10-20; #11 closed). Old AIC (`Ignibyte/aic`) is the archive and the reference,
pinned at main `e0778896`.

**Read `docs/planning/handoff.md` first**, then `roadmap.md` (the standing rules, the five kinds,
the ten systems, the milestones) and `plan.md` (the decisions and the port map from old wards).
The architecture set an app's agent reads, shipped in `ignibyte/aic-framework`, is
[framework/docs/README.md](framework/docs/README.md); gate:29 holds it to the code.

## Status

**The profiler (Chad, 2026-11-02).** #229 (the core), #230 (the pages) and #231 (the toolbar) are
delivered: with `AIC_PROFILER=1` every dynamic request is kept in `aic_profiles`, named in
`X-AIC-Profile`, readable at `/_profiler`, and every page carries a live toolbar
([framework/docs/profiler.md](framework/docs/profiler.md)). AIC pins Tessera 0.4.3 (#255). #234 holds it natively: gate:23's
`profiled` steps run a second pass with the profiler on and compare each step's recorded profile
on both sites. #232 delivered `bin/aic code:coverage`, what of AIC and Tessera an app uses; #233 an XML Api
answer (`XmlResponse`, the fixture's `/stock.xml`). Next: Lotline's aic-demo #25 to #38; the
compiler carry #235 (`/u` on bad UTF-8) waits.

Current order (Chad, 2026-10-25): finish epic #151, the theme owns all markup. #153 (components,
layouts and assets in `themes/<theme>/`) and #154 (a controller names a layout through
`PageDocuments`, each file falling back to `html.phtml`/`page.phtml`) and #115 (the tick
stream renders a theme component), #156 (gate:32, no HTML under `modules/`) and #157 (`make:layout`,
`make:page --layout`, the `theme:<t>` code-index area) and #159 (the stock page reads only the
rows it shows, through `EntityQuery::orderByDescending()` and `limit()`) are delivered. The elephc fork carry #152 (`?>`, inline HTML, `<?=`) and
#155 (templates run as templates, no markup in any PHP class, Tessera framing in framework
templates) are delivered; #158 (`htmlspecialchars` substitution) stays a parked fork carry. Lotline
(#128, the port of the old site's theming; #132 closed) has its theme: `Ignibyte/aic-demo` (beside this repo as `../aic-demo`) runs
current AIC with `themes/lotline/` (the drawer shell, every part of the old look as theme components on
its `/theme` style guide, Tailwind from `frontend/`); its product pages are the next stage. Old Lotline (`../lotline`) is product and design reference only. #150 (an
intermittent native session-ID defect) is closed: about 42,000 stress requests never reproduced it.

**The theme (#153, Chad 2026-10-25).** "There is no markup in php classes outside of components."
`config/app.php` names the theme; `themes/<theme>/` shows templates on top and keeps the code
behind them in `src/` (#167, Chad 2026-10-26): `layouts/` (`html.phtml`, `page.phtml`, named
variants), `components/<Name>/` (each component's kebab `.phtml` alone, PascalCase folders),
`src/Layouts/` (`Page.php`, whose constructor's Region properties are the regions, and named page
data), `src/Components/<Name>/` (the Component class and its value classes), `assets/` (every
public file; CSS bundles in path order) and, optionally, `frontend/` (a front-end build's sources,
as in a Drupal theme). Modules hold controllers, services,
entities, forms, blocks and migrations, never a component, template or asset; their manifests
declare no presentation. Modules use theme classes; Deptrac's Theme layer keeps the theme off the
modules. No theme registry, no runtime discovery. Since #155 every template is `.phtml` and runs
as a template: the build escapes it into `build/generated/templates/`, a lookup requires it, and no
PHP class holds markup. Everything the build writes is under `build/` (`build/generated/`,
`build/assets/`, the manifests); `public/` holds only `index.php`.

#114 moved the 15 catalog atoms into `framework/templates/components/*.phtml`, compiled by the
same reader and compiler; installed apps consume the shipped classes without package writes.

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
component's declared `.phtml` compiles under `generated/templates/` (`composer run build`, kept fresh by
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
#96/#149: gate:25 accepts only declared `*.phtml` templates, auditing unsupported and unclaimed
authored sources for refusal. The class tools read `*.php` only, so no template reaches them.
#97 to #99 (#87, #88): templates compile from plain PHP, one Renderer makes HTML, and gate:27 counts
`RawHtml` against its grants. #97: `composer run build` compiles each
declared `*.phtml` into its explicit generated class, escaping every `<?= ?>` by its static type; loops are real
loops (`break`, `continue`) and `empty()` lowers by type; the old grammar is gone. A component
is data: its constructor takes the ready values as promoted public props, computed by whoever
builds it, and a class-typed loop variable is named for its class (`$keyedComponent`). #98: one
Renderer makes HTML. `Component` declares no method; `AIC\Component\Application\Renderer` is the
only `new Html`, and `Psr7ResponseWriter` the only reader of `markup()`. A page answers a layout of
regions holding components (`PageDocuments::content(list<Component>, int, ?LayoutName)`, #154), never a string. A
template writes children through a `RenderPass` (`child()` with markers, `region()`, `inline()` in a
layout), and a Tessera fragment is the same tree rendered by its path. The build generates the
lookup (`generated/ThemeTemplates` and its parts, #153), ending at the compiled
framework atom lookup; a module component with no template is refused. #91, the agent workflow,
runs as #100 to #106; #100: the run engine and its state, the dev-only `ignibyte/aic-workflow` package.
#101: the engine runs the gates itself and keeps each verdict in `var/workflow/evidence.sqlite`
beside the subject hash of the tree it judged. #102: the guard hook holds the agent to the open run.
#104: house rules judge each run's change, frozen at begin; the probe sweep is #119.
#106: the code index (`bin/aic code:index|tree|search|refs`); the plan check reading it is #118.
#109 (part 1): the inspect commands, dev-only in `ignibyte/aic-workflow`: `bin/aic route:list`,
`command:list`, `job:list`, `schedule:list`, `module:list`, `module:show <name>` and `kind:list`,
answered from `build/surface.json` (now with each command's, job's and schedule's module, the
modules' bindings, middleware, prefixes and factories, and a `kinds` table), text or `--json`
(`aic.console/1`, `workflow/schema/console.v1.json`), each lookup recorded as `code.queried`;
make:* is #122. #118: plan's grounding check (`GroundingCheck`, after the contract): on an index it
rebuilds in full, every File Manifest row and Entry Points class must be real (rows against the
run's base commit since #172, so a run begun on a tree that holds its work plans unchanged), and the
run's own `code.queried` events at plan must show `code:tree`, a lookup of each modified or deleted
file (its area once the file is gone from the tree) and existing class (`code:refs` counts its subject only), the area of each created file, and a
`code:search` before each new class; `var/index/` is walled. #108: the code wiki, `docs/wiki/`
(`README.md` and `modules/<m>.md`), written by `composer run build` as its last step from the
surface and the code index, held by gate:24, read live with `bin/aic code:wiki [<module>|<Class>]`;
a module's notes go in `modules/<m>/README.md`. After any change under `modules/`, run
`composer run build` and commit `docs/wiki/` with it. #125: the guard judges a write by where it
really lands and refuses, at every lever, one outside the project (symlinked `vendor/ignibyte/aic-*`
included) or under `vendor/`; only `/dev/` is open outside.
#105: `bin/aic workflow:init` installs the workflow in an app (`workflow/pack/`); it refuses here.
#103: the seeker, an `aic-seeker` subagent that reads each run's change against the AIC checklist;
#127 made it one advisory pass at code, on the diff plus one hop. #126 (part A): the root is a
three-module fixture app; a module's handlers build in parts that fit (`HandlerParts`). #116 (part 1):
the gate suite is the dev package `ignibyte/aic-gates`, run by `vendor/bin/aic-gate`. #130: the app side,
profiles, stubs, `aic-gate init`, gate:30 and `config/native-site.json`. #117: modes and holds (agentic or interactive, `workflow:ask` and the
operator's `workflow:answer`, the stall rule, the shell's read forms, the php wall).
#122: `bin/aic make:*` (module, page, api, component, block, command, job, migration, theme,
layout (#157) and entity (#123); dev-only in
`ignibyte/aic-workflow`) write gate-clean classes and add the manifest call, held by the guard while a run
is open and recorded as `code.made`; gate:31 holds `modules/scaffold/` to the twelve make lines.
#119: `bin/aic workflow:probe [--scratch=<dir>] [--keep] [<scenario>…]` replays the engine's planted
cases (`workflow/probes/sweep/`) in throwaway apps made by `workflow/probes/scratch-app.sh`, the
builder the seeker probe shares; it ends `probe-summary: P passed, F failed, N not run.`, exits 0
only with F = N = 0, runs here only and never beside a gate run, and is not a gate. **Run it in
Code of every ticket that changes `workflow/`, the gate runner, the gate list, a gate's verdicts
or a tool config naming `workflow/`, and paste its summary in the notes** (about 25 minutes; while coding, run the
scenarios the change touches by name).

## Five kinds

Every entry point is one of five kinds, each one interface with one method and one sealed
response type: **Page** (HTML document, region fragments or a redirect; form controllers are
Pages), **Api** (JSON or XML; no SOAP), **Stream** (SSE, file downloads), **Command** (CLI, exit code)
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

- One package, **`ignibyte/aic-framework`**, in `framework/`, namespace `AIC\` with one namespace per
  system (`AIC\Http`, `AIC\Routing`, `AIC\Entity`, ...). It reaches the root through a composer
  path repository and moves to its own repo when published.
- The repo root is AIC's fixture app, `aic/app` (#126): four modules under `modules/`, three cut
  from the ten-module demo (the tag `demo-reference`; rebuilt in `Ignibyte/aic-demo` by #128 and
  #129). `site` holds home, greeting and job; `note` holds stock; `account` holds auth and
  session; content and faq were dropped. `scaffold` (#122) is what the make commands make, held
  by gate:31: regenerate it, never edit it by hand. It is AIC's test bed, not a showcase: a feature the
  gates need is added here with its framework change. Each module has its classes
  in `modules/<name>/src/<Kind>/` as `App\<Name>\<Kind>\` (`Controller`, `Block`, `Form`,
  `Entity`, `Repository`, `Service`, `Command`, `Job`, `Migration`). There is no `app/`
  and no hand-written composition class (#93).
- The theme (#153, #167) is `themes/app/` (`config/app.php`'s `theme`): `layouts/` (`html.phtml`,
  `page.phtml`), `components/<Name>/` (its kebab `.phtml`), `src/Layouts/` (`Page.php`),
  `src/Components/<Name>/` (`App\Theme\Components\<Name>\<Name>`) and `assets/`. The build finds
  them; nothing declares them. Templates
  run as templates (#155): the build escapes each into `build/generated/templates/`, and a generated
  lookup method requires it inside the render pass's buffer. Every file in `assets/` is
  published, its role from its extension; stylesheets join `aic.css` in path order.
- Stored files (#213): `AIC\Storage`'s `PublicFiles` (served at `/files/<key>` before any session) and
  `PrivateFiles` (no URL; a module's Stream sends one through `download()`), under
  `<root>/var/storage/`, which a deploy keeps across releases.
- `composer run build` owns `build/assets/` (served at `/assets/` by AIC's asset handler), the typed `PublicAssets` lookup and private
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
- **`ignibyte/aic-workflow`** (#100), in `workflow/`, namespace `AIC\Workflow\`: the agent workflow's
  engine, a second package that is dev-only (`require-dev`, a path repository like the
  framework's; Chad, 2026-10-22). `bin/aic` lists its commands when it is installed:
  `workflow:run <ticket>` (one phase per call, plan → code → check → complete),
  `workflow:status`, `workflow:logs gate[:N]` (#144), `workflow:ask "<question>"` (the agent's, #117),
  and `workflow:reset`, `workflow:answer advance|stay|"<answer>"` and
  `workflow:amend "<row>" "<new row>" "<reason>"` (#267, one frozen spec row) (the operator's, from a
  terminal only). An app opts in
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
    that reaches it) over every file git lists (and an app's `vendor/ignibyte/aic-framework/src`), plus the
    build's surface (routes, commands, jobs, modules), in `var/index/code.sqlite`. A refresh
    re-parses only what changed, and every query refreshes first. `bin/aic code:index [--full]`,
    `code:tree [<module|System|path>]`, `code:search <text>` and `code:refs <name>` need no
    `config/workflow.php`. While a run is open, each lookup appends `code.queried` (the files and
    scopes it showed) to the run's event log, which the guard walls. gate:2 admits
    `nikic/php-parser` for `workflow/composer.json` only. Since #232 (schema version 3) methods
    keep their declared return class and visibility, and calls on typed receivers are `chains`
    edges, read only by `bin/aic code:coverage [--json] [--missing]`: every framework surface and
    the app files that use it (a report, exit 0; the Tessera rows are `framework/docs/tessera.md`'s
    vocabulary table).
  - **The seeker** (#103, one advisory pass since #127): with every other row green, code answers
    `inspection-due` (`CheckStatus::Due`, exit 1, no retry budget spent) until one counted ledger
    is filed; complete runs no seeker. The coding agent dispatches the `aic-seeker` subagent
    (`workflow/pack/claude/agents/aic-seeker.md`); it reads `bin/aic workflow:seeker-brief` (the
    spec, the changed paths and `one_hop`, the files one code-index edge from each), the diff and
    `workflow/pack/seeker/checklist.md`, and answers each item `pass` or `fail <file>:<line>` before
    a ledger the agent pipes to `bin/aic workflow:seeker-report`. The ledger names the subject it
    read and cites a checklist id per finding; it counts only when the guard's `returned` row
    (`SubagentStop`, #169) holds its digest. No finding holds the run at any severity: the row
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
- **`ignibyte/aic-scaffold`** (#279), in `scaffold/`, namespace `AIC\Scaffold\`: a Composer plugin
  (dev-only, here and in an app, #280) that writes, on every install and update, the root
  files installed `ignibyte/aic-*` packages declare in `extra.aic-scaffold` (`replace`, `add-once`,
  `append`), then runs each package's engine (the gates' `aic-gate init`; AIC's root opts it out).
  The framework's files are `framework/scaffold/`: edit `public/index.php`, `bootstrap/serve.php`,
  `bin/aic` and the config starters there, never only at the root. gate:2 admits
  `composer-plugin-api` for its manifest alone; a phpat rule keeps runtime code off it.
- **`ignibyte/aic-project`** (#280), in `project/`: the template `composer create-project` copies
  (an app's `composer.json`, the `site` module, the `app` theme, `config/native-site.json`, DDEV's
  config, a README), split like the other packages. Everything else at an app's root is written on
  install by its owner, so the template never carries a copy. A created app's first gate run is
  `29 passed, 0 deferred`. The template's classes are not AIC's: they sit outside every class root,
  and gate:26 reads `project/` through `ProjectProfile::templateTrees()`, judging each path relative
  to the folder. The packages require each other at `^0.1`.
- No `aic/ward-*` packages, no metapackage, no ward manifests.
- **Modules** (#90, #92, #93): `modules/<name>/<name>.module.php` returns a fluent
  `AIC\Module\Contracts\ModuleManifest`, naming only what the module has:
  `(new ModuleManifest('job'))->requires('site')->route(…)->job(…)->schedule(…)->migration(…)`.
  The other declarations are `binding`, `block`, `plugin`, `export`, `command`, `middleware` (with
  a `MiddlewarePosition`), `protectedPrefix` and `factory`.
  - `#[Tagged(Type::class)]` on a constructor's list parameter takes the plugins of that type.
  - A factory is a readonly module class whose methods return services built from values; a
    constructor asking for a method's return type gets its product.
  - `composer run build` checks every manifest and writes the wiring into `build/generated/`
    (`App\Generated\`). Per module, `<M>Routes`, `<M>Handlers` (in parts; a split module's shared
    services in `<M>HandlerServices`, #257), `<M>Middleware`, `<M>Blocks` (blocks
    wired with their services, #203), `<M>Commands`, `<M>Jobs`, `<M>Provider` (plugins),
    `<M>Migrations`, `<M>Schedules` and `<M>Prefixes`. Then the
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
**Code** (no tests but browser tests; front-end work is looked at with Playwright, see On this box), **Quality
checks** (`composer run gate` until green, the only gate run), **Documentation** (docs, plan
updates, changelog, then delivery). AIC writes **no unit, mutation, coverage or regression
tests** for now (Chad may bring them back once the framework is done), except browser tests
(#174): a Playwright look the pipeline keeps in `tests/browser/`, which gate:23 runs on the
compiled site. An invariant that must hold ships as a static guard (PHPStan, phpat or semgrep
rule) proven to bite by planting a violation.

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

- Copy from `../aic` at `e0778896` and trim. Never require an old `aic/ward-*` package.
- Cut every edge to a removed system on the way in (the list is in `plan.md`). A class that only
  served a removed system stays behind.
- Keep native-compilation discipline from the first commit: build-time manifests, no
  runtime-dynamic `require`/`include`, the build-time reflection registry, no static
  cross-request state.

## Quality gates (binding)

`composer run gate` runs every gate on the host (no DDEV), about 15 seconds, and ends with one
`gate-summary:` line, after one `gate-result: gate:N passed|deferred|failed` line per gate, which
the workflow engine reads (#101). AIC's own tickets still run the suite in Phase 3. **No baselines. No suppressions. Source fixes only.** The runner
(ignibyte/aic-gates' `gate-runner.sh`, #116) runs every gate of the suite (`vendor/bin/aic-gate --numbers`,
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
| | | 23 | native site: the compiled app answers as PHP does; steps reach every GET route; HTML answers pass the lint; kept browser tests pass |
| | | 24 | build manifests: `build/surface.json` is fresh |
| | | 25 | templates: the plain PHP grammar, deny by default; the HTML lint |
| | | 26 | module boundaries: kind folders, the 15-file cap, exports and requires, the shell |
| | | 27 | raw html: every RawHtml use listed and held to its grant |
| | | 28 | house rules: every shipped rule bites on its probe |
| | | 29 | architecture docs: `framework/docs/` states what the code has |
| | | 30 | suite files: the root configs are ignibyte/aic-gates' stubs, composer.json's suite lines as shipped |
| | | 31 | make scaffold: `modules/scaffold/` is what the make lines make |
| | | 32 | module markup: no HTML anywhere under `modules/` |

- The gate code is the dev-only package `ignibyte/aic-gates` in `gates/` (#116; namespace `Gates\`,
  outside `AIC\` and `App\`), required by path like `ignibyte/aic-workflow`; a phpat rule keeps runtime
  code from depending on it. It holds the code (`src/`, `architecture/`), the entries, the build,
  the runner and the tool installer (`bin/`, every entry rooted at the working directory), the
  base configs (`config/`) and the elephc pins (`native/`). AIC's root `phpstan.neon`,
  `phpstan-architecture.neon`, `psalm.xml`, `deptrac.yaml`, `phpcs.xml.dist` and `rector.php`
  include their bases and name AIC's paths; `pint.json` and `captainhook.json` stay whole at the
  root (CaptainHook resolves paths against its config's directory).
- **Profiles** (#130): `ProjectProfile` decides `Aic` when `gates/composer.json` names
  `ignibyte/aic-gates`, `App` otherwise, and gives `GateRoots` (class, source, runtime and site roots, the
  manifests, the script folders, gate:26's shell). An app runs every gate but 22, 28, 29 and 31;
  gate:21 probes its `modules/`, `themes/` and `build/generated/`, and gate:23 compiles them with the framework from
  `vendor/`. gate:23's steps are `config/native-site.json` (`aic.native-site/1`, one step a
  line; with none the gate defers; `profiled` steps run again with `AIC_PROFILER=1`, #234).
- **Tool pins** (#280): `gates/composer.json` pins every tool that reads or rewrites code (21
  packages) to the exact version AIC's root runs, so an app judges with the versions the verdicts
  were proven on. `install-tools.sh` pins the non-PHP ones the same way (#282). A tool bump is an AIC change: update the root, run the gate, move the pin.
- **An app's suite files** (#130): `gates/stubs/` holds the eight root files an app keeps.
  `vendor/bin/aic-gate init` writes each that is missing or differs (`phpcs.xml.dist` with one
  root per module), appends the ignore lines, prints composer.json's lines and never writes it;
  it refuses here. gate:30 (`SuiteFilesGate`) holds the stubs and composer.json's suite lines; in
  AIC it holds `pint.json`, `captainhook.json` and the `gate:N` aliases. In both profiles it also
  compares `config/phpcs-custom.xml` and `config/phpmd-custom.xml` inside the installed
  `ignibyte/aic-gates` package with their writers. The AIC source build writes these two files; app builds leave the package alone.
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
- gate:21 probes each class under `framework/src`, `modules`, `themes` and `build/generated` on its own: its class-graph closure,
  compiled with `ELEPHC_BIN`, then `tools/elephc`, then the first `elephc` on `PATH` (#222, #281;
  here link the fork's in-place `target/release/elephc` on `PATH` rather than copying it; native packages cache in
  `ELEPHC_NATIVE_CACHE`, by default `~/.cache/elephc-native`),
  run, and compared with host PHP. Work files land in `.native-gate/`
  (gitignored); pcre2 and oniguruma are pinned in ignibyte/aic-gates' `native/elephc.{toml,lock}`. Everything
  outside those roots runs interpreted by design.
- gate:25 (`gates/bin/gate-templates.php`, #96/#149/#153) audits template candidates under
  modules/, themes/ and the unsupported old templates/ root, and parses the theme's `.phtml`
  files and the atoms with php-parser (`Gates\Templates\`), deny by default.
  - **Allowed:** inline HTML; `<?= one expression ?>`; `if`, `switch`, `for` and `foreach` in
    the alternative syntax; `match`; `break` and `continue`; the component variable (from the
    first line's `<?php /** @var \Fqcn\Name $name */ ?>`) and loop variables; property and
    array reads, `?->`; literals, comparisons, `&& || !`, `?:`, `??`, `isset()`, `empty()`,
    `.` and arithmetic; enum cases and class constants (`Status::Sold`).
  - **Refused:** every function (`TemplateGrammar::functions()` is empty), method and static
    call, `::class`, `new`, closures, assignment outside a `for` header, `while` and
    `do`/`while`, `<?php echo`, declarations, superglobals, and anything else.
  - **Placement:** `themes/<theme>/components/<Name>/<name>.phtml` or
    `themes/<theme>/layouts/`; a template anywhere else is refused.
  - **The HTML lint (#170):** `TemplateHtml` reads the markup with each echo as a NUL marker and
    refuses (`html`) inline style (`style`, `<style>`), inline script (`on*`, `javascript:`, a
    `<script>` with no `src` or a body), an echoed tag or attribute name, values not in double
    quotes, spacing other than one space between attributes; `TemplateWhitespace` (`whitespace`)
    refuses tabs, CR, trailing whitespace, indents off a step of 4, two blank lines and a blank last
    line. gate:23's `NativeSiteHtml` lints the PHP site's HTML answers as sent: the inline rules,
    duplicate ids, `<img>` without `alt`, and `Dom\HTMLDocument` parse errors on a full document.
    Write JSON in an attribute as `data-x="{&quot;a&quot;:1}"`.
  - **The class tools skip templates,** by Chad's decision, and so do the Stop hooks. A tool
    handed a template by explicit path ignores its excludes (Pint and phpcbf then rewrite it),
    so never pass a template to a fixer.
- gate:26 (`gates/bin/gate-modules.php`, #94) reads the manifests and `git ls-files -co
  --exclude-standard`. A class's kind (`Gates\Domain\ClassKinds`, shared with the surface
  manifest) decides its folder: Page, Api and Stream in `src/Controller/`, then `Command/`,
  `Job/`, `Block/`, `Notification/`, `Middleware/`, `Migration/`, `Repository/` and `Form/` by
  name suffix. A plain class goes in `Entity/`, `Service/` or `Form/`. A Component in a module, or
  a module `templates/` or `assets/` folder, is refused with the theme path to move it to (#153).
  Its message names the folder, the `->export(...)` or the `->requires(...)` to add; the shell
  list is a constant in `ModuleShellRule`, which also admits the theme's classes.
- Components only (#99), as gate:8 rules (`gates/src/PhpStan`) and a gate:9 phpat test:
  - `aic.htmlConstruction`: only `Renderer` makes an `Html`.
  - `aic.htmlMarkupRead`: only `Psr7ResponseWriter` reads one, a first-class callable included.
  - In a module's `src/Controller/`: `aic.controllerReturnType` refuses a method returning
    anything but its kind's response, so helpers go to `src/Service/`.
  - `aic.renderTreeInput` and `RenderingArchitectureTest`: `Region`, `Regions` and every
    `Layout` take components, blocks, regions, enums and scalars, never `Html`.
- gate:27 (`gates/bin/gate-raw-html.php`, `Gates\Rendering`, #99) lists every `new RawHtml` and holds
  each class to its grant in `config/raw-html.php` (`RawHtmlGrant`: class, exact uses, reason);
  only a theme component class may hold a grant (#156). `RawHtml::` may appear only in `Renderer` and `RenderPass`. `GATE_RAW_HTML_ROOT`
  takes a plant root.
- gate:32 (`gates/bin/gate-module-markup.php`, `Gates\Markup`, #156) holds `modules/` to no HTML:
  template and markup file types by name, PHP literals folded with php-parser (concat and
  interpolation, holes for values) against an HTML element list, custom element names, markup
  declarations, computed brackets and character references, inline output, HTML functions, raw
  text files, links and unparsable files; any `<` in `src/Controller/`. A usage placeholder named
  like an element is refused: write `{table}`. Since #233 XML as text too: an `<?` declaration, a
  closing `</name>` or a self-closing `<name/>` of any name (a lone `<login>` placeholder stays);
  XML is built as `AIC\Controller\Domain\XmlDocument`. `GATE_MODULE_MARKUP_ROOT` takes a plant root.
- gate:28 (`gates/bin/gate-house-rules.php`, #104) runs every `workflow/rules/*.php` rule on its
  `.probe/` cases in a temporary directory: each `held-*` must exit 0, each `broken-*` 1.
- gate:21 caches (#110): a class whose closure files, compiler, bridge libraries, PHP, pinned
  packages and probe program are unchanged since its last green probe reports `CACHED`
  (`.native-gate/cache.txt`); a change reprobes only the classes whose closure holds it. Probes
  run 8 at a time under `nice -n 10`; `AIC_NATIVE_JOBS` sets the cap. A cold run takes under a
  minute, an unchanged tree under a second.
- `vendor/bin/aic-gate tools` installs gitleaks, semgrep and shellcheck, each pinned and in or linked
  from `tools/` (#282: semgrep 1.156.0 in a per-host venv under `~/.cache/aic-tools/`, shellcheck
  0.11.0 from its release binary; gates 15, 17 and 18 look in `tools/` first, and a missing tool
  fails its gate), Playwright, and in an app the compiler (#281): the fork release `gates/native/elephc-release` pins by
  SHA-256, unpacked once per host under `~/.cache/aic-elephc/<tag>/<target>/` and linked as
  `tools/elephc` (`AIC_ELEPHC_MIRROR` overrides the download base). AIC's profile installs none:
  AIC runs the fork's `main` from source. Linux x86_64 only; elsewhere the fork builds from source;
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
  (`build/generated/`, `AIC\Module\Application\AppKernel`, `ConsoleKernel`).
- PSR interfaces at every seam.

## On this box

- Browser looks run on this box with Playwright 1.62.1 (`npm ci` from the root `package.json`;
  Chromium in `~/.cache/ms-playwright`): explore with `npx playwright cli`, write the look in
  `tests/browser/`, run it with `vendor/bin/aic-gate browser --base-url=https://aic-nextgen.ddev.site:8843`
  (#174). Marley's Browser tab (the `marley` `browser_*` tools) when Chad needs to watch.
  `aic-nextgen.ddev.site` resolves locally.
- `.mcp.json` is owner-managed (the Playwright MCP server). Never write or overwrite it; it is
  gitignored here.
- Never `git stash -u`: it takes the untracked `.mcp.json` with it.
- `grep` here is ugrep: BRE `\|` alternation silently matches nothing. Use `grep -E` or the Grep
  tool, and never conclude "unused" from a grep alone.
- Never grep or walk the whole filesystem or `$HOME`; scope searches to the project.
- The compiled site is a build: after a PHP change, rerun `composer run native:serve` (or
  gate:23) before a browser check on it, or it still runs the old binary. The php-fpm reference in
  DDEV picks changes up per request.
- gate:23 fails on a GET route of `build/surface.json` no step reaches (#171): a page a ticket adds
  needs a step in `config/native-site.json` that expects a status below 400.
- gate:23 and `native:serve` find Postgres through `PG*` or `ddev describe` (its published port
  moves on every restart). Every route opens a Postgres session, so when the app's `bin/aic
  db:ping` does not answer (DDEV down; a host Postgres on 5432 refuses the app), gate:23 defers
  as a whole, before building (#110).
- Edit `composer.json` with targeted edits, never a JSON dump and rewrite (it reindents and
  escapes unicode).
- The elephc compiler is AIC's fork, checked out beside this repo as `../elephc` (carry workflow in its `FORK.md`).
  Its releases (`aic-v<version>-<n>` tags, `.github/workflows/aic-release.yml`) are what apps install;
  the first is `aic-v0.27.7-1` (#282). The fork runs that workflow alone: upstream's are disabled
  there. Tag only a commit AIC's gates passed on, then move the pin. Run its binary
  in place, one cargo command at a time, never kill a running cargo, and never run `cargo fmt`
  there: it reformats the whole repo.
