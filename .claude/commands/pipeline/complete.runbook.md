---
spec: complete.md
loaded_on: demand
purpose: Detailed Phase 4 (Documentation) procedures — decisions and docs, changelog, self-review, AAR, memory promotion, the two docs gates, pipeline-doc update, hand-off to /commit.
---

# /pipeline:complete — Runbook

Documentation is the last phase. The code passed Quality checks and is not touched again: this phase writes only docs (`enforce-agent-scope.sh`). If writing the docs exposes a code problem, go back through `/pipeline:check`.

## Step 1 — Decisions (mandatory)

Nextgen keeps its decisions in the private planning repo, not in ADR files:

- A new decision, or a changed one: `docs/planning/plan.md`, Decisions (who decided, the date, the reason).
- A milestone, the order or an open decision moved: `docs/planning/roadmap.md`.
- The state a new session starts from changed: `docs/planning/handoff.md`.

`enforce-pipeline-completion.sh` blocks Stop until this session wrote one of them (or another doc outside the pipeline and ticket directories).

## Step 2 — Other docs

The README, CLAUDE.md, CONSTITUTION.md (only through an amendment, CONSTITUTION.md §Amendment), or any doc the change affects. Keep them true to the code as it now is.

## Step 3 — Changelog

`CHANGELOG.md`: a new entry at the top of `Unreleased` for the substantive change, with the ticket number.

## Step 4 — Self-review (CONSTITUTION.md §4)

Answer in the notes: did any phase use a workaround? Are the docs the clearest account of what was built? Would a developer reading them in six months understand what was built and why?

## Step 5 — After-action review (mandatory)

Write `## AAR` in the notes; `enforce-completion.sh` blocks Stop until it exists:

1. **Failures**: one row per failure across the phases: category (validation, runtime, security, performance, infra, compilation, external), severity, one-line description and root cause.
2. **Decisions**: one row per decision, pointing at `plan.md` or the spec's locked-in decisions.
3. **Recall**: which precedent (completed pipelines, plan.md, memory) was used or ignored.
4. **Outcome**: completed, partial or abandoned, and a 1–5 effectiveness score. Write the section even for a clean run.

## Step 6 — Memory (conditional)

Lessons that generalize go to auto-memory (one file per fact, plus its `MEMORY.md` line). Update an existing memory rather than duplicating one. Skip when nothing generalizes.

## Step 7 — Docs gates

```bash
composer run gate:19    # docs: dead relative links, (TODO) and (WIP) markers
composer run gate:22    # pipeline docs: frontmatter, links, spec body size
```

Fix the docs until both pass. `enforce-completion.sh` blocks Stop when the transcript shows either run missing.

## Step 8 — Update the pipeline doc

Notes' `## Phase 4: Documentation`: docs written, the changelog entry, the decisions recorded. Spec: `status: Phase 4 — Documentation PASS; ready for delivery`, `last_command: /pipeline:complete`, `next_step: Run /commit`.

## Step 9 — Hand off to /commit

Print the pipeline ID, the ticket, the files changed, the Phase 3 green summary line, the docs written and the AAR counts. Then invoke `/commit`: it commits, opens the PR, merges, archives the pipeline doc pair and closes the ticket. It runs no gates.
