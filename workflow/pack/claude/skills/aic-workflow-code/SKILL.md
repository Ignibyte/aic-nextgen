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
- **Components are data.** A component's constructor takes the ready values as public promoted
  properties. Its template, `modules/<name>/templates/components/<Name>.html.php`, is plain PHP
  with `<?= expression ?>`, the alternative `if`/`foreach` syntax and property reads: no
  function or method calls.
- **The build.** After a change to a manifest, a template or a module's assets, run
  `composer run build`. It writes `generated/`, `build/surface.json`, `public/assets/` and each
  component's compiled `src/Component/<Name>Template.php`; never edit those by hand. The
  compiled template is a path in the module, so it needs its manifest row.
- **Find before you add.** `bin/aic code:search` and `bin/aic code:refs` before you write a
  class: reuse what the app has instead of a second copy.

## What never changes

The gates and the tools' configuration, `gates/`, baselines and suppressions; and what the hook
walls whatever the manifest says: `config/workflow.php`, `var/workflow/` (but the spec), `var/index/` (the code index),
`.claude/settings.json`, the workflow's skills, commands and lock in `.claude/`, `AGENTS.md` and
`CLAUDE.md`. Nothing outside the project and nothing under `vendor/` is written either, even through a
symlink: the hook judges where a write really lands. A gate that fails is fixed in the code. A house rule holds each run to this too.

## Leaving code

`bin/aic workflow:run`. Code's exit runs the gate suite and checks that every changed path is in
the manifest, the spec's frozen rows are still there, every declared entry point is built as
declared (and nothing undeclared was added), and the house rules hold. Fix each blocked row and
run again.

When everything else passes, code answers `inspection-due`: an independent reader must look at
the change before it leaves code.

1. Dispatch the `aic-seeker` agent with the Agent tool, in the foreground (not a fork, not in the
   background), with the prompt `Inspect the open run.` and nothing else. It takes its scope
   from `bin/aic workflow:seeker-brief`, not from you.
2. Pipe the `## Seeker Inspection` section it returns, unchanged, into
   `bin/aic workflow:seeker-report` (a heredoc is fine). Never write a ledger yourself and never
   edit what it returned: the hook recorded what it returned, and a ledger that differs is
   refused. A self-reviewed ledger counts as no inspection.
3. `bin/aic workflow:run`. An open CRITICAL or HIGH finding holds code; the row names each one.
   Fix them, then dispatch the seeker again; it marks the fixed rows `resolved`. MEDIUM and LOW
   are shown and do not hold.

When code passes, go to the [check skill](../aic-workflow-check/SKILL.md).
