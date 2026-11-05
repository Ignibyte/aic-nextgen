---
name: aic-workflow-check
description: Pass the check phase of a run of this AIC app's workflow. Use when a run is at check, or the rendered or browser check holds it.
---

# Check: the app answers as declared

Check's exit runs code's checks again (the gate runs again only when the tree changed since its
last green verdict), then two of its own:

- **Rendered.** The engine requests each GET route the spec declares from the site in
  `config/workflow.php` and holds it to the declared status (200 unless declared otherwise) and
  its kind's content type (a Page's HTML, an Api's JSON or XML). When the setting is empty,
  the row is `held` and the run waits on the operator: the site and its setting are theirs.
  Tell them, and stop; your turn may end.
  A route that answers 500 is a bug to fix in code; the row quotes the status.
- **Browser.** When the spec names looks. Explore the page with the Playwright CLI
  (`npx playwright cli`), then write each look as a Playwright test at the path its item names:
  `import { expect, test } from '@playwright/test'`, `page.goto('/path')` relative to the site, the
  behaviour the item describes as `expect` assertions. A signed-in test reads `AIC_GATE_LOGIN` and
  `AIC_GATE_PASSWORD`. Run it with `vendor/bin/aic-gate browser --base-url=<site> <test>`, then tick
  the item with what you saw: `- [x] tests/browser/<name>.spec.ts — <what it shows> — saw: <what
  showed>`. The engine runs every look itself; the row holds while an item is unticked, a test is
  missing or a look fails. When Playwright is missing, ask the operator to run
  `vendor/bin/aic-gate tools`.

You may still change code at check; the manifest still bounds what you write. Then
`bin/aic workflow:run`. When check passes, go to the
[complete skill](../aic-workflow-complete/SKILL.md).
