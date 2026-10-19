---
name: elephc-carry
description: Fix a bug in the elephc compiler fork (Ignibyte/aic_compiler) as a carry commit, through Plan, Code, Quality checks and Documentation with the bundler's `just` recipes; only solid, PHP-generic fixes also go upstream to illegalstudio/elephc. Use when AIC runtime code fails the native gate (gate:21) because elephc gets legal PHP wrong, and for compiler tickets.
---

# Compiler carry fix (elephc fork)

AIC's own workflow covers the PHP framework and writes no tests. A compiler fix follows the compiler's version of the same four phases, defined in the fork's `FORK.md` ("Carry workflow"); that file is the source, this skill is the checklist. First restructure the AIC code when a construct cannot be compiled ahead of time by nature (a runtime-path `require`, dynamic reflection); a carry is for elephc rejecting or miscompiling legal PHP (CONSTITUTION.md §3). Compiler fixes do get tests, in upstream's layout, so each sync can tell when plain upstream covers a carry and it can be dropped.

Places:

- Compiler checkout: `/srv/stacks/elephc` (`origin` = Ignibyte/aic_compiler, `upstream` = illegalstudio/elephc, `chadmandoo` = the owner's public fork, used for upstream PRs).
- Checks: `/srv/stacks/elephc-bundler/justfile`: `just -f /srv/stacks/elephc-bundler/justfile <recipe>`.
- Upstream rules: `AGENTS.md` and `CONTRIBUTING.md` in the compiler checkout. Read them before the first commit of a session.
- History: old AIC's carry inventory (`/srv/stacks/aic/docs/planning/elephc-upstream-sync/carry-inventory.md`) and native roadmap (`/srv/stacks/aic/docs/planning/native-compilation-roadmap.md`), read-only; the friction table in `docs/planning/handoff.md`.

## 1. Plan

1. `git fetch upstream`. Reproduce the bug on plain upstream: a minimal PHP program, its `php` output, the native output. A failure seen only with forced lowering (the native gate's guarded `eval`) must also reproduce without it.
2. Find the cause: the file and function, and why it is wrong. `--emit-ir --ir-opt=off` shows the EIR. If an old-fork commit already fixed it (the carry inventory; `git show <sha>` in the compiler checkout), re-porting that approach is the default.
3. Search upstream's open pull requests and issues for the same bug (`gh pr list --repo illegalstudio/elephc --state open --search '<words>'`, and `gh issue list` the same way). If someone already has a PR, carry ours only until theirs merges, and don't open a second one.
4. Post the repro, cause and approach as a comment on the AIC ticket (`bin/ticket.sh comment <N>`; create one for the carry if none exists), then wait for the owner's OK.

## 2. Code

1. `git switch -c fix/<slug> upstream/main`, or from `origin/main` when the fix builds on another carry. One fix per branch.
2. Write the fix, following upstream's conventions. Write a regression test in their layout (`tests/codegen/<area>/`, `compile_and_run`; `compile_and_run_with_heap_debug` plus a clean leak summary when ownership is involved), with the `//!` preamble and a `///` doc on every function.
3. Iterate with `just test <filter>`.
4. Commit in upstream's style: `fix: <what>`, short, no `Co-Authored-By` line, no `CHANGELOG.md` entry. Update `docs/internals/` in the same commit when a page there no longer matches.
5. `just bite <filter>`: reverts `src/` and fails unless at least one new test fails.

## 3. Quality checks

`just check` until it prints `check: PASS`, fixing at the source between runs. It reports the warning-free debug build, `diff --check`, the survey and battery ledgers against the upstream base (`broke` rows fail), and the full suite (each failure rerun alone and labelled `flake`, `env` or `FAIL`). A run takes about an hour; run it in the background and do not touch the compiler checkout meanwhile (it builds from the working tree). Then rerun AIC's native gate: `composer run gate:21`.

## 4. Documentation and delivery

1. PR the branch into `Ignibyte/aic_compiler` `main` and merge it with `--merge`, never a squash, so the fork holds the commit upstream gets.
2. Only when the fix is solid (`check: PASS`, a byte-parity program) and about PHP rather than shaped around AIC, one narrow fix per PR, and with the owner's OK for this change: `git push chadmandoo fix/<slug>`, then `gh pr create --repo illegalstudio/elephc --head chadmandoo:fix/<slug>`. Say it was verified on Linux x86_64 only.
3. Comment the outcome on the AIC ticket and push the ticket in `docs/planning`.
4. `just record <sha>` when the run will be a baseline; commit new ledgers in the bundler.

## Rules

- Run the suite through `just suite` or `just check`, never a bare `cargo test`.
- One cargo command at a time, and never kill a running one. Never run `cargo fmt`: it reformats the whole repository.
- Never post on `illegalstudio/elephc` without the owner's OK for that specific change.
- This box is x86_64 only. A change to assembly or ABI code ships with the aarch64 side written but unverified, and the PR says so.
- Keep `TMPDIR` unchanged for elephc: the native package fingerprint includes it.
- Report in three bullets: what landed, what failed or is left, links to the PRs.
