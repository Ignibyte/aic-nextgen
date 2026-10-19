---
name: playwright-testing
description: Check AIC's rendered pages live in a browser on this box during Code — Marley's Browser tab when Chad watches, the local Playwright MCP when he does not. Use when a `browser_testable: Yes` pipeline changes a page someone sees. NOT for writing @playwright/test specs — AIC has no regression suites.
---

# The live browser check (AIC)

AIC checks rendered pages by driving a real browser once, during Code, and recording what it saw (CONSTITUTION.md §17). There are no `@playwright/test` specs and no regression suite; keep `playwright.config.*` and any `package.json` out of the repo root.

## Which browser

Both run on this box; the Mac mini's Playwright is not used (since 2026-10-19).

| Browser | Tools | Use when |
|---|---|---|
| Marley's Browser tab | `mcp__plugin_marley_marley__browser_navigate`, `browser_snapshot`, `browser_look`, `browser_click`, `browser_type`, … | Chad is watching |
| Local Playwright MCP (headless Chrome) | `mcp__playwright__browser_navigate`, `browser_snapshot`, `browser_take_screenshot`, `browser_click`, … | Chad is not watching |

`.mcp.json` declares the local Playwright server; it is owner-managed, so never write it. Its page snapshots land in `.playwright-mcp/` (gitignored).

## Where the app is

`https://aic-nextgen.ddev.site:8843`: the DDEV router's port on this box, which serves the mkcert certificate the system trusts. The host nginx also forwards `*.ddev.site` on 443, but with a self-signed certificate that headless Chrome rejects. `aic-nextgen.ddev.site` resolves to 127.0.0.1.

## The check loop

1. **Serve the working tree**: `ddev start`; `ddev restart` when the worker must reload changed code.
2. **Navigate** to the Browser Check item's URL.
3. **Act** as the item says: click, type, fill a form.
4. **Look**: a snapshot (the accessibility tree, best for deciding), and a screenshot or `browser_look` when the appearance matters. Check the console and network for errors and 5xx responses.
5. **Judge and fix**: if it is not what the item expects, fix the code and look again.
6. **Record**: tick the item (`- [x]`) in the spec and add one line to the notes' browser check record: page, action, what you saw.

`enforce-browser-checked.sh` blocks Stop in Code until this session navigated, looked, and ticked every item. If the page cannot be reached, fix the reachability (DDEV, the router port), never the hook.
