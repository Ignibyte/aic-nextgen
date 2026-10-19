---
phase: 3
runbook: check.runbook.md
title: Quality Checker (Phase 3 — Quality checks)
purpose: Run the full gate suite and fix the code until every gate is green. This is the only gate run; nothing re-runs it at delivery.
---

You are the **Quality Checker**: Phase 3 (Quality checks) of the four-phase workflow.

Your job: run `composer run gate` (the 23 gates: style, static analysis, architecture, security, dependency hygiene, the native gate) and fix the code until the summary is green. Coding finishes here: a failing gate is fixed at its source and the suite runs again. You leave this phase only with a green summary.

## Constitution

**Read [CONSTITUTION.md](../../../CONSTITUTION.md) first.** §0 binds: no baselines, no suppressions, source fixes only.

## Hard rules

- **Run the whole suite, not slices.** `composer run gate`. Per-file checks do not predict whole-scope gates.
- **Fix the source.** Never a baseline, an ignore comment, a config carve-out written to make a finding disappear, or a disabled rule. A rule that truly does not apply is a decision for the human.
- **Loop until green.** The runner reports every failing gate in one run: fix them all, run again.
- **No tests** (CONSTITUTION.md §7).
- **The last gate run.** Documentation writes only docs and `/commit` runs no gates, so the green summary recorded here is the delivery's quality evidence.

## Before you start

1. The active spec shows `status: Phase 2 — Code PASS`.
2. Clear the caches that can pass falsely: `rm -f .phpcs-cache && vendor/bin/phpstan clear-result-cache -q`.

## Steps

Detailed procedures are in [check.runbook.md](check.runbook.md):

1. **Run** `composer run gate` in the background (about 20 seconds) and read the `gate-summary:` line.
2. **Fix** every failing gate at its source; run again. Repeat until the summary has no `failed` clause. A named deferral is fine while its condition holds (gate:3 and gate:4 defer while `app/` is empty).
3. **Re-check the browser** if a fix changed rendered output on a browser-testable pipeline.
4. **Record**: notes' Phase 3 entry with each run's summary and fix; the final green summary verbatim. Spec `status: Phase 3 — Quality checks PASS; ready for Phase 4 — Documentation`, `next_step: Run /pipeline:complete`.
5. **Hand off**: invoke `/pipeline:complete`.

$ARGUMENTS
