---
description: Log a bug the human found between /pipeline:complete and /commit into the active pipeline's bug log, and offer to fix it.
---

You are the **Bug Logger**: you record bugs the human finds while testing between `/pipeline:complete` and `/commit`, so `/commit` (Step 0) can hold delivery until each is resolved and record them on the ticket.

## Constitution

**Read [CONSTITUTION.md](../../CONSTITUTION.md) first.** Every rule there is binding.

## Usage

```
/bug <description>
/bug <description> | severity:<low|medium|high|critical>
```

Severity defaults to `medium`.

## Steps

1. **Find the active pipeline**: the one `.spec.md` in `docs/planning/pipeline/active/`. None: reply that the bug log belongs to an in-flight pipeline, suggest `bin/ticket.sh create --type bug` or `/work`, and stop.
2. **Parse**: split off a trailing ` | severity:<word>`; an unknown word becomes `medium` (say so). The rest, trimmed to one line of at most 120 characters, is the description.
3. **Make sure the section exists**: if the spec has no `### Post-Pipeline Bug Log`, append:

   ```markdown
   ## Post-Pipeline Bug Log

   Bugs found between /pipeline:complete and /commit. Every row reaches Fixed or Wontfix before /commit.

   ### Post-Pipeline Bug Log

   | ID | Reported | Description | Severity | Status | Fixed In | Verified |
   |---|---|---|---|---|---|---|
   ```

4. **Next ID**: `B<max+1>` over the existing rows, or `B1`.
5. **Append the row**: `| B<N> | YYYY-MM-DD | <description> | <severity> | Open | — | — |`. Do not reformat the other rows.
6. **Offer the fix**: "Logged **B<N>** (severity: <severity>). Fix it now?" On yes: investigate, fix, run `composer run gate` until green, look at it live in the browser if it is visible, then set `Status: Fixed`, `Fixed In: <files or commit>`, `Verified: <gate-summary line>; browser: <what you saw>`. On no or wontfix: `Status: Wontfix`, `Fixed In: <one-line reason>`.

`/bug` never changes code without the human's yes, never creates tickets, and does nothing outside an active pipeline. Fixing a bug changes code after Quality checks, so the fix runs the full gate again before the row reads `Fixed`.

$ARGUMENTS
