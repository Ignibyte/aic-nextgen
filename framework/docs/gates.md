# Gates

`composer run gate` runs every quality gate, one after another, and keeps going after a failure so
one run names every failing gate. Each gate prints one `gate-result: gate:N passed|deferred|failed`
line, and the run ends with one `gate-summary:` line. One gate runs alone by its number:
`composer run gate:21` runs the native gate.

## Where the gates come from

The suite is the dev package `aic/gates`: every gate's code, its entry scripts, the runner, the
build (`aic-build`) and each tool's base config. `vendor/bin/aic-gate --list` names every gate
with its scope and description, and `vendor/bin/aic-gate 21` runs one, as `composer run gate:21`
does. A tool's config in the project (`phpstan.neon`, `phpcs.xml.dist`, `deptrac.yaml`,
`psalm.xml`, `rector.php`) includes the base and names only the project's paths, so a change to
a base reaches the project on `composer update`. Run every gate from the project's root, where
composer runs scripts.

An app requires `aic/gates` for development and runs `vendor/bin/aic-gate init` once: it writes
the root configs, adds the ignore lines, and prints the lines composer.json carries (the
scripts and the allowed plugins), which the app pastes in itself. gate:30 holds those files to
the package's, so `init` again after `composer update` brings them back in step. gate:23 replays
the app's own requests from `config/native-site.json`: one step a line, each the method, path,
form, extra header and status both sites must answer alike. A new route adds its step. Both sites
run against one database, so a record each creates gets its own id: a step with `captures: true`
takes its answer's `Location` as `{note}` for the next steps, and that path reads as `<captured>`
in each site's answers before they are compared (#178).

Browser tests (#174) are Playwright specs in `tests/browser/`. `init` writes the root
`package.json` and `package-lock.json` that pin Playwright 1.62.1 and ignores `/node_modules/`;
`vendor/bin/aic-gate tools` runs `npm ci` and installs Chromium. gate:23 runs every kept test
against the compiled site it serves, with the gate user's login in `AIC_GATE_LOGIN` and
`AIC_GATE_PASSWORD`, and fails on any failing test or on Playwright missing or at another version.
`vendor/bin/aic-gate browser [--base-url=<url>] [<spec>…]` runs tests against any site with the
same config, `gates/browser/playwright.config.mjs`; a workflow run takes its looks with it.

The base NEON files include PHP configs that register the actual PHPStan rules and tagged
architecture services. PHPat's attributed callables return every named architecture rule. PHPCS
and PHPMD read custom XML generated from their custom classes. AIC's source build writes those
two package files; an app consumes them without writing its installed package. gate:30 compares
them with their writers in both profiles. Missing or stale package XML requires a rebuilt or
reinstalled package; `aic-gate init` restores the app's root stubs.

Root Rector configs call the package's typed setup with their own root and paths. Psalm's
bootstrap loads PHPCS's installed autoloader and token definitions, since PHPCS does not expose
those declarations through Composer's class map.

Gate 13 runs two complete project analyses against the same root `psalm.xml` and working
directory: ordinary first, then `--taint-analysis`. Both are attempted even when the first
fails. Their complete stdout and stderr are preserved, each mode reports its actual exit,
and either failure fails the gate. A process error is reported separately, without claiming
an observed exit, and does not skip the other attempt.

Root and shipped configs default `runTaintAnalysis` to false so pass one is ordinary. The
second pass explicitly enables taint. Method exception and unused-code checks remain on;
the approved `restrictReturnTypes` setting is false because PHPStan level 10 holds return
types. Its reason and the typed-startup/global-exception policy are recorded in the XML.

## The rules every gate shares

- **No baselines and no suppressions.** A finding is fixed in the source. No baseline file, no
  `@phpstan-ignore`, `@psalm-suppress`, `@phpcs:ignore` or `@phpmd` annotation, no lowered
  threshold, no path excluded to hide a finding. gate:5 refuses the files and settings that would
  hide one.
- **A false positive is a shape problem.** When a tool misreads correct code, reshape the code
  into a form the tool reads; never silence the tool.
- **A deferral is not a pass.** A gate that cannot run in full (no database, a missing tool)
  defers and says why, and the summary names it. Nothing deferred counts as checked.

## Every gate

Gates marked *AIC only* check the framework's own repository and do not run in an app.

| # | Gate | What it checks | Usual fix |
|---|---|---|---|
| 0 | composer audit | the installed packages against the advisory database | update the package |
| 1 | composer-normalize | `composer.json` in canonical order | `composer normalize` |
| 2 | require allow-list | runtime `require` holds only `php`, `ext-*`, `psr/*` and `aic/framework` | move a tool to `require-dev`; write the helper instead of requiring a framework |
| 3 | require-checker | every symbol runtime code uses comes from a declared require | declare the extension or package |
| 4 | composer-unused | no required package goes unused | drop the require |
| 5 | no baselines | no baseline files, no ignored errors, no suppression levels | fix the finding the baseline hid |
| 6 | Pint | PSR-12 and the house style | `vendor/bin/pint` on the file (never on a template) |
| 7 | Rector | PHP 8.5 idioms, dead code, type coverage, naming (dry run) | `vendor/bin/rector process` on the file, then read the change |
| 8 | PHPStan | level 10 with the strict sets, 100% type coverage, cognitive complexity, dead code, AIC's own rules | type the code; split a method; remove what nothing calls |
| 9 | phpat | the architecture rules: kinds, sealed responses, rendering | move the dependency or the class |
| 10 | PHPMD | every ruleset: methods up to 30 lines, classes up to 300, 15 methods, coupling 15 | split the class or the method |
| 11 | PHPCS | PSR-12 and slevomat, lines up to 140 | `vendor/bin/phpcbf` on the file (never on a template) |
| 12 | Deptrac | the layer ladder in every namespace (see [systems.md](systems.md#the-layer-ladder)) | move the class to the right layer, or depend on a contract |
| 13 | Psalm | full ordinary error level 1, then full taint analysis; unused code | type the value; validate input before it reaches a sink |
| 14 | phpcpd | no copy-paste of 4 lines or 50 tokens | extract the shared part |
| 15 | semgrep | the house ruleset: no env outside config, no static state, no singletons, no eval, no `mixed` in generics | restructure; state lives in wired objects |
| 16 | no dynamic require | no `require` or `include` of a computed path in runtime code | use a literal path or the build |
| 17 | gitleaks | no secrets in the history | remove the secret and rotate it |
| 18 | shellcheck | every shell script, at warning level | fix the script |
| 19 | documentation | no `(TODO)` or `(WIP)` markers and no dead relative links in Markdown | write the page or fix the link |
| 20 | captainhook | the git hooks are installed | `composer install` |
| 21 | native | every runtime class compiles, links, runs and matches PHP under elephc | reshape the class into a form elephc compiles; report a compiler defect |
| 22 | pipeline docs | *AIC only*: its own process documents | not run in an app |
| 23 | native site | the app compiled with `elephc --web` answers each step as the PHP app does, a step reaches every GET route in `build/surface.json`, every kept browser test in `tests/browser/` passes on the compiled site, and every HTML answer passes the HTML lint: no inline style or script, each `id` once, an `alt` on every `<img>`, no HTML5 parser error on a full document | find the step that differs and the class behind it; add a step for an unreached route; fix the markup in the template that writes it |
| 24 | build outputs | everything under `build/` (the generated wiring and templates, `build/assets/`, the manifests) and the wiki match what the build writes | `composer run build` |
| 25 | templates | the theme's `*.phtml` files and the atoms use the grammar and the HTML lint (see [templates.md](templates.md#markup-and-whitespace)); a template anywhere else, or an unsupported source, fails | move the logic into the component's props; style through a class in the theme's stylesheet, script from a script file |
| 26 | module boundaries | kind folders, the 15-file cap, exports and requires, no cycles, no component, template or asset in a module, nothing outside `modules/` but the shell and the theme's classes | move the class (a component to the theme), export it, require the module, or split the module |
| 27 | raw html | every `RawHtml` use is listed, matches its grant in `config/raw-html.php`, and sits in a theme component | use a component; or grant the theme component's use with its reason |
| 28 | house rules | *AIC only*: the workflow's shipped rules bite on their probes | not run in an app |
| 29 | architecture docs | *AIC only*: these pages state what the code has | not run in an app |
| 30 | suite files | root configs match the stubs, composer scripts/plugins match the suite, and custom PHPCS/PHPMD XML matches its writers | `vendor/bin/aic-gate init` for root stubs; rebuild or reinstall a stale package |
| 31 | make scaffold | *AIC only*: `modules/scaffold/` and its theme components and layout are what the twelve `bin/aic make:*` lines make, byte for byte, and `make:module` prints the lines the build asks for | not run in an app |
| 32 | module markup | no file under `modules/` holds HTML: no template or markup file, no inline output, no tag, markup declaration or character reference in a literal or text file, no HTML function; any `<` in a controller | move the markup into a theme component; write a usage placeholder as `{name}` |

## The gates that need more than PHP

- **gate:21** compiles each runtime class with its dependencies under elephc, runs it, and compares
  the output with PHP's. A class whose files and toolchain are unchanged since its last green run
  is reported cached. When elephc rejects legal PHP, first reshape the code (typed properties and
  returns, small value objects, no runtime reflection on dynamic names); legal PHP elephc still
  gets wrong is a compiler defect to report to AIC.
- **gate:23** builds the whole app natively, serves it beside the PHP app, and replays a script of
  requests against both: status, content type, location, body and the `Tessera-*`, `Vary` and
  `Cache-Control` headers must match (#173). It also lints every
  HTML body the PHP app answers with, as sent (#170), and fails on any GET route of
  `build/surface.json` that no step reaches (#171): a GET step expecting a status below 400 whose path,
  as sent and without its query, the router would match. Every route opens a Postgres session, so when
  the database does not answer the gate defers as a whole, the lint with it.
- **gate:24** runs the build in check mode. A stale file means the build was not run after a change.

## Templates and the class tools

The class tools (Pint, Rector, PHPStan, PHPMD, PHPCS, Deptrac) read `*.php` files only, so every
`*.phtml` template is outside them; gate:25 checks the authored templates instead. Psalm follows
each lookup's literal `require` into the generated template it names and checks it there; the
native gate compiles each lookup with its templates. Never pass a template to Pint or phpcbf.

The required architecture set also includes [rendering.md](rendering.md), [templates.md](templates.md),
[tessera.md](tessera.md), [security.md](security.md), [native.md](native.md) and
[workflow.md](workflow.md). Gate:29 requires all twelve pages and their README links, and compares
the new tables with public method names, string constants, enum cases and actual development
command registrations. These inventories do not mechanically verify every prose claim.
