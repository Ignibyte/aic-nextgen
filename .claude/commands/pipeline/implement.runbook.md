---
spec: implement.md
loaded_on: demand
purpose: Detailed Phase 2 (Code) procedures — where code goes, keeping touched files clean, the live browser check, package safety, pipeline-doc update.
---

# /pipeline:implement — Runbook

## Step 1 — Write the code

- Framework code lives under `framework/src/<System>/{Contracts,Domain,Exception,Attribute,Application,Infrastructure}/` (`AIC\<System>\…`), the layer ladder Deptrac checks (gate:12). The demo app is modules under `modules/<name>/` (`App\<Name>\`), wired by the generated classes in `generated/` (`App\Generated\`). Gate tooling lives under `gates/` (`Gates\`) and never ships.
- Every file: `declare(strict_types=1);`, one class per file, a class docblock and full method docblocks, everything typed, `final` (or `abstract` by design), `readonly` unless mutation is essential, no `mixed`, `array<K, V>` and `list<T>` in phpdoc, no static state, no `new` in business logic (CONSTITUTION.md §14).
- Every entry point is one of the five kinds (Page, Api, Stream, Command, Job; CLAUDE.md).
- Every runtime class compiles natively (CONSTITUTION.md §3): typed properties and small value objects over loose arrays (elephc ignores a plain `array<K, V>` docblock), literal `require` paths, no runtime reflection on dynamic class names, no `eval`.

## Step 2 — Keep touched files clean

```bash
vendor/bin/pint <files>
vendor/bin/phpstan analyse --memory-limit=2G
composer run gate:21                  # the native gate, for runtime classes
```

The Stop hooks (`enforce-quality.sh`, `enforce-sast.sh`, `enforce-secrets.sh`) block Stop while changed files fail Pint, PHPStan, Rector, PHPCS, PHPMD, Deptrac, semgrep or gitleaks. They do not replace Phase 3: whole-scope findings show up only in the full run.

When elephc rejects or miscompiles legal PHP, restructure the AIC code first. A real compiler defect becomes a carry (the `elephc-carry` skill) with its own ticket.

## Step 3 — Browser check (`browser_testable: Yes`)

1. Make the served app reflect the change: `ddev start` (or `ddev restart` when the worker must reload), at `https://aic-nextgen.ddev.site:8843`.
2. For each item: navigate to the URL, perform the action, then look. Use Marley's Browser tab (`browser_navigate`, `browser_snapshot`, `browser_look`) when Chad is watching, the local Playwright MCP (`browser_navigate`, `browser_snapshot`, `browser_take_screenshot`) when he is not. See the `playwright-testing` skill.
3. Compare with the expected result; if it is wrong, fix the code and look again.
4. Tick the item (`- [x]`) and add one line per item to the notes' browser check record: page, action, what you saw.

## Step 4 — Package safety

`git status --short` shows no change under `vendor/`. If a tool touched `vendor/`, revert it and stop.

## Step 5 — Pipeline doc

Notes' `## Phase 2: Code`: what was built, every deviation from the File Manifest with its reason, failures and fixes, the browser check record. Spec: tick the acceptance criteria now met; `status: Phase 2 — Code PASS; ready for Phase 3 — Quality checks`, `last_command: /pipeline:implement`, `next_step: Run /pipeline:check`.
