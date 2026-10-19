# CLAUDE.md — AIC nextgen

## What this is

The fresh start of **AIC** (Absolute Insanity, AI; AIAI), Ignibyte's AI-first, CMS-slim PHP
framework. Old AIC (`/srv/stacks/aic`, `Ignibyte/aic`) grew to 43 wards; on 2026-10-18 Chad
decided to start over with only what earns its place and move it across by copying and
trimming. This repo, `Ignibyte/aic-nextgen` (private), replaces `Ignibyte/aic` at cutover
(ticket #11). Old AIC is the archive and the reference, pinned at main `e0778896`.

**Read `docs/planning/handoff.md` first**, then `roadmap.md` (the standing rules, the five kinds,
the ten systems, the milestones) and `plan.md` (the decisions and the port map from old wards).

## Status

The workspace is up (#1): composer with the `framework/` path package, DDEV with FrankenPHP and
Postgres 17, and `public/worker.php` serving a placeholder. The gate suite is in (#2):
`composer run gate`, 23 gates, the native gate (gate:21, #14) among them. The first runtime class
is `AIC\Routing\Domain\Method`. The four-phase workflow is in (#3): `CONSTITUTION.md`, the
commands in `.claude/commands/`, the hooks in `.claude/hooks/`. Milestone M0 is done; next is #4.

## Five kinds

Every entry point is one of five kinds, each one interface with one method and one sealed
response type: **Page** (HTML document, region fragments or a redirect; form controllers are
Pages), **Api** (JSON, XML, SOAP), **Stream** (SSE, file downloads), **Command** (CLI, exit code)
and **Job** (queued or scheduled work: done, retry, fail). A static guard makes every entry-point
class implement exactly one, and the build writes a manifest of every entry point with its kind.
"Worker" means the FrankenPHP HTTP worker; background work is a job.

## Stack

- PHP **8.5**, strict types everywhere.
- **FrankenPHP worker mode** is the only runtime: `public/worker.php` loops
  `frankenphp_handle_request()`. One request per worker at a time; per-request state lives behind
  `RequestScopeInterface` and is reset between requests. No Swoole, no FPM, no coroutines.
- Postgres 17 through DDEV. FrankenPHP is DDEV's web server through the `ddev/ddev-frankenphp`
  add-on (generic webserver, PHP 8.5 ZTS). Keep the add-on's own files unedited; project settings
  go in `.ddev/docker-compose.frankenphp_extra.yaml` (worker mode) and
  `.ddev/config.zz-router-ports.yaml` (the box's router ports, 8880/8843).
- PSR at every seam (PSR-3 is gone with logging; PSR-4, 7, 11, 14 if events survive, 15, 17).

## Layout (working decision, ticket #1)

- One package, **`aic/framework`**, in `framework/`, namespace `AIC\` with one namespace per
  system (`AIC\Http`, `AIC\Routing`, `AIC\Entity`, ...). It reaches the root through a composer
  path repository and moves to its own repo when published.
- The repo root is the demo app, `aic/app`, namespace `App\`, under `app/`.
- One project theme at `theme/` renders into `public/`.
- No `aic/ward-*` packages, no metapackage, no ward manifests.

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

- The gate code is dev-only tooling in `gates/` (namespace `Gates\`), outside `AIC\` and
  `App\`; a phpat rule keeps runtime code from depending on it.
- phpat runs only rule classes registered with the `phpat.test` tag in
  `phpstan-architecture.neon`; a rule class that is not listed never runs.
- PHPMD holds old AIC's Constitution limits (method <= 30 lines, class <= 300, <= 15 methods,
  coupling <= 15). Raise one only when code needs it, with the reason beside the value.
- A new gate or rule is done only when it has been seen to fail on a planted violation.
- Before a final run: `rm -f .phpcs-cache` (a stale cache passes PHPCS falsely).
- gate:21 probes each class under `framework/src` and `app` on its own: its class-graph closure,
  compiled with the fork's elephc in place (`/srv/stacks/elephc/target/release/elephc`;
  `ELEPHC_BIN` overrides), run, and compared with host PHP. Work files land in `.native-gate/`
  (gitignored); pcre2 and oniguruma are pinned in `gates/native/elephc.{toml,lock}`. Everything
  outside those two roots runs interpreted by design.
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
- No `mixed`. No untyped arrays: `array<TKey, TValue>` in phpdoc.
- `final` by default; `abstract` only when designed for extension.
- No `static` caches, no globals, no singletons: a worker serves request after request, and
  static state leaks between them.
- No `new` in business logic; the container wires.
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
- Edit `composer.json` with targeted edits, never a JSON dump and rewrite (it reindents and
  escapes unicode).
- The elephc compiler is `/srv/stacks/elephc` (carry workflow in its `FORK.md`). Run its binary
  in place, one cargo command at a time, never kill a running cargo, and never run `cargo fmt`
  there: it reformats the whole repo.
