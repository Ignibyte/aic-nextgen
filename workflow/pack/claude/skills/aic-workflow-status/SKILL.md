---
name: aic-workflow-status
description: Read where this AIC app's workflow run stands. Use to find whether a run is open, which phase it is at, what held its last attempt, or whether it waits on the operator.
---

# Read the run

`bin/aic workflow:status` prints the open run as JSON, or the last archived run when none is
open (`run` says `open` or `archived`). With no run at all it says so and names the command that
starts one.

- `phases` maps each phase to `pending`, `active` or `passed`; `current_phase` is the one being
  worked, or null once the run is done.
- `reports` holds each phase's last report: its attempt and its rows, each with a `check`, a
  `status`, a `why` and a `remedy`.
- `attempts` counts the calls each phase took. `notes` records what the run noted without
  failing on it, such as a ticket file it could not move.
- A row whose status is `held` waits on the operator. Only they clear it, from a terminal: tell
  them what it says and stop.

The run's state lives in `var/workflow/`, which only the engine writes. Read it through this
command, never by editing the files there. To go on, use the
[continue skill](../aic-workflow-continue/SKILL.md).
