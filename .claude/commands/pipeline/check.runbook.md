---
spec: check.md
loaded_on: demand
purpose: Detailed Phase 3 (Quality checks) procedures — running the gate, reading the summary, the usual failures and their source fixes, recording the result.
---

# /pipeline:check — Runbook

## Step 1 — Run the gate

```bash
rm -f .phpcs-cache && vendor/bin/phpstan clear-result-cache -q
composer run gate > "$TMPDIR/gate.log" 2>&1     # Bash run_in_background; about 20 seconds
```

The suite runs on the host (no DDEV, no database). Read the log once, after it finishes (CONSTITUTION.md §18.1). aic/gates' runner (`vendor/bin/aic-gate`) runs every gate even after a failure and ends with one line:

- `gate-summary: N passed, D deferred (…). …` with no `failed` clause: green. Each deferral names its gate and reason.
- `gate-summary: … K failed (gate:a,gate:b).`: red, and every failing gate is named.

Read each failing gate's own output above the summary for the finding.

## Step 2 — Fix and repeat

Fix at the source, then run the whole suite again. Common shapes:

| Gate | Usual cause | Source fix |
|---|---|---|
| gate:1 normalize | `composer.json` key order, or a stale lock hash | `composer update --lock`, then `composer normalize` |
| gate:6 Pint | formatting | `vendor/bin/pint <file>` |
| gate:7 Rector | naming rules fire across the codebase | `vendor/bin/rector process <file>` and keep Rector's output |
| gate:8 PHPStan | types, strict rules, shipmonk, unused public API | fix the types; never `@phpstan-ignore` |
| gate:9 phpat | a rule in `gates/architecture/` | follow the rule |
| gate:10 PHPMD | method > 30 lines, class > 300, too many methods | extract methods or a collaborator class |
| gate:11 PHPCS | slevomat rules (cognitive complexity ≤ 5, line length 140, function length 20) | follow the sniff; split the method |
| gate:12 Deptrac | a layer reaches up, or a class sits outside every layer | move the dependency down the ladder |
| gate:14 phpcpd | four or more copied lines | share them through a class or method |
| gate:15 semgrep | the AIC rules (no static state, no env outside config, …) | follow the rule |
| gate:21 native | elephc rejects or miscompiles a runtime class | restructure the class; a real compiler defect is a carry |

Composer installs need no `COMPOSER_ROOT_VERSION`: the path repository pins `aic/framework` at `0.1.x-dev`.

## Step 3 — Browser re-check

When a fix changed rendered output on a browser-testable pipeline, look at the affected pages again and update the notes' browser check record.

## Step 4 — Record

Notes' `## Phase 3: Quality checks`: one line per run (`run 1: 1 failed (gate:10) — Runner::run 34 lines → extracted probeAll()`), ending with the green summary line verbatim. Spec: `status: Phase 3 — Quality checks PASS; ready for Phase 4 — Documentation`, `last_command: /pipeline:check`, `next_step: Run /pipeline:complete`.

`enforce-completion.sh` blocks Stop in this phase when the transcript shows no `composer run gate`.
