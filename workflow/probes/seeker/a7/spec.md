---
ticket: 1
browser_testable: No
---

## Acceptance Criteria

| # | Target | Met |
|---|---|---|
| 1 | The notes list shows the newest note first | [ ] |
| 2 | Only NoteRepository queries the database in the note module; the note controllers never do | [ ] |
| 3 | CHANGELOG.md has an entry for it | [ ] |

## Design

NoteRepository returns the notes newest first; the controllers and the template stay as they are.

### File Manifest

| File | Change | Purpose |
|---|---|---|
| `modules/note/src/Repository/NoteRepository.php` | modify | Newest first; states the rule |
| `CHANGELOG.md` | modify | The entry |

### Locked-In Decisions

1. Only NoteRepository queries the database in the note module; the note controllers never do.

## Entry Points

| Kind | Method | Path or name | Class | Module | Status | Example |
|---|---|---|---|---|---|---|
