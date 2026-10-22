## Building this app: the AIC workflow

This app is built through the AIC workflow (`aic/workflow`). Building means any change to the
app: its modules, config, templates, assets, or anything else a ticket asks for. A hook holds you
to the workflow. With no run open you can write only in the ticket directory; during a run, only
the run's spec and the paths its file manifest declares.

1. Every change starts from a ticket in `tickets/`. Its format is in `tickets/README.md`; when the
   work has no ticket, write one.
2. Start the run with `bin/aic workflow:run <number>`, then call `bin/aic workflow:run` once per
   step. Each call works the current phase (plan, code, check, complete) and prints a JSON
   envelope; its `blocked` rows say what holds the phase and what to do about it.
3. The engine runs the gates, and its verdict is the only one that counts. Never change a gate or
   a tool's configuration, never add a baseline, never suppress a finding: fix the code.
4. Read `{vendor}/aic/framework/docs/README.md` before you plan, then the pages it lists for
   the area the ticket touches: the kinds, the modules and their manifests, the systems, the
   conventions and the gates.
5. Ask the code index before you name a class or a path: `bin/aic code:tree [<module>]`,
   `bin/aic code:search <text>`, `bin/aic code:refs <name>`. Plan's exit reads these lookups.
6. `bin/aic workflow:reset` and `bin/aic workflow:init --force` are the operator's. When a run
   waits on the operator, stop and tell them what holds it.

Each phase has a skill in `.claude/skills/aic-workflow-*`; `/aic:workflow:start` begins.
