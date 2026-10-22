---
ticket: 1
browser_testable: No
---

## Acceptance Criteria

| # | Target | Met |
|---|---|---|
| 1 | The FAQ page says how many questions it answers, above them | [ ] |
| 2 | CHANGELOG.md has an entry for it | [ ] |

## Design

The FAQ page counts the entries it already reads and shows the count above the list.

### File Manifest

| File | Change | Purpose |
|---|---|---|
| `modules/faq/src/Component/FaqCount.php` | create | The count |
| `modules/faq/src/Component/FaqCountTemplate.php` | create | Its compiled template |
| `modules/faq/templates/components/FaqCount.html.php` | create | Its template |
| `modules/faq/src/Controller/FaqPage.php` | modify | Shows the count |
| `CHANGELOG.md` | modify | The entry |

### Locked-In Decisions

1. The count comes from the entries the page already reads.

## Entry Points

| Kind | Method | Path or name | Class | Module | Status | Example |
|---|---|---|---|---|---|---|
