# Workflow

`aic/workflow` is a development package. It installs an app's agent instructions, skills, review
role and guards, then runs the app's work through a durable engine. Runtime framework classes do
not depend on it. Read [conventions.md](conventions.md) and the relevant architecture pages before
planning; use the app's generated `docs/wiki/` for its module inventory.

## Install and start

Install the package through Composer and run `bin/aic workflow:init` from the app root. Review the
installed instructions, settings, hooks and lock, then commit them. Reinitializing updates unedited
managed blocks and preserves edited content according to the installer contract. Configure the
app's typed `config/workflow.php`. Init refuses in the package source repository.

For Codex, run `bin/aic workflow:codex-check` and review every readiness result before starting the
client. The installed adapter must be active, with the configured native seeker role available.
Use the installed start and phase skills. A missing adapter is a readiness failure, not permission
to run without the guard.

`bin/aic workflow:run <ticket>` advances one phase per invocation. `workflow:status` explains the
open run, phase, hold and next action. The engine's phase values are:

| Phase | Work |
|---|---|
| `Phase::Plan = plan` | goal, bounded file/entry-point contract, grounded in current code |
| `Phase::Code = code` | implementation, applicable live browser checks, gate evidence and advisory inspection |
| `Phase::Check = check` | required independent inspection and current evidence |
| `Phase::Complete = complete` | documentation and completion conditions, then archive |

The engine alone writes `var/workflow/`: run.json, append-only events.jsonl, history and gate
evidence in evidence.sqlite. Do not edit those files to skip a hold. A run keeps its identity and
judged source hash through recovery. The engine runs gates itself; an unrelated terminal run is
not the run's evidence.

## Guard, questions and review

The guard checks each write against the phase, exact File Manifest, protected paths, vendor wall
and real filesystem destination. A symlink cannot authorize a write outside the app. Declare a
needed path before editing it. The code index under `var/index/` is engine-owned too. Client hooks
cover supported shell, native patch and connected file operations; unsupported mutation routes
hold rather than assuming an unknown operation is safe.

House rules are frozen when the run begins and judge its actual change. A held rule names the
contract to fix. `workflow:ask` records an operator question; the operator answers from a terminal
with `workflow:answer`. In interactive mode phase advance can require an explicit operator answer;
agentic mode advances under the configured rules. Elapsed time is not an answer.

At inspection-due, dispatch the installed independent seeker role. Use `workflow:seeker-brief` for
the actual bounded review context and `workflow:seeker-report` to file its result. The code phase's
advisory inspection and the check phase's required decision are distinct. A self-review must not be
filed as independent approval. Changed source can make evidence or inspection stale; follow the
engine's next action rather than reusing a verdict for different bytes.

## Code index and scaffolds

Use `code:tree`, `code:search` and `code:refs` to inspect the area before adding or changing a class.
`code:index` refreshes the index; plan grounding rebuilds it and checks the run's recorded queries
against its File Manifest and Entry Points. Query each existing file/class being changed and the
area of a new file. `code:wiki` reads the generated module map. Inspect commands read the build's
surface inventory, rather than scanning runtime objects.

Make commands create authored files and edit the owning manifest through the same guard. They
record what was made. Declare the exact resulting paths in the open run first, run the build and
keep generated outputs with the authored change. [modules.md](modules.md#generate-theme-files)
covers component, page and theme generation.

## Command inventory

These are the actual development command registrations, checked against the package. The workflow
probe command is registered but refuses in an installed app: its replay fixtures are source-package
verification tooling. Other commands require their applicable config and open-run state.

| Command | Purpose |
|---|---|
| `Command::code:index` | refresh code index |
| `Command::code:refs` | references to a file or symbol |
| `Command::code:search` | search indexed code |
| `Command::code:tree` | inspect a module or area |
| `Command::code:wiki` | read generated wiki |
| `Command::command:list` | inspect console commands |
| `Command::job:list` | inspect jobs |
| `Command::kind:list` | inspect kinds |
| `Command::make:api` | scaffold an Api |
| `Command::make:block` | scaffold a Block |
| `Command::make:command` | scaffold a Command |
| `Command::make:component` | scaffold declared Component data and template |
| `Command::make:job` | scaffold a Job |
| `Command::make:migration` | scaffold a Migration |
| `Command::make:module` | scaffold a module |
| `Command::make:page` | scaffold a Page and declared component template |
| `Command::make:theme` | scaffold the site's selected theme |
| `Command::module:list` | inspect modules |
| `Command::module:show` | inspect a module's declarations |
| `Command::route:list` | inspect routes |
| `Command::schedule:list` | inspect schedules |
| `Command::workflow:answer` | operator response or phase decision |
| `Command::workflow:ask` | record a question hold |
| `Command::workflow:codex-check` | Codex readiness |
| `Command::workflow:init` | install or upgrade managed client files |
| `Command::workflow:logs` | inspect stored gate evidence |
| `Command::workflow:probe` | source-package replay only |
| `Command::workflow:reset` | operator-controlled run reset |
| `Command::workflow:run` | run or resume a phase |
| `Command::workflow:seeker-brief` | obtain the review brief |
| `Command::workflow:seeker-report` | file an actual inspection |
| `Command::workflow:status` | inspect run state |

## Evidence and recovery

`bin/aic workflow:logs gate:8` reads full stored output for the open or latest archived run. Select
`gate` for aggregate output, including abnormal execution. Use `--run=...`, `--phase=code`,
`--attempt=1`, `--from-line=1`, `--lines=100` and `--json` to select a precise stored record and
window. Reading diagnostics creates no database, migrates no schema, reruns no gate and does not
establish freshness. Capture provenance and selection completeness are separate; old truncated
output cannot become complete by requesting a larger window.

After a failed or interrupted suite, read its stored diagnostics first, fix the actual prerequisite
or source defect and resume the same run. A deadline or observation timeout does not prove a
process died. Verify the owned handle before restarting it, and reap owned children when execution
really stops. After prerequisite recovery, inspect the bounded brief at the next inspection-due
state. Use reset only for the operator's deliberate reset, never as a routine way around a hold.

Gate:28 checks the shipped house rules against their held/broken controls in the source package;
gate:29 checks these phase and command inventories there. Installed apps run the suite profile
in [gates.md](gates.md), including both full Psalm modes, native classes and the whole-site oracle.
A failure or deferral remains visible until the required evidence is current and passing.
