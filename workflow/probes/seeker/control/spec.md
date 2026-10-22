---
ticket: 1
browser_testable: No
---

## Acceptance Criteria

| # | Target | Met |
|---|---|---|
| 1 | The FAQ's second answer names generated/ as where the build writes the wiring | [ ] |

## Design

One string in the demo FAQ entries changes; the page and the API read it as before.

### File Manifest

| File | Change | Purpose |
|---|---|---|
| `modules/faq/src/Service/DemoFaqEntries.php` | modify | The answer names generated/ |

### Locked-In Decisions

1. Only the answer's text changes.

## Entry Points

| Kind | Method | Path or name | Class | Module | Status | Example |
|---|---|---|---|---|---|---|
