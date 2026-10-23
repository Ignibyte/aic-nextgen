## Building this app: the AIC workflow

Use the installed AIC workflow for every application change.
Read `{vendor}/aic/framework/docs/README.md` before you plan.
Use the phase skills in `.agents/skills/aic-workflow-*`.
Start with `$aic-workflow-start`.
Run `bin/aic workflow:status` before changing a file.
Use one ticket and one actual workflow run per feature.
Use the code index before declaring paths and classes.
Follow the engine's phase and blocked rows.
The engine owns the gate evidence.
Fix findings in source.
Do not add suppressions or baselines.
Do not edit installed packages or workflow control files.
The operator installs and reviews hooks between runs.

The operator runs `bin/aic workflow:codex-check` before a fresh Codex session.
Do not launch that child client from inside the tool sandbox.
The operator reviews exact hook definitions through Codex `/hooks`.
Do not use a hook-trust bypass or edit the trust store.
Start Codex at this project root.
Native shell hooks do not expose workdir or tty flags.
The owned runner starts every inspected command at this project root without interactive input.
Use an explicit `cd` in the command when you need another directory.
Do not use interactive shell sessions or later stdin for commands.

At `inspection-due`, dispatch the installed `aic-seeker` native role.
Give it only this request: `Inspect the open run.`
Wait for its actual return.
File its Seeker Inspection section unchanged through `bin/aic workflow:seeker-report`.
Do not create a substitute ledger.
Actual native identity and return events bind the review receipt.
Delivery belongs to the primary driver after the workflow completes.
