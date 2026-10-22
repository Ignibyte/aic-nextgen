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

## Status

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
- gate:23 builds the whole app natively, serves it beside PHP, and compares 16 steps.
- `composer run native:serve` serves the compiled site on 127.0.0.1:8090.

#26: `build/surface.json` lists every route and command with its kind, written by `composer run
build`; gate:24 keeps it fresh. #25: byte ranges (206/416) on downloads and Server-Sent Events
(`EventStream` pulling from an `EventSource`, streamed natively). #7 (M3) runs as #35 to #39;
#35: `AIC\Component` (`Component::render(): Html`, the catalog atoms with a caller). #36: a
component's `X.tpl` compiles to `XTemplate.php` beside it (`composer run build`, kept fresh by
gate:24); the generated class passes every gate unexcluded. #37: a Page returns
`PageDocument(Layout)`; regions render as `data-region` elements, components and blocks between
`<!--aic:...-->` markers. #38: blocks (`AIC\Block`) place themselves in a region by weight and
route prefix, as tagged plugins. #39: the theme builds into `public/assets/aic.css` (gate:24; `assets/` since #95);
nginx serves it in DDEV, a proxy in production, `/assets/{file}` on the compiled site. #7 and M3
are done. #8 runs as #41 to #44; #41: `AIC\Session` (a Postgres session behind
`__Host-aic_session`, on the request as `aic.session`). #42: `FormGuardMiddleware` checks the CSRF
token and the honeypot on every unsafe request; the `Form` atom takes a `FormToken`. #43: the app secret comes only from `AIC_APP_SECRET`
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
#96: gate:25 holds every `*.html.php` template to the plain PHP grammar (deny by default); the
class tools skip `modules/*/templates/` and `templates/`.

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
- The repo root is the demo app, `aic/app`: ten modules under `modules/` (`site`, `home`,
  `greeting`, `note`, `session`, `auth`, `content`, `job`, `stock`, `faq`), each with its classes
  in `modules/<name>/src/<Kind>/` as `App\<Name>\<Kind>\` (`Controller`, `Component`, `Block`,
  `Form`, `Entity`, `Repository`, `Service`, `Command`, `Job`, `Migration`). There is no `app/`
  and no hand-written composition class (#93). Page layouts compile from `templates/layouts/`.
- One site theme, CSS only, at the top-level `assets/` (#95). `composer run build` bundles each
  module's `modules/<name>/assets/*.css` (in requires order), then the theme last, into
  `public/assets/aic.css`. It copies a module's other assets (scripts, images, fonts, flat until #55)
  to `public/assets/<module>/`. Nothing links a module script; a component that needs one declares
  it from #55 (Chad, 2026-10-21). The build owns `public/assets/`: gate:24 calls an orphan there
  stale.
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
`gate-summary:` line. **No baselines. No suppressions. Source fixes only.** The runner
(`bin/gate-runner.sh`) runs each `gate:N` script in `composer.json` and keeps going after a
failure, so one run names every failing gate. A gate that cannot run in full yet *defers* and
says why; the summary names each deferral, and a deferral is not coverage.

The settings of each gate are in `CONSTITUTION.md` §0.

| # | Gate | # | Gate |
|---|---|---|---|
| 0 | composer audit (installed packages) | 11 | PHPCS, PSR-12 + slevomat |
| 1 | composer-normalize | 12 | Deptrac, the layer ladder per system |
| 2 | runtime require allow-list | 13 | Psalm level 1, taint analysis |
| 3 | require-checker | 14 | phpcpd (copy-paste) |
| 4 | composer-unused | 15 | semgrep, `semgrep.yml` |
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

- The gate code is dev-only tooling in `gates/` (namespace `Gates\`), outside `AIC\` and
  `App\`; a phpat rule keeps runtime code from depending on it.
- phpat runs only rule classes registered with the `phpat.test` tag in
  `phpstan-architecture.neon`; a rule class that is not listed never runs. AIC's own PHPStan rules
  live in `gates/src/PhpStan` and are listed under `rules:` in `phpstan.neon`.
- PHPMD holds old AIC's Constitution limits (method <= 30 lines, class <= 300, <= 15 methods,
  coupling <= 15). Raise one only when code needs it, with the reason beside the value.
- A new gate or rule is done only when it has been seen to fail on a planted violation.
- Before a final run: `rm -f .phpcs-cache` (a stale cache passes PHPCS falsely).
- gate:21 probes each class under `framework/src`, `modules` and `generated` on its own: its class-graph closure,
  compiled with the fork's elephc in place (`/srv/stacks/elephc/target/release/elephc`;
  `ELEPHC_BIN` overrides), run, and compared with host PHP. Work files land in `.native-gate/`
  (gitignored); pcre2 and oniguruma are pinned in `gates/native/elephc.{toml,lock}`. Everything
  outside those roots runs interpreted by design.
- gate:25 (`bin/gate-templates.php`, #96) parses each `*.html.php` under `modules/` and
  `templates/` with php-parser (`Gates\Templates\`), deny by default.
  - **Allowed:** inline HTML; `<?= one expression ?>`; `if`, `switch`, `for` and `foreach` in
    the alternative syntax; `match`; `break` and `continue`; the component variable (from the
    first line's `<?php /** @var \Fqcn\Name $name */ ?>`) and loop variables; property and
    array reads, `?->`; literals, comparisons, `&& || !`, `?:`, `??`, `isset()`, `empty()`,
    `.` and arithmetic; enum cases and class constants (`Status::Sold`).
  - **Refused:** every function (`TemplateGrammar::functions()` is empty), method and static
    call, `::class`, `new`, closures, assignment outside a `for` header, `while` and
    `do`/`while`, `<?php echo`, declarations, superglobals, and anything else.
  - **Placement:** `modules/<m>/templates/components/` or `templates/layouts/`.
  - **The class tools skip templates,** by Chad's decision, and so do the Stop hooks. A tool
    handed a template by explicit path ignores its excludes (Pint and phpcbf then rewrite it),
    so never pass a template to a fixer.
- gate:26 (`bin/gate-modules.php`, #94) reads the manifests and `git ls-files -co
  --exclude-standard`. A class's kind (`Gates\Domain\ClassKinds`, shared with the surface
  manifest) decides its folder: Page, Api and Stream in `src/Controller/`, then `Command/`,
  `Job/`, `Block/`, `Component/` (with the generated templates), `Notification/`, `Middleware/`,
  `Migration/`, `Repository/` and `Form/` by name suffix. A plain class goes in `Entity/`,
  `Service/` or `Form/`. Its message names the folder, the `->export(...)` or the
  `->requires(...)` to add; the shell list is a constant in `ModuleShellRule`.
- gate:21 caches (#110): a class whose closure files, compiler, bridge libraries, PHP, pinned
  packages and probe program are unchanged since its last green probe reports `CACHED`
  (`.native-gate/cache.txt`); a change reprobes only the classes whose closure holds it. Probes
  run 8 at a time under `nice -n 10`; `AIC_NATIVE_JOBS` sets the cap. A cold run takes under a
  minute, an unchanged tree under a second.
- `bin/install-tools.sh` installs gitleaks (pinned, into `tools/`), semgrep and shellcheck;
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
