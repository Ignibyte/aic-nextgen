# The AIC checklist

What the seeker judges in a run's change. Each item names what the gates already hold, so the
seeker does not redo a gate, then what it judges, and a default severity. Every finding opens with
the id of the item it breaks.

## A1 — Components only

The gates hold: no `Html` built outside the Renderer, no markup string in a controller, a
controller returns only its kind's response, layouts and regions take components, and every
`RawHtml` use sits within its grant in `config/raw-html.php`.

Judge: markup built outside a controller and carried in through a grant; a component that should
have been reused (an atom, or a component another module exports); one template doing several
components' work. HIGH; MEDIUM for a missed reuse.

## A2 — Tessera where it belongs

The gates hold: the compiled site answers each step as PHP does.

Judge: a page or part that should be a keyed Tessera component or answer a region fragment, where
the app's own pattern does. MEDIUM; HIGH when the spec asked for it.

## A3 — Module boundaries in spirit

The gates hold: a class's kind decides its folder, 15 files per folder, exports and requires, and
nothing outside `modules/` but the shell.

Judge: an export added only so another module can reach a class it should not own; code moved
into `site` to dodge a require; a class named for a folder it does not belong in; a module reading
another module's tables. HIGH.

## A4 — Plain PHP templates in spirit

The gates hold: every `*.html.php` uses only the plain PHP template grammar, in its place.

Judge: presentation moved into a component's PHP to keep a template legal; a template branching
on state that should be separate components. MEDIUM.

## A5 — Generated code is never edited by hand

The gates hold: `build/surface.json`, `generated/`, the compiled templates and `public/assets/`
match what the build writes.

Judge: a change under `generated/`, `build/` or `public/assets/` with no source change that makes
it; a build writer special-cased for one module. HIGH.

## A6 — No suppression, no carve-out, no weakened rule

The gates hold: no baseline file and no suppression comment.

Judge: any change to a gate or a tool's configuration or rule code (`phpstan.neon`, `psalm.xml`,
`phpcs.xml.dist`, `deptrac.yaml`, `rector.php`, `pint.json`, `semgrep.yml`, `phpmd.xml`, the
gate scripts in `composer.json`, `gates/`, `bin/gate-*`), to `config/raw-html.php`, to
`config/workflow.php`, or to a house rule or its probe, that the spec's decisions do not record
with a reason. A module's PSR-4 line and its PHPCS root are free. HIGH; CRITICAL when the change
quiets a finding the run itself causes.

## A7 — Every invariant is a static guard

The gates hold: nothing; this is the rule they come from.

Judge: an invariant the spec or a comment states with no guard (a PHPStan, phpat or semgrep rule)
to hold it; a new rule with no planted violation shown to fail it; a test written instead of a
guard. HIGH.

## A8 — Native compilation not dodged

The gates hold: every runtime class compiles, links, runs and matches PHP; the compiled site
answers as PHP does; no runtime-dynamic `require`.

Judge: runtime behaviour moved outside `framework/src`, `modules` and `generated` (into `bin/`,
`config/` or `gates/`) so the native gates do not see it; a native gate's deferral that the change
caused. HIGH.

## A9 — A workaround names its reason

The gates hold: nothing.

Judge: code shaped around a compiler limit with no comment naming elephc and the ticket that
carries the fix; a workaround whose fix has landed. MEDIUM.

## L1 — Dead code

Judge: code the change adds that nothing calls, a parameter nothing reads, a branch that cannot
run. MEDIUM; HIGH when it hides a missing call.

## L2 — Drift from the plan

Judge: the change does something the spec does not ask for, or leaves out something it does: a
criterion, a decision, an entry point, a manifest row with no change behind it. HIGH.

## L3 — Coupling

Judge: a class that reaches across systems or modules through a concrete class where the app has
a seam; a new dependency where an existing service does the job. MEDIUM.

## L4 — Security

Judge: input that reaches SQL, a shell, a file path or the page without the app's own guard;
a secret in code; a form or route that skips the CSRF or auth middleware the app uses. CRITICAL or
HIGH.

## L5 — Rebuilt what exists

Judge: the change writes a class, helper or component the app or the framework already has. Ask
`bin/aic code:search` and `bin/aic code:refs` before you raise it. MEDIUM.

## L6 — Criteria shown

Judge at complete only, where each criterion's `Met` cell cites a check: a cited check that does
not show what the criterion says. HIGH when a check exists that would show it (a rendered request
for a route, a gate for the code's shape) and the run cites another; MEDIUM when no check the
engine runs can show it (a command's output), so the operator looks at it by hand. At code and
check nothing is ticked yet, so raise no L6 there; a criterion the change plainly does not meet is
L2.
