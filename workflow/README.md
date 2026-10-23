# AIC workflow

`aic/workflow` is a development package. It supplies the app's four-phase engine, code index,
scaffolds, independent review and client guards. Runtime code does not depend on it.

## Install in an app

Require the package for development, then select the client explicitly:

```sh
composer require --dev aic/workflow:0.1.x-dev
bin/aic workflow:init --client=codex
```

Omitting `--client` selects Claude Code. `--client=claude` is the explicit equivalent. Codex
installs `.agents/skills/`, `.codex/agents/aic-seeker.toml`, `.codex/hooks.json` and the committed
`.codex/aic-workflow.lock`. Both clients install the app's workflow settings and managed
instructions. Init preserves unrelated hooks and edited owned files. A regular rerun upgrades
unchanged owned files. The operator can force a reviewed owned file from a terminal; an open
run, malformed settings or an obstructing symlink still refuses before any write.

Commit the installed instructions, role, hooks, settings and lock. This maintainer repository
holds the package source, so init refuses here; its own ticket phases remain manual.

## Review and launch Codex

The operator reviews the project folder and exact callback definitions in Codex's supported
`/hooks` interface. Trust is tied to the current definitions. Do not bypass that review or edit
the trust store. Before launching a fresh session, run this from the operator terminal:

```sh
bin/aic workflow:codex-check
```

The check compares installed instructions and ownership with the package, then asks the real
client for enabled, synchronous, trusted callbacks with matching source, event, matcher,
command and timeout. Missing responses, configuration errors and stale controls hold launch.
A successful process exit without the requested protocol response is insufficient.

The check starts a finite child Codex process. Run it before entering the tool sandbox: the
child may need client-state writes that a workspace sandbox refuses. Its failure inside that
sandbox does not establish readiness. Restore or review the actual controls and rerun the
operator check; do not manufacture a receipt.

Start Codex at the app root. The chosen model and sandbox policy also apply to the native
seeker. Apps whose gates need advisory downloads or local Postgres need network access in
that workspace policy. Keep database credentials in the environment or the app's supported
local configuration, outside committed source.

Keep writable tool state inside the workspace too. The native gates honor
`ELEPHC_NATIVE_CACHE`; Semgrep has separate settings, log and version-cache paths. For a
workspace sandbox, set these in the operator terminal before launching Codex:

```sh
mkdir -p .native-gate/.cache/semgrep
export ELEPHC_NATIVE_CACHE="$PWD/.native-gate/.cache/elephc-native"
export SEMGREP_SETTINGS_FILE="$PWD/.native-gate/.cache/semgrep/settings.yml"
export SEMGREP_LOG_FILE="$PWD/.native-gate/.cache/semgrep/semgrep.log"
export SEMGREP_VERSION_CACHE_PATH="$PWD/.native-gate/.cache/semgrep/version-cache"
```

These are disposable tool caches, outside committed source. A gate failure caused by a
read-only external cache still holds the run. Repair the tool environment and resume that
same run; do not waive the gate or broaden filesystem permissions to hide the failure.

Use `$aic-workflow-start` and the installed phase skills. One feature uses one ticket and one
engine run. The engine runs and records its own gates. At `inspection-due`, dispatch the native
`aic-seeker` role with `Inspect the open run.`. File its returned Seeker Inspection section
unchanged. A parent-written, changed, stale or duplicate ledger does not replace that return.

## What the native guards judge

The adapter reads actual tool and lifecycle fields. It checks every add, update, delete and
move destination in a native patch before allowing the tool. Known MCP path fields pass
through the same phase, protected-path, vendor and real-destination walls. Unsupported local
mutation routes hold. Supported MCP file mutations are `write_file` and `edit_file` with
explicit non-empty paths; an unknown tool cannot gain permission by supplying a path.
A separate native `PermissionRequest` callback verifies the caller and
rechecks supported MCP scope before approving that request. It refuses shell escalation and
unknown tools. This project callback does not change global approval or sandbox settings.

Native Bash hooks omit the requested workdir and tty options. After checking the original
command, AIC replaces it with an owned runner that starts at the project root and gives its
child closed input and no tty. Use an explicit `cd` in the inspected command for another
directory. Later `write_stdin` input cannot supply the child's commands.

The parent session and actual child id/type bind native review calls to the current run.
The seeker's supported edit, shell-write and MCP-write routes refuse. This is workflow policy;
the role inherits the parent's client permissions and does not acquire a separate OS sandbox.

## Browser checks in Codex

Configure a trusted Playwright MCP server in the app's Codex configuration. Use an isolated
headless browser and a project-local output directory for its automatic logs and downloads.
Review that server configuration before launch. AIC inspects tool arguments, not the server's
internal implementation or every side effect of a page.

Structured page actions, snapshots and diagnostics are supported for the primary agent at
Check, with readable run state and no pending hold. Direct navigation URLs must use the
configured app origin. The callback does not inspect redirects or destinations reached by
page actions. It is not a browser network sandbox.

A screenshot requires an explicit absolute `filename`. Declare that artifact in the spec's
File Manifest. Every explicit browser artifact filename passes the existing protected,
phase, vendor and actual-destination checks. Relative and implicit screenshot destinations
are refused. Snapshot and diagnostic results can return directly without a filename.
Arbitrary JavaScript evaluation, unsafe Playwright code and file-transfer routes are held.
The independent seeker cannot use browser actions.

Use the real browser to perform each spec item and record what you observed. Tick an item
only after that action. The engine requires actual allowed browser calls at Check as well
as the checked items. A copied screenshot or HTTP request alone does not satisfy it.

## Client limits

Enabled trusted hooks are required for this integration. Codex can continue a tool call when a
callback cannot start, times out or returns malformed output. An explicit supported denial
blocks the call when the callback runs. AIC emits that denial for its handled tool failures and
records review returns only from valid lifecycle events; it cannot turn a client hook-startup
failure into an unconditional client refusal. The readiness check and protected controls do not
remove this client limit. See the [Codex hooks documentation](https://learn.chatgpt.com/docs/hooks).

The supported client is the local Codex CLI with the native hook and subagent capabilities.
Verify another client or execution route before relying on equivalent coverage. A tool's name
or a copied event alone is not evidence that a real callback ran.
