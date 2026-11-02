---
spec: plan.md
loaded_on: demand
purpose: Detailed Phase 1 (Plan) procedures — reading the surface, the design section of the spec, the browser check list, ticket operations, pipeline-doc instantiation from _templates/.
---

# /pipeline:plan — Runbook

Phase 1 produces a spec Code can build from without asking anything.

## Step 1 — Parse the request

Restate the goal in one sentence: what is different for the project once this ships. If the request is ambiguous in a way that changes what gets built, ask before designing. If it only changes details, pick a sensible default and record it as a locked-in decision.

## Step 2 — Read the surface

- The files the change touches, and the system that already solves the same shape: mirror its structure.
- A port: the old-AIC class at `e0778896` (`git -C ../aic show e0778896:<path>`) and the port map in `docs/planning/plan.md`. Cut every edge to a removed system.
- Precedent: `docs/planning/pipeline/completed/` and the tickets (`grep -ril '<topic>' docs/planning/tickets/`).
- A sweep over more than three candidate paths goes to an Explore subagent (CONSTITUTION.md §18.2).

## Step 3 — Design

Fill the spec's `## Design` section:

- **Approach**: how it works (classes, the system and layer each sits in, the seam it plugs into, what it reuses). Code should not need to invent structure.
- **File Manifest**: one row per file to create, modify or delete, with its purpose. The phase gate refuses Code while the manifest has no file row.
- **Locked-In Decisions**: what the human should agree to before code exists (names, public API, config keys, trade-offs).

CONSTITUTION.md §14 binds the design: strict types, final by default, PSR interfaces at every seam, no `new` in business logic, per-request state only in `RequestScopeInterface`. §3 binds it too: every runtime class must compile natively (gate:21), so prefer typed properties and small value objects to loose arrays, literal `require` paths, and build-time manifests over runtime reflection.

## Step 4 — Browser check

Set `browser_testable: Yes` when the change renders something a person sees. List the checks, one page, one action and one expected result each:

```
- [ ] https://aic-nextgen.ddev.site:8843/ — open the page → the placeholder shows "Worker mode: yes"
```

Code ticks them after looking at the live page. There are no regression specs. With no visible surface, set `browser_testable: No` and delete the section.

## Step 5 — Ticket

- Continuing a ticket: `bin/ticket.sh comment <n>` with the plan summary, `bin/ticket.sh update <n> --status in-progress --phase plan`.
- New work: `bin/ticket.sh create --title "…" --type feature|bug|chore --tags … --description "…"`.

Ticket changes are committed and pushed in `docs/planning`, not in the code repo.

## Step 6 — Pipeline document pair

1. `python3 -c "import uuid; print(uuid.uuid4())"` for `pipeline_id`.
2. Copy `docs/planning/pipeline/_templates/WORK-template.spec.md` and `.notes.md` to `docs/planning/pipeline/active/WORK-<Title>.spec.md` and `.notes.md`.
3. Fill the frontmatter (`pipeline_id`, `ticket`, `title`, `goal`, `browser_testable`, `last_updated`), the acceptance criteria table, the design and the browser check. Keep the spec body at or under 80 lines (gate:22); longer rationale goes in the notes.
4. Write the notes' Phase 1 entry: why this work, why this approach.
5. Leave `status` at the template value until confirmation.

## Step 7 — Confirmation

Present the goal, the approach, the file manifest, the browser check (if any) and the next step (`/pipeline:implement`). On confirmation set `status: Phase 1 — Plan PASS; ready for Phase 2 — Code`: that line is what `enforce-phase-gate.sh` reads before it lets Code write a file.
