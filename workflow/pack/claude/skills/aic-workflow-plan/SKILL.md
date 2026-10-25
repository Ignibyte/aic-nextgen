---
name: aic-workflow-plan
description: Write the spec for a run of this AIC app's workflow (the plan phase). Use when a run is at plan, or the envelope says the spec is not written or not a contract.
---

# Plan: write the spec

At plan you may write one file: the run's spec, `var/workflow/spec-<number>.md`. Copy
[spec-template.md](spec-template.md) there and fill it in.

## Look before you write

Read AIC's architecture pages first: `{vendor}/aic/framework/docs/README.md` lists them, and
`kinds.md`, `modules.md` and `conventions.md` beside it say how a module's code is shaped.

Ask the code index about the code the ticket touches before you name a class, a path or a route:

- `bin/aic code:tree` lists the modules and the framework's systems; `bin/aic code:tree <module>`
  lists one module's routes, exports, requires and files, each with its classes and methods.
- `bin/aic code:search <text>` finds classes, methods and routes by name.
- `bin/aic code:refs <name>` lists what uses a class or method.

The index follows the code, and the engine logs your lookups during the run. Read the files the
index points you to before you plan a change to them.

Plan cannot pass until the `grounding` row does. It reads the lookups you made during this run
at plan, on an index the engine rebuilds itself, and holds the phase naming every miss at once:

- at least one `bin/aic code:tree` answered;
- each File Manifest row is one file path with `create`, `modify` or `delete`; a modify or delete
  row names a file git lists, a create row a path that is not there yet;
- each file you modify or delete was shown by `code:tree <module>` or `code:tree <path>`, each
  existing Entry Points class by `code:refs <Class>`, and each file you create sits in an area
  you outlined (a new module or folder: `bin/aic code:tree`); a `code:refs` answer counts for
  its subject only, not the lines that refer to it;
- before you create a class, `bin/aic code:search <part of its name>` looked for one the app
  already has.

Each reason names the lookup to run. Run it, fix what it shows, then `bin/aic workflow:run`.

## The sections

- **Frontmatter:** `ticket` is the ticket's number. `browser_testable: Yes` when the work changes
  a page a person sees; then add a `## Browser Check` section with one
  `- [ ] <URL> — <what to do> → <what should show>` item per check. Check will need a browser
  tool in your session and the site in `config/workflow.php`; when either is missing, tell the
  operator now.
- **Acceptance Criteria:** one row per thing that is true when the ticket is done, numbered from
  1, each with an empty `Met` cell. Write targets a gate, the build or a request can show.
- **File Manifest:** one row per file the run will create, modify or delete, the path in
  backticks, one path per row. Everything you write later must be here, docs included. Leave
  out the exact generated inventories `build/surface.json` and `build/assets.json`, plus
  `generated/`, `public/assets/` and `docs/wiki/`, which `composer run build` writes. Other
  build files need their own exact manifest rows. A changed
  component template also changes its compiled `src/Component/<Name>Template.php`: list both. A
  new module also needs its line in `composer.json` and its root in `phpcs.xml.dist`.
- **Locked-In Decisions:** the choices the code must keep, numbered, each with its reason.
- **Entry Points:** every route, command or job the run adds or changes, by kind (Page, Api,
  Stream, Command or Job), method, path or name, class, module, the status a GET answers with
  (200 when blank) and, for a path with a `{parameter}`, an Example path to request. Keep the
  heading with no rows when the work adds none.

## Leaving plan

`bin/aic workflow:run`. The engine reads the spec as a contract and freezes it. From then on its
criteria, manifest, decisions, entry points and browser items are append-only: a row removed or
reworded is a breach, and the phase holds until it is back. A manifest row added later is allowed
and recorded as declared late. The plan exit also runs the house rules' own probes.

When plan passes, go to the [code skill](../aic-workflow-code/SKILL.md).
