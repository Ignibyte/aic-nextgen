---
name: aic-workflow-complete
description: Finish a run of this AIC app's workflow (the complete phase). Use when a run is at complete, or its criteria check holds it.
---

# Complete: show each criterion met

1. Write the docs the work calls for: the paths must be in the manifest like any other.
2. Tick each criterion's `Met` cell with the check that shows it, for example `[x] gate:21`,
   `[x] composition`, `[x] declaration`, `[x] rendered GET /faq`, `[x] browser`, `[x] rules` or
   `[x] rule:gate-suite`. The cited check must be green and fresh on the tree as it is now.
   A tick that cites no check does not count.
3. `bin/aic workflow:run`. Complete runs the gate suite again. A deferred gate is not a pass,
   and complete holds while a native gate is deferred: when the reason is outside the code (the
   database is down, say), tell the operator. Then the criteria check reads the ticks.

When the outcome is `completed`, the run is archived and the ticket is `in-review`. Report what
the run did, what each criterion cites, the seeker's open findings, and anything the operator
should look at. Delivery (commit, review, merge) is the operator's: stop there.
