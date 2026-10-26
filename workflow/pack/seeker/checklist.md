# The AIC checklist

What the seeker checks in a run's change: did the agent build it to AIC's standard. Each item is
one line of the seeker's answer, `pass` or `fail` with the file and line. Each names what the
gates already hold, so the seeker does not redo a gate, then what it judges, and a default
severity. Every finding opens with the id of the item it breaks. Severity says how much a
finding matters; none holds the run.

## A1 — No HTML in pages or controllers

The gates hold: no `Html` built outside the Renderer, no markup anywhere under `modules/` (no
template or markup file, no HTML in any string or text file, gate:32), a controller returns only
its kind's response, and every `RawHtml` use is a theme component's, within its grant in
`config/raw-html.php`.

Judge: markup built anywhere but a component's template and carried in (a Service string, a
grant); a new component whose job an atom (`AIC\Component\Domain`) or a theme component already
does, and a new named layout whose job a theme layout already does. Ask `bin/aic code:search` for each
new component and `bin/aic code:tree theme:<name>` for the theme. HIGH; MEDIUM for a missed reuse.

## A2 — Declared templates are plain `.phtml`

The gates hold: only explicitly declared `*.phtml` inputs use the
template grammar. The build escapes each into `build/generated/templates/`, where its lookup requires
it; no PHP class holds markup (#155).

Judge: logic a template carries past showing its component's values (sums, lookups, state
branches that should be separate components); presentation moved into a component's PHP to keep
a template legal. MEDIUM.

## A3 — Every class in its kind's folder and module

The gates hold: a class's kind decides its folder, 15 files per folder, nothing outside
`modules/` but the shell.

Judge: a class in the module that does not own its work (a module querying another module's
tables, code moved into `site` to dodge a require); a class named for a folder it does not belong
in. HIGH.

## A4 — Exports and requires declared

The gates hold: a module uses another's class only through that module's export and its own
`->requires(...)`.

Judge: an export added only so another module can reach a class it should not own; a binding,
route or command the change needs and the manifest does not declare. HIGH.

## A5 — No generated file edited

The gates hold: everything under `build/` (the generated wiring and templates, `build/assets/`, the
manifests) matches what the build writes.

Judge: a change under `build/` with no source change that makes
it; a build writer special-cased for one module. HIGH.

## A6 — No suppression, no gate-config change

The gates hold: no baseline file and no suppression comment.

Judge: any change to a tool's root configuration (`phpstan.neon`, `phpstan-architecture.neon`,
`psalm.xml`, `phpcs.xml.dist`, `deptrac.yaml`, `rector.php`, `pint.json`, `captainhook.json`),
to composer.json's gate scripts or allowed plugins, a removed or changed step in
`config/native-site.json`, or a change to `config/raw-html.php`, `config/workflow.php`, or a house
rule or its probe, that the spec's decisions do not record with a reason. A module's PSR-4 line,
its PHPCS root and an added step are free. HIGH; CRITICAL when the change
quiets a finding the run itself causes.

## A7 — A static guard for every new invariant

The gates hold: nothing; this is the rule they come from.

Judge: an invariant the spec or a comment states with no guard (a PHPStan, phpat or semgrep rule)
to hold it; a new rule with no planted violation shown to fail it; a test written instead of a
guard. HIGH.

## A8 — Native, and every workaround named

The gates hold: every runtime class compiles, links, runs and matches PHP (gate:21); the compiled
site answers as PHP does (gate:23); no runtime-dynamic `require`.

Judge: runtime behaviour moved outside `framework/src`, `modules` and `generated` (into `bin/`,
`config/` or `gates/`) so the native gates do not see it; a native gate's deferral the change
caused; code shaped around a compiler limit with no comment naming elephc and its carry ticket.
HIGH; MEDIUM for an unnamed workaround.

## A9 — The manifest and entry points match the diff

The gates hold: every changed path is in the spec's file manifest, and every declared entry point
is built as declared.

Judge: a manifest row with no change behind it; a change that does more than its row's purpose
says; an entry point that works differently from its row (another module, kind or name). MEDIUM;
HIGH when the change does work the spec does not ask for.

## L1 — Dead code

Judge: code the change adds that nothing calls, a parameter nothing reads, a branch that cannot
run. MEDIUM; HIGH when it hides a missing call.

## L2 — Criteria and decisions

Judge: a criterion or a locked-in decision the change plainly does not meet. HIGH.

## L3 — Security

Judge: input that reaches SQL, a shell, a file path or the page without the app's own guard; a
secret in code; a form or route that skips the CSRF or auth middleware the app uses. CRITICAL or
HIGH.

## L4 — Rebuilt what exists

Judge: the change writes a class, helper or component the app or the framework already has. Ask
`bin/aic code:search` before you raise it. MEDIUM.
