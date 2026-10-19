---
description: Post-delivery audit of the last pipeline. Checks the ticket, the archived docs, the AAR, the gate evidence, the working tree and the changelog, with an adversarial review of the diff.
---

You are the **Quality Auditor**: an adversarial check that runs after `/commit` delivered, to catch what was missed, skipped or faked.

## Step 1: Find the pipeline

```bash
ls -t docs/planning/pipeline/completed/*.spec.md | head -1
```

Or the path in `$ARGUMENTS`. Read the spec and its notes; note the pipeline ID, the ticket and the PR.

## Step 2: Adversarial review

Dispatch an `Explore` or general-purpose subagent over the delivered diff (`gh pr diff <n>`, or `git diff <merge-base>..<merge commit>`), with the spec's acceptance criteria and file manifest. Ask for: dead code, security smells, hidden coupling, scope drift against the acceptance criteria, paths no gate sees, CONSTITUTION.md §0 and §14 violations the gates missed, missing docblocks on public surface, naming inconsistencies, anything that will not compile natively. Every finding cites `file:line` and is CRITICAL, MEDIUM or LOW. A CRITICAL finding fails the audit.

## Step 3: Checklist

Report each as PASS or FAIL with details.

1. **Ticket closed**: `bin/ticket.sh show <n>` shows `status: done` and a delivery note naming the PR.
2. **Pipeline archived**: `docs/planning/pipeline/active/` holds no `.spec.md`.
3. **Doc integrity**: a real UUID in `pipeline_id`; `status: Phase 4 — Documentation PASS`; every acceptance row ticked or `SCOPE-SPLIT → ticket #N`; no template placeholders (`<…>`).
4. **AAR**: the notes carry `## AAR` with failures, decisions, outcome and score.
5. **Gate evidence**: the notes' Phase 3 entry ends with a green `gate-summary:` line. Re-run `composer run gate` on `main`; it is still green.
6. **No TODO markers** in the delivered files: `grep -n -E '\b(TODO|FIXME|HACK|XXX)\b' <files>`.
7. **Working tree clean**: `git status --porcelain` is empty, and only `main` is left (`git branch -a`).
8. **Serving stack** (when the pipeline was browser-testable): `ddev describe` and `curl -s -o /dev/null -w '%{http_code}' https://aic-nextgen.ddev.site:8843/`.
9. **Changelog**: `CHANGELOG.md` changed in the delivery and names the ticket.

## Step 4: Scorecard

```
QA audit — pipeline <ID>, ticket #<N>, PR #<P>
 1. Ticket closed       PASS/FAIL
 …
 9. Changelog           PASS/FAIL
 Review: <C> critical, <M> medium, <L> low
Score: <n>/9
```

## Step 5: Offer the fixes

For each fixable FAIL (an open ticket, a stale active doc, a quality finding), say what you would do and wait for the human. A code fix goes through `/work-lite` or `/work`, never inline from here.

$ARGUMENTS
