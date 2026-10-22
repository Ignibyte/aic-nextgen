# Tickets

One markdown file per ticket, named `NNNN-slug.md`: the number zero-padded to four digits, then a
short slug, such as `0007-faq-search.md`. `bin/aic workflow:run 7` finds it by its number.

## Format

```markdown
---
number: 7
title: "Search the FAQ"
type: feature
status: open
created: 2026-10-22T09:00:00Z
updated: 2026-10-22T09:00:00Z
---

What the ticket asks for, in plain markdown: the problem, what done looks like, anything
the work must not change.

## Comments
```

- `number` matches the file name's number, without the padding.
- `title` is a JSON string: double quotes, with `\"` for a quote inside it.
- `status` is one of `open`, `in-progress`, `in-review`, `done`, `won-t-fix`, `abandoned` or
  `closed`. A run moves it: `in-progress` when the run begins, `in-review` when it completes.
  A person closes it after review.
- `type` is `feature`, `bug`, `chore`, `spike` or `docs`.
- The run adds `phase:` and updates `updated:`. It changes no other line.

Write new tickets with the next free number. The description is written once; later notes go
under `## Comments`, each under a `### <UTC time>` heading.
