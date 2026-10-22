---
name: aic-workflow-check
description: Pass the check phase of a run of this AIC app's workflow. Use when a run is at check, or the rendered or browser check holds it.
---

# Check: the app answers as declared

Check's exit runs code's checks again (the gate runs again only when the tree changed since its
last green verdict), then two of its own:

- **Rendered.** The engine requests each GET route the spec declares from the site in
  `config/workflow.php` and holds it to the declared status (200 unless declared otherwise) and
  its kind's content type. When the setting is empty, the row is `held` and the run waits on
  the operator: the site and its setting are theirs. Tell them, and stop; your turn may end.
  A route that answers 500 is a bug to fix in code; the row quotes the status.
- **Browser.** Only when the spec says `browser_testable: Yes`. Open each Browser Check item's
  URL in a browser tool, do what it says, look, and tick the item with what you saw:
  `- [x] <URL> — <action> → <expected> — saw: <what showed>`. The check holds until every item
  is ticked and the hook has seen a browser tool call during check. A session with no browser
  tool cannot pass it: ask the operator with `bin/aic workflow:ask "<question>"` and end your
  turn.

You may still change code at check; the manifest still bounds what you write. Then
`bin/aic workflow:run`. When check passes, go to the
[complete skill](../aic-workflow-complete/SKILL.md).
