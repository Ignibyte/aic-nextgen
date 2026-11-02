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

A profile never holds a value bound to a statement (gate:15 holds the profiler to passing them
through), a cookie, an authorization header or a session token: headers are read by name from the
two lists above. The URL is kept as requested, as a web server's log keeps it.

The table keeps the newest 500 profiles; each new one deletes the rest. Assets and stored files
answer before the profiler, and the profiler's own pages (`/_profiler`) are not recorded. A
stream's time ends when its response is built, not when the stream closes.

## The seams

Three observers, none set by default, carry the request to the profile:

- `AIC\Kernel\Contracts\RequestObserver`, told by `AIC\Module\Application\DynamicApplication` when a
  request starts and what it answered; it may add a header.
- `AIC\Routing\Contracts\RouteObserver`, told by `AIC\Routing\Application\Router` when the handler
  gets the request (with its session) and when it answers.
- `AIC\Component\Contracts\RenderObserver`, told by `AIC\Component\Application\ObservedTemplates`
  each template's time and by `AIC\Component\Application\Renderer` each whole render's.

Times come from `AIC\Kernel\Domain\Monotonic`, a monotonic clock in microseconds.
