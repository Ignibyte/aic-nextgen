---
description: Delivery at the end of Phase 4. Commit, PR, merge, archive the pipeline docs, close the ticket. Runs no gates.
---

You are the **Delivery step**: the end of Phase 4 (Documentation).

This command builds the commit, opens the PR, merges it, archives the pipeline doc pair and closes the ticket. **It runs no gates.** Quality checks ran the full suite to green and Documentation ran the docs gates; nothing after them touches code, so there is nothing to re-check.

## Step 0: Bug log

If the active spec has a `### Post-Pipeline Bug Log` (written by `/bug`), every row must read `Fixed` or `Wontfix`. An open row goes back through `/pipeline:implement` → `/pipeline:check`.

## Step 1: Confirm the evidence

The spec `status` shows `Phase 4 — Documentation PASS`, and the notes' Phase 3 entry ends with the green `gate-summary:` line. If either is missing, stop and send the work back to the phase that owes it.

## Step 2: Review the changes

```bash
git status --short
git diff --stat HEAD
```

Present a categorized summary (framework, app, gates, configs, docs, changelog). `.mcp.json` (owner-managed), `.claude/settings.local.json` and other local state are never staged.

## Step 3: Commit

Stage explicit paths, never `git add -A`. A Conventional Commits subject of at most 72 characters (the captainhook `commit-msg` hook enforces it):

```bash
git commit -F - <<'COMMITEOF'
<type>(<scope>): <imperative title> (#<ticket>)

<what changed and why>

Pipeline: <pipeline ID>
Ticket: #<N>

<the attribution line the session specifies>
COMMITEOF
```

## Step 4: Branch and push

Never push a feature commit to `main`. On `main`: `git switch -c <feat|fix|chore|docs>/<slug>-<ticket>`, then `git push -u origin <branch>`.

## Step 5: Pull request

`gh pr create --title "<type>(<scope>): <title> (#<ticket>)" --body-file <body>`. Body: a one-to-three bullet summary; the pipeline ID, the ticket and the phases; the green gate-summary line from Phase 3; the browser check record for browser-testable work; the attribution line the session specifies.

## Step 6: Merge

Claude merges its own PRs once the work is done and checked (CLAUDE.md, Delivery):

```bash
gh pr merge <n> --squash --delete-branch
git switch main && git pull --ff-only && git fetch --prune
git branch -a          # only main is left, locally and on origin
```

A merge right after a push can be refused as not mergeable while GitHub recomputes the PR; wait a few seconds and retry.

## Step 7: Archive the pipeline doc pair

In `docs/planning`: move `WORK-<Title>.spec.md` and `.notes.md` from `pipeline/active/` to `pipeline/completed/`, commit `docs: archive WORK-<Title> (#<N>)` and push to its `main`. `enforce-pipeline-completion.sh` blocks Stop while a delivered doc sits in `active/`.

## Step 8: Close the ticket

`bin/ticket.sh close <N> --note "Delivered via aic-nextgen PR #<PR> (<hash>), merged; branch deleted. <one-line summary>."` Record each bug-log row as a ticket comment first. Commit and push in `docs/planning`.

## Step 9: Report

The commit hash and title; the PR number; the Phase 3 gate-summary line; the pipeline docs archived; the ticket closed.

## Hard rules

- Never push a feature commit to `main`.
- Never commit code from this step. A code change goes back through `/pipeline:check`.
- Never stage `.mcp.json`; never `git stash -u` (it takes `.mcp.json` with it).

$ARGUMENTS
