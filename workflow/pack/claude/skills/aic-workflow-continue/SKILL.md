---
name: aic-workflow-continue
description: Work an open run of this AIC app's workflow to its end. Use when a run is open, after writing a spec or code, or when bin/aic workflow:run reported blocked rows.
---

# Work the run

`bin/aic workflow:run` (no number while a run is open) works the current phase once: it runs that
phase's checks and advances when every check passes. Its exit code and envelope say what
happened. At code, check and complete a call runs the whole gate suite and can take several
minutes: give the command a timeout of ten minutes, and wait for it.

| Exit | `outcome` | What to do |
|---|---|---|
| 0 | `advanced` | `current_phase` is the next phase: follow its skill. |
| 0 | `completed` | The run is archived and the ticket is `in-review`. Report and stop. |
| 1 | `blocked` | Read each row of `blocked`, fix what it names, run again. |
| 1 | `held` | The run waits on the operator. Stop and tell them what holds it. |
| 1 | `inspection-due` | Only the seeker's inspection holds the phase: dispatch it as the `seeker` row says. |
| 1 | `paused` | The phase passed and waits for the operator to confirm its exit. Stop and tell them the question; they answer from a terminal. |
| 2 | (none) | Refused: the line says why and what to do. |

Each blocked row has a `check`, a `why` and a `remedy`. Fix the cause the `why` names; running
again without a change only repeats the verdict, and a row that repeats with nothing changed
becomes the operator's: the run then waits on them (`held`). A gate that fails at one phase more times than
the settings allow (3 unless the operator changed it) stops running, and the run then waits on
the operator. Read a failed gate through `bin/aic workflow:logs gate:<N>` before changing code.
Use `--phase=<phase> --attempt=<N>` for a selected attempt.
Use `--json` for the exact stored output and its metadata.
Use `--from-line=1 --lines=100` for an explicit line window with continuation metadata.
Use `bin/aic workflow:logs gate` for aggregate failures or timeouts.
Never rerun a gate only to read its previous output. Never query the protected database directly.

A pending seeker beside failed prerequisites is not an actionable inspection.
After recovery, the first `inspection-due` report permits dispatch.
An unchanged repeat of that actionable report still causes an operator hold.

The phases and their skills:

- plan: [aic-workflow-plan](../aic-workflow-plan/SKILL.md)
- code: [aic-workflow-code](../aic-workflow-code/SKILL.md)
- check: [aic-workflow-check](../aic-workflow-check/SKILL.md)
- complete: [aic-workflow-complete](../aic-workflow-complete/SKILL.md)

Your turn cannot end in the middle of a phase. A Stop hook holds it while the phase is open and
the run waits on nothing, so keep working the blocked rows until the phase advances.

When a row needs something only the operator can give (a browser tool, a decision, access), ask:
`bin/aic workflow:ask "<question>"`, then end your turn. While the question waits, every write in
the project is refused and `workflow:run` is refused; the Stop hook lets the turn end. The
operator answers from a terminal; read the answer as the last `qa_history` entry in
`bin/aic workflow:status`, then carry on. One question at a time. `bin/aic workflow:reset` abandons a run, `bin/aic workflow:answer` answers a question and
`bin/aic workflow:amend` fixes a frozen spec row; all three are the operator's, and the hook refuses them from your shell. So does php that loads the project's
autoloader or names the workflow package: run `bin/aic`, `composer run` and `php -l` instead.
