# Gates

`composer run gate` runs every quality gate, one after another, and keeps going after a failure so
one run names every failing gate. Each gate prints one `gate-result: gate:N passed|deferred|failed`
line, and the run ends with one `gate-summary:` line. One gate runs alone by its number:
`composer run gate:21` runs the native gate.

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
| 13 | Psalm | error level 1, taint analysis, unused code | type the value; validate input before it reaches a sink |
| 14 | phpcpd | no copy-paste of 4 lines or 50 tokens | extract the shared part |
| 15 | semgrep | the house ruleset: no env outside config, no static state, no singletons, no eval, no `mixed` in generics | restructure; state lives in wired objects |
| 16 | no dynamic require | no `require` or `include` of a computed path in runtime code | use a literal path or the build |
| 17 | gitleaks | no secrets in the history | remove the secret and rotate it |
| 18 | shellcheck | every shell script, at warning level | fix the script |
| 19 | documentation | no `(TODO)` or `(WIP)` markers and no dead relative links in Markdown | write the page or fix the link |
| 20 | captainhook | the git hooks are installed | `composer install` |
| 21 | native | every runtime class compiles, links, runs and matches PHP under elephc | reshape the class into a form elephc compiles; report a compiler defect |
| 22 | pipeline docs | *AIC only*: its own process documents | not run in an app |
| 23 | native site | the app compiled with `elephc --web` answers each step as the PHP app does | find the step that differs and the class behind it |
| 24 | build outputs | `generated/`, `build/surface.json`, the template classes and `public/assets/` match what the build writes | `composer run build` |
| 25 | templates | every `*.html.php` uses only the plain PHP template grammar | move the logic into the component's props |
| 26 | module boundaries | kind folders, the 15-file cap, exports and requires, no cycles, nothing outside `modules/` but the shell | move the class, export it, require the module, or split the module |
| 27 | raw html | every `RawHtml` use is listed and matches its grant in `config/raw-html.php` | use a component; or grant the use with its reason |
| 28 | house rules | *AIC only*: the workflow's shipped rules bite on their probes | not run in an app |
| 29 | architecture docs | *AIC only*: these pages state what the code has | not run in an app |

## The gates that need more than PHP

- **gate:21** compiles each runtime class with its dependencies under elephc, runs it, and compares
  the output with PHP's. A class whose files and toolchain are unchanged since its last green run
  is reported cached. When elephc rejects legal PHP, first reshape the code (typed properties and
  returns, small value objects, no runtime reflection on dynamic names); legal PHP elephc still
  gets wrong is a compiler defect to report to AIC.
- **gate:23** builds the whole app natively, serves it beside the PHP app, and replays a script of
  requests against both: status, content type, location and body must match. Every route opens a
  Postgres session, so when the database does not answer the gate defers as a whole.
- **gate:24** runs the build in check mode. A stale file means the build was not run after a change.

## Templates and the class tools

The class tools (Pint, Rector, PHPStan, PHPMD, PHPCS, Deptrac, Psalm) skip `*.html.php` templates;
gate:25 checks them instead. A fixer handed a template by path ignores its excludes and rewrites
it, so never pass a template to Pint or phpcbf.
