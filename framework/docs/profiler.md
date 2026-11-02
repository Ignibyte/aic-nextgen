# The profiler

With `AIC_PROFILER=1`, AIC records every dynamic request in the `aic_profiles` table and names it
in the response's `X-AIC-Profile` header (#229). It is a development tool: leave it unset in
production, where nothing of it is built and no request changes.

## Turning it on

`config/profiler.php` returns `AIC\Profiler\Domain\ProfilerConfig`, on when `AIC_PROFILER` is `1`:

```php
return new ProfilerConfig(enabled: getenv('AIC_PROFILER') === '1');
```

`bootstrap/serve.php` reads it and, when it is on, builds one `AIC\Profiler\Application\Profile`
for the request, wraps the database in `AIC\Profiler\Application\ProfiledDatabase`, and hands the
profile to `AIC\Module\Application\HandlerContexts` as its `AIC\Module\Application\RequestObservers`.
Nothing else may build the profiler: gate:15 refuses it anywhere but `bootstrap/serve.php`. Run
`bin/aic migrate` once for the table. A profile that cannot be kept (the table missing) is dropped
and the request still answers.

The compiled site records the same profiles as PHP: start it with the variable set
(`AIC_PROFILER=1 composer run native:serve`).

## What a profile holds

| Member | What it is |
|---|---|
| `method`, `path`, `query`, `status` | the request and its answer's status |
| `handler` | the matched route's controller class; empty when no route matched |
| `user` | the signed-in user's id; empty for a visitor |
| `microseconds` | `total`, `middleware` (before the handler), `handler` (its render included) and `render` |
| `statements` | each statement in order: its `sql` with `?` placeholders, `microseconds` and `rows`; `BEGIN`, `COMMIT` and `ROLLBACK` for a transaction |
| `components` | each template the Renderer ran, in the order each finished: its Tessera `path`, its `component` class and `microseconds`; an atom shares its component's path |
| `tessera` | the request's `Tessera-Request`, `Tessera-Protocol`, `Tessera-Targets`, `Tessera-Layout` and `Tessera-Write-Id` |
| `answer` | the response's `Content-Type`, `Tessera-Protocol`, `Tessera-Location` and `Tessera-Full-Visit` |
| `bytes` | the response body's length; 0 for a stream |
| `kind`, `browser` | the route's kind; a hash of the session id that links one browser's requests (#230) |
| `headers`, `fields` | the request's header names and posted field names, never their values (#230) |
| `body` | a Tessera fragment answer's markup, at most 64 KiB, its CSRF token redacted; empty otherwise (#230) |

A profile never holds a value bound to a statement (gate:15 holds the profiler to passing them
through), a cookie, an authorization header or a session token: headers are read by name from the
two lists above. The URL is kept as requested, as a web server's log keeps it.

The table keeps the newest 500 profiles; each new one deletes the rest. Assets and stored files
answer before the profiler, and the profiler's own pages (`/_profiler`) are not recorded. A
stream's time ends when its response is built, not when the stream closes.

## The pages

While the profiler is on, `AIC\Profiler\Application\ProfilerPages` answers `/_profiler` before the
app (#230), the way the asset and stored-file handlers do, so no route, session or token is
involved and any app has the pages. They take GET and HEAD only.

- `/_profiler` lists the newest 100 profiles: time, method, path, kind, status, ms, bytes,
  statements and the Tessera request with its targets. A path filter (`?path=`) and the kind tabs
  (`?kind=Page`) are answered in place by Tessera, and the table polls every five seconds.
- `/_profiler/{id}` shows one, in tabs answered in place (`?tab=`): Request (kind, handler, user,
  query, header and field names), Timeline (an SVG bar of middleware, handler, render and the rest),
  Database (each statement, a repeated one marked), Templates (each template rendered with its path)
  and Tessera (the headers asked and answered, the answer's size against the newest whole page for
  the path, and a fragment answer's markup, escaped). A link leads to the same browser's previous
  request.
- `/_profiler/styles.css` is their stylesheet, served from `AIC\Profiler\Domain\ProfilerStyles`.

The pages render in the framework's own layout, `AIC\Profiler\Domain\ProfilerLayout`, from the
framework's atoms alone, so they look the same in every app; Tessera comes from the app's asset
catalog by its logical name. Postgres reads each kept profile's JSON for them, so PHP decodes none.
`AIC\Profiler\Application\ProfilerMount` puts them in front of the app when
`AIC\Module\Application\RequestObservers` says the pages are on, which `bootstrap/serve.php` does
with the profiler.

A profile also keeps, for the pages: the route's kind, a key for the browser (the first 16 hex
digits of a SHA-256 of the session id, never the id), the request's header and field names, and a
fragment answer's markup up to 64 KiB, its `tessera-csrf-token` value replaced by `redacted`.

## The toolbar

While the profiler is on, every whole HTML page gains Tessera's debug meta
(`<meta name="tessera-debug" content="highlight">`) at the end of its head and the profiler's
toolbar at the end of its body (#231), whatever the theme. The render observer offers both
(`AIC\Component\Contracts\RenderObserver::headEnd()` and `bodyEnd()`), and
`AIC\Component\Application\Renderer::document()` places them, so no theme places them and none can
drop them. A Tessera fragment never carries them.

- Collapsed, the toolbar shows the page's own status, time, statements and size. Open, it links to
  the page's profile and to the list, and lists this browser's exchanges as they happen.
- Its list is a Tessera stream, `/_profiler/stream` (`AIC\Profiler\Application\ProfilerFeed`). It
  starts at the page's own profile, whose figures fill the summary (`@profiler-summary`), then
  appends a link per later profile of the same browser (`@profiler-exchanges`). Each event's id is
  the profile's, so Tessera resumes where it stopped. The stream reads the table once a second and
  asks Tessera to reconnect after half a minute.
- Tessera marks each element a response patched (`data-tessera-patched`, Tessera 0.4.0). The
  profiler's stylesheet outlines it while the toolbar's "Outline patched elements" box is ticked:
  a CSS rule, no script.

A profile's id is made when its request starts (`ProfileWriter::newId()`), so the page can link to
it before it is kept. The profiler's stylesheet scopes every rule under `.aic-profiler` (its pages)
or `.aic-toolbar`, so it never restyles an app's own atoms.

## The seams

Three observers, none set by default, carry the request to the profile:

- `AIC\Kernel\Contracts\RequestObserver`, told by `AIC\Module\Application\DynamicApplication` when a
  request starts and what it answered; it may add a header.
- `AIC\Routing\Contracts\RouteObserver`, told by `AIC\Routing\Application\Router` when the handler
  gets the request (with its session and the kind's handler) and when it answers.
- `AIC\Component\Contracts\RenderObserver`, told by `AIC\Component\Application\ObservedTemplates`
  each template's time and by `AIC\Component\Application\Renderer` each whole render's; asked by
  `Renderer::document()` what a whole page gains (#231).

Times come from `AIC\Kernel\Domain\Monotonic`, a monotonic clock in microseconds.
