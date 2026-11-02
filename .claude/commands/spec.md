---
description: Batch-spec about 5 tickets (a count or ticket numbers override it) so each comes out ready to implement. Writes specs and ticket records, never code.
---

# /spec: batch spec-writer

You are the **Batch Spec-Writer**. Queue about five tickets (the ones just discussed, or the next on the roadmap), make sure each exists, and plan every one so a later pass can implement it without re-planning. Fan the tickets out to subagents. You write specs and ticket records only, never implementation code.

## Inputs: `$ARGUMENTS`

- A bare count replaces the default of 5: `/spec 3`.
- Ticket numbers or topics, comma-separated, fix the queue: `/spec 4, 5`.
- Empty: assemble the queue (Step 1).

## Step 1: Assemble the queue

In priority order, then deduplicate:

1. Whatever `$ARGUMENTS` names.
2. Tickets or topics discussed in this conversation that have no spec yet.
3. The roadmap: the milestones in `docs/planning/roadmap.md`, in order, and `bin/ticket.sh next`.

Deduplicate against the tickets first (`bin/ticket.sh list`, `grep -ril '<topic>' docs/planning/tickets/`); never create a second ticket for existing work. A ticket whose latest comment is a spec that still matches the code is already spec'd: skip it unless asked to refresh.

Show the queue (number, title, source, new or existing, already spec'd) before speccing anything.

## Step 2: Make sure each ticket exists

- Existing: `bin/ticket.sh show <n>`.
- New: `bin/ticket.sh create --title … --type … --tags … --description …` with a precise title.

## Step 3: Spec each ticket

Ground every spec in the code: read it, run it, pin every site as `path:line`. For a port, read the old-AIC source at `e0778896` and the port map in `docs/planning/plan.md`.

Give one subagent each ticket, with the rules below verbatim where they bind, then check each returned spec against the code and record it yourself.

### Framework tickets

The spec is a Plan (`/pipeline:plan` Steps 1–4): goal, approach (systems, layers, seams), file manifest, locked-in decisions, acceptance criteria, browser check if any, and what the native gate (gate:21) is expected to meet, with the elephc friction from the handoff that applies. Record it with `bin/ticket.sh comment <n>` and `update <n> --phase plan`, for a `/work <n>` pickup.

### Compiler tickets (the elephc fork)

The spec is the Plan of the carry workflow (the fork's `FORK.md`, the `elephc-carry` skill). Binding rules for the agents:

- One cargo command at a time on the box, none while a build or `just check` runs; speccing needs none.
- `../elephc` is read-only for speccing: `git show`, `git log`, `git grep`.
- Run a compiler binary in place, never a copy (the bridge archives sit beside it). Each compile gets its own runtime cache (`export XDG_CACHE_HOME="$PWD/.cache"`); leave `TMPDIR` alone.
- No ticket edits, no commits, nothing posted on GitHub. The agent writes its spec to the session scratchpad and reports back in under 15 lines.

The spec: the repro program with php's output and the native one; the cause at `src/…:line`; the approach; the tests in upstream's layout and which fail without the fix; `upstream: candidate` or `carry only`, with the reason; a size; acceptance as programs whose output must match php byte for byte. State plainly that the native gate's `ok` mains prove only that a program compiles; the programs are the proof.

## Step 4: Record and report

Commit and push the ticket records in `docs/planning`. Report a table: `# | title | new/existing | spec recorded | size | ready for`, and say plainly that only specs were written, no code.

## Hard rules

- No implementation code.
- Ground every spec in real code, sites pinned as `path:line`.
- Never create a duplicate ticket.

$ARGUMENTS
