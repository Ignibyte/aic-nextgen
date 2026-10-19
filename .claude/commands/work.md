---
description: Entry point for any AIC work. Checks the environment, finds the ticket, then hands off to /pipeline:plan.
---

You are the **Work Initializer**: the pre-flight check and the entry point for all project work.

Your job is to verify the environment is ready, find or name the ticket, and hand off to `/pipeline:plan`. You do NOT write application code or run pipeline phases.

## Step 1: Parse the request

Read `$ARGUMENTS`: a ticket number (`/work 15`) or a description. If empty, ask what needs building or changing.

## Step 2: Waiver check

Only the human can waive the pipeline (CONSTITUTION.md §4). If they said "just do it", "no pipeline" or similar, acknowledge it and point to `/work-lite` for a trivial change. Otherwise continue.

## Step 3: Pre-flight

Run every check; report failures and fix them before going on.

```bash
# Tickets and planning (docs/planning is the private Ignibyte/aic-planning checkout)
test -x bin/ticket.sh && test -d docs/planning/tickets && bin/ticket.sh count --status all
test -d docs/planning/pipeline/active && test -d docs/planning/pipeline/_templates
# .mcp.json carries the local Playwright MCP server (owner-managed: never write it)
test -f .mcp.json && jq -r '.mcpServers | keys' .mcp.json
# PHP tooling and the external tools the gates use
for t in pint phpstan rector phpmd phpcs deptrac psalm composer-unused phpcpd captainhook; do
    printf '%s: %s\n' "$t" "$(test -x vendor/bin/$t && echo OK || echo MISSING)"
done
for t in semgrep shellcheck; do printf '%s: %s\n' "$t" "$(command -v $t >/dev/null && echo OK || echo MISSING)"; done
printf 'gitleaks: %s\n' "$(test -x tools/gitleaks && echo OK || echo MISSING)"
printf 'elephc: %s\n' "$(test -x /srv/stacks/elephc/target/release/elephc && echo OK || echo MISSING)"
# Hooks wired (.claude/settings.json)
jq -r '.hooks | to_entries[] | .key + ": " + ([.value[].hooks[].command | sub(".*/hooks/"; "")] | join(", "))' .claude/settings.json
# The served app, when the work is browser-testable
curl -s -o /dev/null -w '%{http_code}\n' https://aic-nextgen.ddev.site:8843/
```

Missing `docs/planning`: STOP. The pipeline docs and tickets live there; clone `Ignibyte/aic-planning` into it. Missing PHP tools: `composer install`. Missing external tools: `bash bin/install-tools.sh`. Missing `.mcp.json`: ask Chad; never write it.

## Step 4: Active pipeline

```bash
ls docs/planning/pipeline/active/*.spec.md 2>/dev/null
```

If one exists, present it and ask: resume it (the next phase is in its `next_step`), or archive it first? Never two at once.

## Step 5: Context

1. `bin/ticket.sh show <n>` when a ticket is named; otherwise `bin/ticket.sh list` and `grep -ril '<topic>' docs/planning/tickets/` for existing work. Never create a duplicate.
2. Skim `docs/planning/handoff.md`, then the parts of `roadmap.md` and `plan.md` that cover the request.
3. Present what you found.

## Step 6: Hand off

- All checks pass: **"Environment verified. Run `/pipeline:plan <ticket or request>` to begin."** In auto-approve mode ("auto approve", "back to back"), invoke it yourself.
- Checks failed: list what is missing and how to fix it; do not go on.

## The four phases

| Command | Phase | What it does |
|---|---|---|
| `/pipeline:plan` | 1 Plan | spec, design, file manifest, browser check list; the human confirms |
| `/pipeline:implement` | 2 Code | writes the code; the live browser check for UI work |
| `/pipeline:check` | 3 Quality checks | `composer run gate` until green: the only gate run |
| `/pipeline:complete` | 4 Documentation | docs, plan.md, changelog, AAR, the docs gates |
| `/commit` | delivery | branch, commit, PR, merge, archive, ticket close; no gates |

AIC writes no unit, mutation, coverage or regression tests (CONSTITUTION.md §7). Quality is the code gates, fixed at the source: no baselines, no suppressions (§0).

$ARGUMENTS
