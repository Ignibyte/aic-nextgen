---
description: Lightweight path for a trivial change. Skips Plan and Documentation, never Quality checks: code, the full gate suite until green, ship.
---

You are the **Quick Fix Agent**: a short path for a change too small for the four phases. It keeps Code and Quality checks and drops Plan and Documentation.

By invoking `/work-lite` instead of `/work`, the human chooses the light path (CONSTITUTION.md §4). This command keeps guardrails around that choice.

## When to use it

One-line fixes, config tweaks, typos, documentation fixes, string changes, a small method (under 30 lines).

## When NOT to use it (stop and redirect)

If the change involves any of these, stop and say: "This needs the full pipeline. Run `/work <description>` instead."

- A new system under `framework/src/<System>/`, or a new entry-point kind
- A new or changed PSR seam, or a new `Contracts` interface
- A new gate (`gates/bin/gate-*.php`, its row in `GateSuite` and its `composer.json` alias) or a new phpat, PHPStan or semgrep rule
- Container wiring, kernel boot, or the build-time manifests
- More than 5 files
- New domain or application logic, or a refactor across systems

## Steps

1. **Parse** `$ARGUMENTS`. If empty, ask what needs fixing.
2. **Recall** (not skippable; `enforce-completion.sh` checks it): `bin/ticket.sh show <n>` when a ticket is named; the parts of `docs/planning/plan.md` and `roadmap.md` the change touches.
3. **Scope check**: list the files that will change. Over 5, or any trigger above: stop and redirect.
4. **Fix it.** Minimal and focused, to the conventions in CLAUDE.md and CONSTITUTION.md §14.
5. **Quality checks** (not skippable; `enforce-completion.sh` checks for `composer run gate`):

   ```bash
   rm -f .phpcs-cache && vendor/bin/phpstan clear-result-cache -q
   composer run gate > "$TMPDIR/gate.log" 2>&1; tail -3 "$TMPDIR/gate.log"   # in the background
   ```

   Fix at the source until the `gate-summary:` line has no `failed` clause. A change to a page someone sees: look at it live in Marley's Browser tab or the local Playwright MCP (CONSTITUTION.md §17).
6. **Record**: a failure or insight goes in the ticket comment (step 8); a decision goes in `docs/planning/plan.md`; a durable cross-session lesson goes to auto-memory.
7. **Ship**:

   ```bash
   git switch -c fix/<slug>-<ticket>       # never commit to main
   git add <specific files>
   git commit -m "fix(<scope>): <description>"   # plus the attribution line the session specifies
   git push -u origin fix/<slug>-<ticket>
   gh pr create --title "…" --body "…"      # include the green gate-summary line
   gh pr merge <n> --squash --delete-branch && git switch main && git pull --ff-only && git fetch --prune
   ```

   Claude merges its own PRs once the gate is green (CLAUDE.md, Delivery). No gate runs at push.
8. **Ticket**: `bin/ticket.sh close <n> --note "Delivered via PR #N …"` (or `comment`), committed and pushed in `docs/planning`.

| Skipped | Not skipped |
|---|---|
| Plan (pipeline doc, design, confirmation) | ticket and docs recall |
| Documentation (changelog, AAR), unless the fix needs it | `composer run gate` until green |
| | a live look at any changed page |

$ARGUMENTS
