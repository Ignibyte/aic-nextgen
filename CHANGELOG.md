# Changelog

Every delivered pipeline adds an entry here with its ticket number (`/pipeline:complete`). Tickets live in the private planning repo; old AIC's are cited as "AIC #N".

## Unreleased

### Added

- **The four-phase workflow** (#3). `CONSTITUTION.md`, trimmed to one package. The commands `/work`, `/work-lite`, `/pipeline:plan|implement|check|complete`, `/commit`, `/spec`, `/scope-split`, `/bug` and `/qa`. The hooks for the phase gate, write scope, docs before code, MCP config, phase evidence, pipeline completion, browser check, quality, SAST and secrets, wired in `.claude/settings.json`. The skills `elephc-carry` and `playwright-testing`. gate:22, the pipeline-doc sync. The pipeline templates live in the planning repo.
- **The native gate, gate:21** (#14). Every runtime class under `framework/src` and `app` is compiled, linked and run with the fork's elephc, and its output compared with PHP's. The first runtime class is `AIC\Routing\Domain\Method`.
- **The gate suite** (#2). `composer run gate` runs 21 gates (gate:0 to gate:20) on the host, and `bin/gate-runner.sh` reports every failure in one run. The gate code is dev-only, in `gates/` (`Gates\`). captainhook installs the git hooks.
- **The workspace skeleton** (#1). `aic/app` at the root, `aic/framework` in `framework/` through a path repository, the FrankenPHP worker `public/worker.php`, DDEV with FrankenPHP and Postgres 17, and the build step `bin/aic-build`.

### Fixed

- **Hook project root** (#3, found live). The hooks take the root from `$CLAUDE_PROJECT_DIR`, falling back to the git root. Starting from the session's working directory found the nested planning repo whenever the shell sat in `docs/planning`, and the MCP-config hook then blocked every write.
- **Hook command detection** (#3). Old AIC's hooks recognized only Skill-tool invocations of the workflow commands. A command the user typed is recorded as `<command-name>/…</command-name>`, so it never made a session a pipeline session.
- **Two hook checks that never ran** (#3). The SAST hook's semgrep check looked for `.semgrep.yml` and the quality hook's PHPCS check for `phpcs.xml`; the real files are `semgrep.yml` and `phpcs.xml.dist`.
- **Two template defects** (#3):
  - The pipeline spec template started at `Phase 1 — Plan PASS`, so an unconfirmed plan passed the phase gate.
  - The notes template carried an empty `## AAR` heading, so an AAR that was never written passed the completion hook.
- **phpat rules that never ran** (#2, #14). phpat runs only rule classes registered with the `phpat.test` tag, and old AIC registered none. Built-in classes now count as dependencies, so the no-Swoole rule can fail.
- **Git hooks on old commits** (#2). Only the enabled git hooks are installed. A disabled hook still ran captainhook, which failed on any checkout older than `captainhook.json`.
- **`bin/ticket.sh` fixes** (#1):
  - JSON escapes are kept on update.
  - File modes are kept on every write.
  - A partly invalid update is refused instead of half-applied.
  - Phases are validated, and a reopened ticket drops its `closed` date.
