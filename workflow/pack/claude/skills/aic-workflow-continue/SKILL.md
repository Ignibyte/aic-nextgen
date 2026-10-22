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
| 2 | (none) | Refused: the line says why and what to do. |

Each blocked row has a `check`, a `why` and a `remedy`. Fix the cause the `why` names; running
again without a change only repeats the verdict. A gate that fails at one phase more times than
the settings allow (3 unless the operator changed it) stops running, and the run then waits on
the operator. Read a failing gate's output in its row before you touch code.

The phases and their skills:

- plan: [aic-workflow-plan](../aic-workflow-plan/SKILL.md)
- code: [aic-workflow-code](../aic-workflow-code/SKILL.md)
- check: [aic-workflow-check](../aic-workflow-check/SKILL.md)
- complete: [aic-workflow-complete](../aic-workflow-complete/SKILL.md)

Your turn cannot end in the middle of a phase. A Stop hook holds it while the phase is open and
the run is not waiting on the operator, so keep working the blocked rows until the phase
advances. `bin/aic workflow:reset` abandons a run; it is the operator's, and the hook refuses it
from your shell.
