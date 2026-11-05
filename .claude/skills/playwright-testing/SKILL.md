---
name: playwright-testing
description: Look at AIC's rendered pages with Playwright during Code, and keep the looks worth keeping as browser tests in tests/browser/ (#174). Use when a `browser_testable: Yes` pipeline changes a page someone sees. Marley's Browser tab when Chad wants to watch.
---

# The browser look (AIC)

Anything front-end facing is looked at with Playwright (Chad, 2026-10-26, #174). A look is a Playwright test of the behaviour the change is for. The pipeline decides whether to keep it: a kept test stays in `tests/browser/` and gate:23 runs it against the compiled site on every gate run from then on. Browser tests are the one exception to "no tests" (CONSTITUTION.md §7).

## The tools

| Tool | Use |
|---|---|
| `npx playwright cli` (Playwright 1.62.1, `node_modules/` from `npm ci`) | explore a page from the terminal while writing the test |
| `tests/browser/<name>.spec.ts` | the look: `import { expect, test } from '@playwright/test'`, `page.goto('/path')`, `expect` assertions |
| `vendor/bin/aic-gate browser --base-url=https://aic-nextgen.ddev.site:8843 [<test>…]` | run looks against DDEV with ignibyte/aic-gates' config (`gates/browser/playwright.config.mjs`) |
| `composer run gate:23` | run every kept test against the compiled site, signed in as the gate user (`AIC_GATE_LOGIN`, `AIC_GATE_PASSWORD`) |
| Marley's Browser tab (`mcp__plugin_marley_marley__browser_*`) | when Chad watches |

`package.json` and `package-lock.json` at the root are ignibyte/aic-gates' stubs (gate:30 holds them); `npm ci` installs Playwright, and the Chromium it drives is in `~/.cache/ms-playwright`. `.mcp.json` is owner-managed: never write it.

## Where the app is

`https://aic-nextgen.ddev.site:8843`: the DDEV router's port on this box, with the mkcert certificate the system trusts. `aic-nextgen.ddev.site` resolves to 127.0.0.1. The compiled site is gate:23's own, on a free local port.

## The loop

1. **Serve the working tree**: `ddev start`; `ddev restart` when the worker must reload changed code.
2. **Write the look** at the path its Browser Check item names: what the item says should happen, as assertions. A test of a Tessera swap marks the window before the click and checks the mark after, so a full load fails it.
3. **Run it**: `vendor/bin/aic-gate browser --base-url=https://aic-nextgen.ddev.site:8843 tests/browser/<name>.spec.ts`. Fix the code, not the test, when the page is wrong.
4. **Prove it bites**: break what it checks once (a full load for a swap, a wrong label) and see it fail.
5. **Record**: tick the item with what you saw and, after `saw:`, `— keep` or `— drop`. Keep the tests that guard behaviour someone would miss; delete a dropped one.

`enforce-browser-checked.sh` blocks Stop in Code until this session ran Playwright (or drove a browser through MCP) and ticked every item. If the page cannot be reached, fix the reachability (DDEV, the router port), never the hook.
