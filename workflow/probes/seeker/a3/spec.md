---
ticket: 1
browser_testable: No
---

## Acceptance Criteria

| # | Target | Met |
|---|---|---|
| 1 | `bin/aic notes:stats` prints the number of notes and the number of content pages, a line each | [ ] |
| 2 | CHANGELOG.md has an entry for it | [ ] |

## Design

A Command in the note module counts the rows it needs and prints them.

### File Manifest

| File | Change | Purpose |
|---|---|---|
| `modules/note/src/Command/NoteStats.php` | create | `notes:stats` |
| `modules/note/src/Repository/NoteRepository.php` | modify | The counts |
| `modules/note/note.module.php` | modify | Declares the command |
| `CHANGELOG.md` | modify | The entry |

### Locked-In Decisions

1. One command, two lines, no arguments.

## Entry Points

| Kind | Method | Path or name | Class | Module | Status | Example |
|---|---|---|---|---|---|---|
| Command | | notes:stats | App\Note\Command\NoteStats | note | | |
