---
name: aic-workflow-code
description: Write the code for a run of this AIC app's workflow (the code phase). Use when a run is at code, or the code phase's checks hold it.
---

# Code: build what the spec says

At code you may write the spec and each path in its file manifest, nothing else. When you need
another file, add its row to the manifest first; the run records it as declared late.

## How this app is built

- **Modules.** Each feature is a module in `modules/<name>/`. Its manifest,
  `modules/<name>/<name>.module.php`, declares what the module has: its routes, commands, jobs,
  blocks, migrations, the modules it requires and what it exports. `bin/aic code:tree <module>`
  shows one.
- **Folders by kind.** A class lives in `modules/<name>/src/<Kind>/` as
  `App\<Name>\<Kind>\<Class>`. Pages, Apis and Streams go in `Controller/`; then `Command/`,
  `Job/`, `Block/`, `Component/`, `Migration/`, `Repository/`, `Form/`; plain classes in
  `Entity/`, `Service/` or `Form/`. A gate holds the folders and their size; its message names
  the folder to use.
- **Five kinds.** Every entry point is a Page (an HTML layout of components, a fragment or a
  redirect), an Api (JSON or XML), a Stream (events or a download), a Command (a CLI exit code)
  or a Job (queued or scheduled work). A controller returns its kind's response and nothing
  else; a helper goes in `src/Service/`.
- **Components are data, in the theme.** A component's constructor takes the ready values as
  public promoted properties. It lives in the theme `config/app.php` names: its template in
  `themes/<theme>/components/<Name>/<name>.phtml`, its class (and value classes) in
  `themes/<theme>/src/Components/<Name>/<Name>.php`. The template is plain PHP
  with `<?= expression ?>`, the alternative `if`/`foreach` syntax and property reads: no
  function or method calls. A module holds no template or asset; its controllers build components.
- **The build.** After a change to a manifest, a template or a theme asset, run
  `composer run build`. It writes everything under `build/`: `build/generated/` (including the escaped
  templates in `build/generated/templates/` that the lookups require), `build/surface.json`,
  `build/assets.json`, `build/assets/`; and `docs/wiki/`; never edit those by hand. These exact inventories and generated directories
  are build outputs; declare every authored source and the edited module manifest.
- **Make a new class.** For a new module, page, api, component, block, command, job,
  migration, entity (`make:entity`: entity, repository, migration and list page) or first site theme, prefer `bin/aic make:*` (`bin/aic command:list` lists them) to writing it by
  hand: it writes the class in its folder, adds the manifest call, and the result passes every
  gate. `--dry-run` lists the paths it will write; add each to the manifest first.
- **Themes and templates.** `make:component <Class>` writes the component and its template into
  the theme and edits no manifest. A default `make:page` writes its own view there too;
  `make:page --component=<Class>` reuses a theme component. `make:theme <name>` writes
  `themes/<name>/layouts/` (`html.phtml`, `page.phtml`), `src/Layouts/Page.php` and CSS, then prints the
  `config/app.php`, Composer and PHPCS lines to add. A Page controller takes the framework's
  `PageDocuments`, names its title and may name a layout
  (`content([...], 200, 'Stock', ThemeLayoutName::Focus)`); a layout
  ships `page--<name>.phtml`, `html--<name>.phtml` or both, each falling back to the default.
  `make:layout <name>` writes a page layout (`src/Layouts/<Case>Page.php`, the content region only,
  and `layouts/page--<name>.phtml`); after `composer run build`, `make:page … --layout=<name>` names it. The
  Composer prefix for `App\\Theme\\Components\\` must be configured first; run
  `composer dump-autoload`. Use the dry-run to declare every path it lists.
- **Find before you add.** `bin/aic code:search` and `bin/aic code:refs` before you write a
  class: reuse what the app has instead of a second copy.

## What never changes

The gates and the tools' configuration (the root files ignibyte/aic-gates ships, which the operator restores
with `vendor/bin/aic-gate init` between runs), baselines and suppressions; and what the hook
walls whatever the manifest says: `config/workflow.php`, `var/workflow/` (but the spec), `var/index/` (the code index),
`.claude/settings.json`, `.codex/`, the installed `.agents/` skills, the workflow's skills, commands and lock in `.claude/`, `AGENTS.md` and
`CLAUDE.md`. Nothing outside the project and nothing under `vendor/` is written either, even through a
symlink: the hook judges where a write really lands. A gate that fails is fixed in the code. A house rule holds each run to this too.

One gate runs alone with `composer run gate:<N>`; `vendor/bin/aic-gate --list` names them. A new
route gets its step in `config/native-site.json`, the requests gate:23 sends to both sites: add a
line, never change or remove one.

## Leaving code

`bin/aic workflow:run`. Code's exit runs the gate suite and checks that every changed path is in
the manifest, the spec's frozen rows are still there, every declared entry point is built as
declared (and nothing undeclared was added), and the house rules hold. Fix each blocked row and
run again.

Read a failed gate's stored output with `bin/aic workflow:logs gate:<N>` before changing code.
Use `--phase=code --attempt=<N>` to inspect a specific attempt.
Use `--json` to retain the exact stored transcript and its metadata.
Use `--from-line=1 --lines=100` for an explicit line window.
Read its continuation metadata when more lines remain.
Use `bin/aic workflow:logs gate` for aggregate suite failures, including timeouts.
The command does not rerun gates or write the evidence database.
Historical capture completeness can be unknown. Do not call an old tail complete.
Do not query the protected database directly.

When everything else passes, code answers `inspection-due`: an independent reader checks the
change once before it leaves code.

1. Dispatch the installed `aic-seeker` with your client's subagent tool. In Codex, select its native role.
   Use the prompt `Inspect the open run.` and nothing else. Wait for its actual return. It takes its scope
   from `bin/aic workflow:seeker-brief`, not from you.
2. Pipe the `## Seeker Inspection` section it returns, unchanged, into
   `bin/aic workflow:seeker-report` (a heredoc is fine). Never write a ledger yourself and never
   edit what it returned: the hook recorded what it returned, and a ledger that differs is
   refused. A self-reviewed ledger counts as no inspection.
3. `bin/aic workflow:run`. The `seeker` row passes and lists every open finding with its
   severity. None holds the run: fix what you agree with while you are still at code, and name
   the rest in your report. A run has one seeker pass, so `seeker-report` refuses a second ledger.

When code passes, go to the [check skill](../aic-workflow-check/SKILL.md).
