# CONSTITUTION — Absolute Insanity, AI (AIAI)

Binding rules for every line of AIC (composer vendor `aic/`, PHP namespaces `AIC\` and `App\`). Any rule can be tightened by amendment; none can be loosened. Ported from old AIC's Constitution (`/srv/stacks/aic` at `e0778896`) and trimmed to one package (#3). The decisions behind these rules are in `docs/planning/plan.md` (the private planning repo).

---

## §0  Quality above all else

Best-practice code is the only acceptable output.

- `composer run gate` must pass green. It is the binding quality bar, and it is purely code: style, static analysis, architecture, security, dependency hygiene and the native gate. It runs in Phase 3 (Quality checks) and loops there until green. No gate runs at push or at delivery: nothing after Quality checks touches code.
- **Source fixes only.** Never a baseline entry, never a suppression annotation, never a lowered threshold or a config carve-out written to make a finding disappear.
- A failing gate is a real defect. If a tool produces a false positive, refactor the code into a shape the tool understands; do not silence the tool.
- A gate that cannot run in full yet *defers* and says why; the summary names every deferral, and a deferral is not coverage.

The gates (`bin/gate-runner.sh` runs every `gate:N` script and reports every failure in one run):

| # | Gate | Setting |
|---|---|---|
| 0 | composer audit | the installed packages against the advisory database; abandoned packages reported |
| 1 | composer-normalize | the root and framework manifests in canonical order |
| 2 | runtime require allow-list | `require` holds only `php`, `ext-*`, `psr/*` and `aic/framework`; `require-dev` is unrestricted |
| 3 | require-checker | every symbol runtime code uses is backed by a declared require |
| 4 | composer-unused | no required package goes unused |
| 5 | no baselines, no suppressions | no baseline files, no `ignoreErrors`, no psalm `suppress`/`info` levels |
| 6 | Pint | `pint.json`: PSR-12, strict types, ordered imports, 100+ rules |
| 7 | Rector | PHP 8.5 sets, dead code, code quality, type coverage, naming (dry run; any change fails) |
| 8 | PHPStan | level 10, bleeding edge, strict, deprecation, shipmonk, ergebnis, disallowed calls, type coverage 100%, cognitive complexity (class ≤ 50, function ≤ 8), dead code (shipmonk/dead-code-detector: methods, constants, enum cases, properties; calls over unknown types do not count); AIC's own rules in `gates/src/PhpStan` (a tagged plugin implements its type) |
| 9 | phpat | the architecture rules in `gates/architecture/`, each registered as a `phpat.test` service |
| 10 | PHPMD | every ruleset; method ≤ 30 lines, class ≤ 300, cyclomatic < 8, NPath ≤ 80, parameters < 12, methods ≤ 15, coupling ≤ 15 |
| 11 | PHPCS | PSR-12 + full slevomat; line length 140; forbidden suppression annotations; namespace allow-list |
| 12 | Deptrac | Contracts → Domain → Application → Infrastructure per system; `--fail-on-uncovered` |
| 13 | Psalm | errorLevel 1, taint analysis, unused code, sealed properties and methods |
| 14 | phpcpd | no copy-paste: 4 lines, 50 tokens, fuzzy |
| 15 | semgrep | the AIC ruleset in `semgrep.yml` (no env outside config, no static state, no singletons, no eval, no Swoole, no shell pipe into an early-exit reader, no `mixed` in phpdoc generics outside PSR overrides, …) |
| 16 | no dynamic require | no runtime-dynamic `require`/`include` in `framework/src` or `app` |
| 17 | gitleaks | no secrets in the history; default rules plus AIC's (Anthropic, OpenAI, Google keys) |
| 18 | shellcheck | every shell script in `bin/` and `.claude/hooks/`, at `-S warning` |
| 19 | documentation | no `(TODO)` or `(WIP)` markers and no dead relative links in markdown |
| 20 | captainhook | the enabled git hooks are installed |
| 21 | native | every runtime class compiles, links, runs and matches PHP under elephc |
| 22 | pipeline docs | the pipeline docs and phase commands: frontmatter, line caps, internal links |
| 23 | native site | the app compiled with `elephc --web` answers the step script as the interpreted app does: status, content type, location, normalised body; database steps defer when Postgres does not answer |

A new gate or rule is done only when it has been seen to fail on a planted violation.

---

## §1  PSR at every seam

Every cross-system seam uses a PSR interface: PSR-4 (autoloading), PSR-7 (HTTP messages), PSR-11 (container), PSR-14 (events, if they survive), PSR-15 (middleware), PSR-17 (HTTP factories). Implementations are ours; **interfaces are PSR**. No bespoke equivalents. (PSR-3 left with logging.)

---

## §2  One package, our own implementation

AIC is one package, `aic/framework`, in `framework/` with a namespace per system (`AIC\Http`, `AIC\Routing`, …), pulled into the demo app at the repo root. We may study any MIT- or BSD-licensed code to learn its patterns; we **never** depend on framework code from outside AIC (HTTP, routing, container, events, middleware, any Laravel or Symfony component). Composer dependencies are allowed for PSR interface packages, for the gate tooling (`require-dev`), and for orthogonal helpers nobody rewrites (`roave/security-advisories`). gate:2 holds the runtime `require` to that.

---

## §3  The runtime and native compilation

AIC runs as the compiled site: the app built with `elephc --web` into one prefork server, each request served with fresh PHP state (#33). The same entry, `bootstrap/serve.php`, runs interpreted under php-fpm as the reference.

- **No static mutable state**, no static caches, no globals, no singletons: state lives in the objects the composition root wires.
- The container, the kernel and the route table are immutable once wired.

Every runtime class (everything under `framework/src` and `app`) compiles natively with elephc, from the first class (gate:21):

- Build-time manifests, not runtime discovery; literal `require` paths only (gate:16); no runtime reflection on dynamic class names; no `eval`.
- Give the compiler its types in code: typed properties, typed returns, small value objects. elephc ignores a plain `array<K, V>` docblock.
- When elephc rejects or miscompiles a class, restructure the AIC code first. Legal PHP that elephc gets wrong is a compiler carry (the `elephc-carry` skill), with its own ticket.

---

## §4  The workflow

Every change goes through four phases: **Plan → Code → Quality checks → Documentation**, then delivery.

| Phase | Command | Writes | Ends when |
|---|---|---|---|
| 1 Plan | `/pipeline:plan` | `docs/planning/*` only | the human confirms the spec: goal, design, file manifest, browser check |
| 2 Code | `/pipeline:implement` | anything | the code is written and, for UI work, checked live in a browser |
| 3 Quality checks | `/pipeline:check` | anything | `composer run gate` is green |
| 4 Documentation | `/pipeline:complete` | docs, `CHANGELOG*`, `CLAUDE.md`, `README.md`, this file | decisions recorded, changelog, AAR, docs gates green |
| delivery | `/commit` | commit, PR, merge, archive, ticket close | the branch is merged and gone |

- **Only the human can waive the pipeline.** `/work-lite` is the light path the human chooses for a trivial change: it skips Plan and Documentation, never Quality checks.
- **Every phase has an entry gate** (`enforce-phase-gate.sh`): the previous phase is PASS in the spec's `status`. **Never two pipeline documents active at once.**
- **Each phase has a write scope** (`enforce-agent-scope.sh`); the boundaries are hard walls. A code change found during Documentation goes back through Quality checks.
- **Recall before action.** Read the ticket, the plan and the docs the change touches before working (`enforce-docs-before-code.sh`, `enforce-completion.sh`).
- **Evidence over claims.** A phase's evidence is in the transcript and the files: the ticket comment, the green gate summary, the AAR, the ticked browser checks.
- **Knowledge is recorded.** Decisions go in `docs/planning/plan.md`; each pipeline ends with an `## AAR` in its notes; durable lessons go to memory.
- **The pipeline doc shape.** A pipeline is a `WORK-<Title>.spec.md` (the bounded contract, body ≤ 80 lines) plus a `.notes.md` (rationale, the phase journal, the AAR) in `docs/planning/pipeline/`, instantiated from `_templates/`. Each `/pipeline:*` command is a spec (body ≤ 100 lines) plus a runbook. gate:22 checks both.
- **Delivery.** A branch per ticket, a commit, a PR; Claude merges its own PR once the work is checked and deletes the branch.

---

## §7  Verification

AIC ships no unit, integration, mutation, coverage or regression tests. Quality is enforced in the code itself:

- The gates (§0), fixed at the source.
- Invariants are static rules, not tests: phpat rules (gate:9), custom PHPStan rules (gate:8), semgrep rules (gate:15), gate checks. "X must never appear in Y" ships as one of these and is proven to bite by planting X once.
- Behaviour a person sees is checked live in a browser during Code (§17).

---

## §14  Code standards (binding)

Every PHP file:

1. `declare(strict_types=1);` right after the opening tag.
2. One class, interface, enum or trait per file; the filename matches the symbol.
3. A class-level docblock describing intent, not implementation.
4. Every public and protected method has `@param` for each parameter, `@return`, and `@throws` for every exception that can escape.
5. Every property typed; `readonly` unless mutation is essential.
6. Every parameter and return typed, including `void` and `never`.
7. No `mixed`. Genericity goes in phpdoc (`array<string, Foo>`, `list<T>`). The one exception is a method marked `#[Override]` whose PSR interface dictates `mixed` (PSR-7's `getAttribute`, `withQueryParams(array $query)`): PHPCS and semgrep both exempt such overrides, and nothing else.
8. `final` by default; `abstract` only when an extension hierarchy is intended.
9. Modern PHP 8.5: `match` over `switch`, enums over class constants, first-class callables, named arguments where they read better.
10. No `new` in business logic: the composition root wires (`App\AppServices`, with the framework's `SapiServices`), in typed code.
11. No `env()` outside `config/`; no `getenv()` in runtime code.
12. No `global`, no `$GLOBALS`, no superglobals outside the SAPI entry point (`bootstrap/serve.php`).
13. No suppression annotations (`@phpstan-ignore`, `@psalm-suppress`, `@phpcs:ignore`, `@phpmd:*`).
14. Every entry point is one of the five kinds: Page, Api, Stream, Command or Job.

---

## §17  Browser verification

When a change renders something a person sees, the spec sets `browser_testable: Yes` and lists Browser Check items (page, action, expected result). During Code the agent opens each page in a browser on this box (Marley's Browser tab when Chad watches, the local Playwright MCP when he does not), performs the action, looks at the result, and ticks the item with what it saw. The check is live and happens once: no Playwright spec files, no regression suite. `enforce-browser-checked.sh` blocks Stop in Code until it happened.

---

## §18  Tooling discipline

### §18.1  Long-running jobs

A tool that runs a minute or more writes its output to a file at a known path, runs with `Bash run_in_background`, and is read once, after it finishes. No sleep-polling, and no re-running to "see more": the file holds everything.

### §18.2  Delegated discovery

In Plan, a lookup across more than three candidate paths, or in a directory not known in advance, goes to an Explore subagent.

---

## Amendment

This Constitution is amended by editing this file through the four phases (`/pipeline:plan` → `/pipeline:implement` → `/pipeline:check` → `/pipeline:complete`) and delivering through `/commit`. Verbal agreement does not amend it.

### Amendment log

- **2026-10-19 — Wiring is code (#21).** §14 rule 10 names the composition root, not a container, as what wires: constructors and readonly properties, typed throughout and compiled natively. The rule against `new` in business logic is unchanged. The PSR-11 container arrives with plugins (#5).
- **2026-10-19 — PSR overrides (#20, Chad's decision).** §14 rule 7 names its one exception: a `#[Override]` method whose PSR interface dictates `mixed`. PHPStan accepts only `array<mixed>` as the contravariant phpdoc of PSR-7's `array` parameters, PHPCS `DisallowMixedTypeHint` already exempted overrides, and semgrep's `aic-no-mixed-in-phpdoc-template` now does the same, bite-checked both ways.
- **2026-10-19 — nextgen (#3).** Ported from old AIC's Constitution at `e0778896` and trimmed to one package: the ward, Swoole and Forge history stays in the archive; the gates are renumbered from gate:0 with no gaps (#2, #14, #3); §3 restated for the FrankenPHP worker and native compilation from the first class (and in #33 for the compiled site); §4 collects the workflow rules old AIC cited as §9, §11, §15 and §16; §18.3 and §18.4 (the retired inspection checkpoint and worktrees) dropped.
