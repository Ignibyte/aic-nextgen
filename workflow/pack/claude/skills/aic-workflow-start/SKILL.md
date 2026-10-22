---
name: aic-workflow-start
description: Start or resume work on this AIC app through its gated workflow. Use before any change to the app (a module, config, a template, an asset), when asked to build, fix or change something, or when a ticket is named.
---

# Start a run

Every change to this app goes through a run: one ticket, four phases (plan, code, check,
complete), held by the engine's checks and by a hook on your tools. With no run open, the hook
lets you write only in the ticket directory.

1. `bin/aic workflow:status`. When it shows `"run": "open"`, that run is yours to finish: go to
   the [continue skill](../aic-workflow-continue/SKILL.md). There is one run at a time.
2. Find the ticket in `tickets/`. When the work has none, write one there: the next free number,
   status `open`, the format in `tickets/README.md`. Say in it what done looks like.
3. `bin/aic workflow:run <number>`. The run begins at plan, and the envelope reports the spec as
   not written yet. That is expected.
4. Write the spec with the [plan skill](../aic-workflow-plan/SKILL.md).

Do not edit the app before the run reaches code: the hook refuses it, and a refusal is not
something to work around. Do not run the gates to judge your own work: the engine runs them at
each phase's exit.
