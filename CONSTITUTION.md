# CONSTITUTION — Absolute Insanity, AI (AIAI)

Binding rules for every line of AIC (composer vendor `aic/`, PHP namespaces `AIC\` and `App\`). Any rule can be tightened by amendment; none can be loosened. Ported from old AIC's Constitution (`/srv/stacks/aic` at `e0778896`) and trimmed to one package (#3). The decisions behind these rules are in `docs/planning/plan.md` (the private planning repo).

---

## §0  Quality above all else

Best-practice code is the only acceptable output.

- `composer run gate` must pass green. It is the binding quality bar, and it is purely code: style, static analysis, architecture, security, dependency hygiene and the native gate. It runs in Phase 3 (Quality checks) and loops there until green. No gate runs at push or at delivery: nothing after Quality checks touches code.
- **Source fixes only.** Never a baseline entry, never a suppression annotation, never a lowered threshold or a config carve-out written to make a finding disappear.
- A failing gate is a real defect. If a tool produces a false positive, refactor the code into a shape the tool understands; do not silence the tool.
- A gate that cannot run in full yet *defers* and says why; the summary names every deferral, and a deferral is not coverage.

The gates (aic/gates' runner, behind `composer run gate`, runs every gate of `Gates\Application\GateSuite` and reports every failure in one run; each `gate:N` script is the alias `aic-gate N`, #116):

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
| 13 | Psalm | two full-project passes: ordinary errorLevel 1 then taint; unused code, sealed properties and methods |
| 14 | phpcpd | no copy-paste: 4 lines, 50 tokens, fuzzy |
| 15 | semgrep | the AIC ruleset in `gates/config/semgrep.yml` (no env outside config, no static state, no singletons, no eval, no Swoole, no shell pipe into an early-exit reader, no `mixed` in phpdoc generics outside PSR overrides, …) |
| 16 | no dynamic require | no runtime-dynamic `require`/`include` in `framework/src`, `modules` or `generated` |
| 17 | gitleaks | no secrets in the history; default rules plus AIC's (Anthropic, OpenAI, Google keys) |
| 18 | shellcheck | every shell script in `bin/` and `.claude/hooks/`, at `-S warning` |
| 19 | documentation | no `(TODO)` or `(WIP)` markers and no dead relative links in markdown |
| 20 | captainhook | the enabled git hooks are installed |
| 21 | native | every runtime class compiles, links, runs and matches PHP under elephc; a class whose closure and toolchain hash as at its last green probe is reported cached, the rest probed in parallel |
| 22 | pipeline docs | the pipeline docs and phase commands: frontmatter, line caps, internal links |
| 23 | native site | the app compiled with `elephc --web` answers the step script as the interpreted app does: status, content type, location, normalised body; the whole gate defers when the app's database does not answer (`bin/aic db:ping`) |
| 24 | build manifests | `build/surface.json` (every route and command with its kind, #26), the generated wiring, the app and fixed framework atom classes compiled from explicitly declared `*.tpl.php` sources (#97, #147, #149, #114) and `public/assets/` (the bundled `aic.css` and each module's copied assets, #95) match what `composer run build` writes; a file the build would not write under `generated/`, the source-owned framework atom output folder or `public/assets/` is stale; an installed App never writes framework package outputs |
| 25 | templates | only explicitly declared app inputs and fixed framework atom catalog `*.tpl.php` inputs are accepted; unsupported and unclaimed authored sources are refused; each accepted template is plain PHP read only (#96, #147): `<?=` and the alternative control syntax over the component and loop variables, no function, method or static call, no `new`, no assignment but a `for` counter, deny by default; the class tools skip authored template sources and include all compiled app and framework classes (Chad, 2026-10-21); `composer run build` compiles each into its class and refuses, with file and line, a value whose static type it cannot write or a null test on a value that cannot be null (#97); contextual control flow emits markers only at safe body boundaries, with compatible branch joins and neutral loops/exits (#114) |
| 26 | module boundaries | inside a module a class's kind decides its `src/` folder; at most 15 files per folder (`src/Migration/` exempt); a module uses only classes another exports, from modules it requires; the requires name modules and form no cycle; nothing outside `modules/` but the shell (#94) |
| 27 | raw html | every `new RawHtml`, the one way round the Renderer's escaping, is listed with its place and reason and held to its class's grant in `config/raw-html.php` (an exact count, a reason, theme component classes only, #156); `RawHtml::` is named only in the Renderer. gate:8's `aic.htmlConstruction`, `aic.htmlMarkupRead`, `aic.controllerReturnType` and `aic.renderTreeInput`, and gate:9's `RenderingArchitectureTest`, hold the rest: only the Renderer makes an `Html`, only the writer reads one, a controller returns its kind's response, and the render tree takes no markup (#99) |
| 28 | house rules | every house rule the workflow ships (`workflow/rules/*.php`) bites on its own probe: each `held-*` case exits 0 and each `broken-*` case exits 1 under `gates/bin/gate-house-rules.php`, and a rule with no held or no broken case fails (#104) |
| 29 | architecture docs | the pages in `framework/docs/`, which ship in aic/framework, state what the code has: every gate composer.json runs (and this table's rows), every entry kind and module folder, every namespace under `framework/src/` in exactly one system, every manifest declaration, the required public method/constant/enum/command inventories, every `AIC\…` name, `gate:N` and `composer run` script they cite, and every anchor they link; their prose holds no-ai-slop's banned words out and at most two em dashes a page (#107) |
| 30 | suite files | an app's root configs (`phpstan.neon`, `phpstan-architecture.neon`, `psalm.xml`, `deptrac.yaml`, `phpcs.xml.dist`, `rector.php`, `pint.json`, `captainhook.json`) are aic/gates' stubs byte for byte, `phpcs.xml.dist` with one `App\<M>` root per module on disk; composer.json's `build`, `gate`, `native:serve` and one `gate:N` alias per gate the project runs are as shipped, no other script is named `gate…`, and the tools' four plugins are allowed. In either profile the generated custom PHPCS/PHPMD registrations match their writers; only AIC's source build writes them. In AIC's own repository it holds `pint.json`, `captainhook.json` and composer.json's lines (#130) |
| 31 | make scaffold | `modules/scaffold/`, the fixture's fourth module, with its theme components and layout, is what the eleven `bin/aic make:*` lines in `ScaffoldSequence` make, byte for byte (compiled templates live under `generated/templates/`): `gates/bin/gate-make-scaffold.php` runs the real commands over an in-memory overlay that hides the module, and compares both ways; `make:module`'s two printed lines are the ones the build asks for. A writer's template changed, a hand edit, an extra file or a changed PSR-4 string fails it, and its message prints the regeneration (#122). AIC only |
| 32 | module markup | no file under `modules/` holds HTML (#156): a template or markup file (`.tpl.php`, `.html`, `.svg`, `.twig` and the rest) fails by name; PHP is parsed and every literal form folded (concat chains and interpolation, values as holes) and refused when it holds an HTML element or custom element tag, a markup declaration, a bracket left for a computed tag or a character reference; inline output and HTML functions fail; other text files are read raw; links and unparsable files fail closed; in `src/Controller/` any `<` fails. `gates/bin/gate-module-markup.php`; `GATE_MODULE_MARKUP_ROOT` takes a plant root |

A new gate or rule is done only when it has been seen to fail on a planted violation.

---

## §1  PSR at every seam

Every cross-system seam uses a PSR interface: PSR-4 (autoloading), PSR-7 (HTTP messages), PSR-11 (container), PSR-14 (events, if they survive), PSR-15 (middleware), PSR-17 (HTTP factories). Implementations are ours; **interfaces are PSR**. No bespoke equivalents. (PSR-3 left with logging.)

---

## §2  One package, our own implementation

AIC is one runtime package, `aic/framework`, in `framework/` with a namespace per system (`AIC\Http`, `AIC\Routing`, …), pulled into the fixture app at the repo root (three modules since #126; the demo is the `demo-reference` tag). A second package, `aic/workflow` in `workflow/` (namespace `AIC\Workflow\`), is dev-only: the agent workflow's engine, required under `require-dev`, never by runtime code (two phpat rules hold both directions), and run interpreted rather than compiled. We may study any MIT- or BSD-licensed code to learn its patterns; we **never** depend on framework code from outside AIC (HTTP, routing, container, events, middleware, any Laravel or Symfony component). Composer dependencies are allowed for PSR interface packages, for the gate tooling (`require-dev`), and for orthogonal helpers nobody rewrites (`roave/security-advisories`). gate:2 holds the runtime `require` to that.

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
10. No `new` in business logic: the generated wiring (`generated/`, written by the build from the module manifests) and the framework kernels (`AppKernel`, `ConsoleKernel`, with `SapiServices`) compose the app, in typed code.
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

- **2026-10-25 — compiled catalog atoms and contextual markers (#114).** §0 rows 24/25 include the fixed 15-atom source catalog and its source-package-owned runtime outputs. App builds consume shipped classes without package writes; authored sources alone skip class tools. The strict compiler classifies tag/quote/comment/raw-text contexts, preserves normal body markers and rejects incompatible joins, loop exits and divided literal names. All 3,282 original atom cases and current context/builder/catalog/hook controls pass. Final Quality passes 32 gates and both full Psalm passes, with 463 valid native runtime closures and 86 fresh site comparisons. Complete workflow replay passes all 186 expectations; source and normal-App suites and both compiled browser flows pass. No rule, threshold, owner map or RawHtml grant was weakened; native/session #150 remains unresolved.

- **2026-10-25 — declared rendering contracts and full architecture set (#149, #120, #121).** §0 rows24/25 accept only explicitly declared portable tpl inputs and refuse unsupported/unclaimed sources before publication; row31 exempts no authored Template-suffixed file. Row29 requires all12 pages/links and the stated source-backed inventories. Docs114 planted checks, core35, hook8 and actual installed-builder97 controls pass. Final32 gates/bothfullPsalm/446runtimeclasses/freshsite86, complete186 workflow and two compiledbrowser22 flows pass. Separate normal App28/all163appclassesfresh/site86 preserves14343 vendor/package files; the source fixture30/all446fresh remains separately identified. No threshold, suppression or compiler policy changed.

- **2026-10-23 — both Psalm passes (#135, #131).** §0 row 13 requires full ordinary level-1 analysis followed by explicit taint analysis. Both attempts preserve complete stdout/stderr and actual outcomes; either failure fails the gate, including launch errors. Real type, unused-code, taint and combined plants fail in both AIC and a relocated App, and both launch-error attempts remain visible. Method exception and unused-code checking stay on. Root/shipped XML record Chad's approved return-type policy because PHPStan level 10 holds return types. Workflow fixtures analyze their actual installed callers. Full replay 152/0/0 and all 32 gates pass with zero deferrals; native 409 cached closures, fresh whole-site 72/72 and 65 original output hashes pass.
- **2026-10-22 — generated custom registrations (#140).** §0 row 30 also holds the custom PHPCS/PHPMD XML to the writers that reference their actual classes. Missing and altered files failed gate:30 in AIC and a relocated app; altered files also failed AIC gate:24. App builds preserved installed package bytes, including deliberately stale XML. All seven PHPStan and twelve architecture rules still failed their planted violations, and ordinary unused-class analysis caught an unregistered class. No rule, threshold or analysis scope was weakened.
- **2026-10-22 — gate:29, the architecture docs (#107).** §0 gains row 29. Fifteen plants each failed gate:29 by name and were reverted: a row deleted from `gates.md`, a `gate:30` script, §0 row 28 deleted, the Stream heading renamed, a folder added to `ClassKinds::FOLDERS`, a namespace folder under `framework/src/`, the `->factory(` mention removed, an unknown `AIC\` name in a code span and in a fenced block, `gate:99`, `composer run nope`, an anchor no heading answers, an unlinked page, a banned word in prose and a third em dash. A banned word in a code span and two em dashes passed. gate:15's two new rules failed on a planted process name and ticket number (30 gates green).
- **2026-10-22 — gate:28, the house rules' probes (#104).** §0 gains row 28. A shipped rule's broken case made to hold failed gate:28 by name (`raw-html-grants broken-reason exited 4, not 1`), and so did gate-suite's (`broken-limit`); both plants were reverted (29 gates green).
- **2026-10-22 — The workflow package (#100).** §2 names `aic/framework` the one runtime package and admits `aic/workflow` beside it as a dev-only package in this repo (Chad, 2026-10-22: "inside is fine for now"). gate:9 gains two phpat rules: runtime code never depends on the workflow, and the workflow takes only the Command kind from the framework. Each was seen to fail on a planted violation (28 gates green).
- **2026-10-22 — Components only, and RawHtml counted (#99).** §0 gains row 27 (gate:27). gate:8 gains five rules and gate:9 one phpat test. Every rule, and each of gate:27's seven violation kinds, was seen to fail on planted violations, and the controls passed. A granted RawHtml page served the same bytes natively and under PHP (28 gates green).
- **2026-10-22 — Templates compile from plain PHP (#97).** §0 rows 24 and 25 name the build's compile step: the template classes come from each `*.html.php`, and the build refuses what it cannot type. Each refusal, `break` and `continue` in a loop, `empty()` per type and a stale class under gate:24 were seen on planted templates; the four demo templates render the same bytes (27 gates green).
- **2026-10-21 — gate:25, the template grammar (#96).** §0 gains row 25. Every refused category and a node outside the table were seen to fail on planted templates (38 lines for 38 plants, none for the allowed ones). The class tools leave templates to gate:25 by Chad's decision, which two valid templates in the tree proved (27 gates green).
- **2026-10-21 — gate:24 holds the assets (#95).** §0 row 24 names the generated wiring and `public/assets/`, both of which the build owns, so a stale bundle, a missing copy or an orphan fails it. Each was seen to fail on a planted violation.
- **2026-10-21 — gate:26, the module boundaries (#94).** §0 gains row 26, and row 16 names `modules` and `generated` in place of `app`. Each of gate:26's five rules was seen to fail on a planted violation (13 plants), and so was its fail-closed read.
- **2026-10-21 — The demo as modules (#93).** §14 rule 10 names the generated wiring and the framework kernels as what composes the app, since `app/` and its composition classes are gone.
- **2026-10-21 — gate:21 caches, gate:23 defers whole (#110).** §0's rows 21 and 23. gate:21 skips a class only when everything its probe compiled, ran and compared is unchanged (the closure's files by content, the compiler and its bridge libraries, host PHP, the pinned packages, the probe program), so its coverage is the same; a failure is never cached. gate:23 no longer runs a subset of steps without the database: since #41 every route opens a Postgres session, so the subset answered 500 on both sides and proved nothing. It now defers, named, as the rest of §0 requires.
- **2026-10-19 — Wiring is code (#21).** §14 rule 10 names the composition root, not a container, as what wires: constructors and readonly properties, typed throughout and compiled natively. The rule against `new` in business logic is unchanged. The PSR-11 container arrives with plugins (#5).
- **2026-10-19 — PSR overrides (#20, Chad's decision).** §14 rule 7 names its one exception: a `#[Override]` method whose PSR interface dictates `mixed`. PHPStan accepts only `array<mixed>` as the contravariant phpdoc of PSR-7's `array` parameters, PHPCS `DisallowMixedTypeHint` already exempted overrides, and semgrep's `aic-no-mixed-in-phpdoc-template` now does the same, bite-checked both ways.
- **2026-10-19 — nextgen (#3).** Ported from old AIC's Constitution at `e0778896` and trimmed to one package: the ward, Swoole and Forge history stays in the archive; the gates are renumbered from gate:0 with no gaps (#2, #14, #3); §3 restated for the FrankenPHP worker and native compilation from the first class (and in #33 for the compiled site); §4 collects the workflow rules old AIC cited as §9, §11, §15 and §16; §18.3 and §18.4 (the retired inspection checkpoint and worktrees) dropped.
