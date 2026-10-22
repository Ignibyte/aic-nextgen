---
ticket: 1
browser_testable: No
---

## Acceptance Criteria

| # | Target | Met |
|---|---|---|
| 1 | The greeting page says how many greetings it shows, above them | [ ] |
| 2 | CHANGELOG.md has an entry for it | [ ] |

## Design

The greeting page counts the greetings it already builds and shows the count above them.

### File Manifest

| File | Change | Purpose |
|---|---|---|
| `modules/site/src/Component/GreetingCount.php` | create | The count |
| `modules/site/src/Component/GreetingCountTemplate.php` | create | Its compiled template |
| `modules/site/templates/components/GreetingCount.html.php` | create | Its template |
| `modules/site/src/Controller/GreetingPage.php` | modify | Shows the count |
| `CHANGELOG.md` | modify | The entry |

### Locked-In Decisions

1. The count comes from the greetings the page already builds.

## Entry Points

| Kind | Method | Path or name | Class | Module | Status | Example |
|---|---|---|---|---|---|---|
