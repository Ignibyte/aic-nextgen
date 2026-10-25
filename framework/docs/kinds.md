# Kinds

Every class in a module is of one kind, and its kind decides the folder it lives in (see
[modules.md](modules.md#folders)). Five kinds are entry points: something outside the app reaches
them, over HTTP, from the console or from the queue. The rest are the pieces those entry points are
built from.

## Entry kinds

Each entry kind is one interface with one method, and the method returns one response type. The
response types are sealed: only the shapes in `AIC\Controller\Domain` implement them, so a
controller can answer only in the ways the table lists, and the framework writes the answer.

| Kind | Interface | Method | Answers with | Folder |
|---|---|---|---|---|
| Page | `AIC\Controller\Contracts\Page` | `page(ServerRequestInterface)` | `PageResponse` | `src/Controller/` |
| Api | `AIC\Controller\Contracts\Api` | `api(ServerRequestInterface)` | `ApiResponse` | `src/Controller/` |
| Stream | `AIC\Controller\Contracts\Stream` | `stream(ServerRequestInterface)` | `StreamResponse` | `src/Controller/` |
| Command | `AIC\Controller\Contracts\Command` | `command(CommandInput)` | `CommandResponse` | `src/Command/` |
| Job | `AIC\Controller\Contracts\Job` | `run(JobInput)` | `JobResponse` | `src/Job/` |

What holds the kinds:

- gate:9 refuses a class that implements two kinds, an app entry point that is a bare PSR-15
  handler, and a response type implemented outside `AIC\Controller\Domain`.
- gate:8 refuses a controller method that returns anything but its kind's response, and any string
  holding `<` in a module's `src/Controller/`. Helpers go to `src/Service/`.
- gate:26 refuses a kind outside its folder.
- `composer run build` lists every route and command with its kind in `build/surface.json`, and
  gate:24 fails when that file is stale.

### Page

An HTML answer to a GET or a POST. Form handlers are Pages too. A Page returns one of:

- `AIC\Controller\Domain\PageDocument`: a whole document, a `Layout` holding components, with a
  status (200 by default).
- `AIC\Controller\Domain\PageFragments`: some regions of that layout, by target, for a request the
  browser runtime sent; a 422 that redraws a form in place is one.
- `AIC\Controller\Domain\PageRedirect`: a location and a status (303 by default).
- `AIC\Controller\Domain\PageFullVisit`: tells the browser runtime to load a location as a full
  page.

A Page never returns markup. It builds components, puts them in a layout and returns the layout;
the Renderer writes the HTML.

### Api

A JSON answer: `AIC\Controller\Domain\JsonResponse` takes a `JsonSerializable` and a status.

### Stream

An answer sent over time or in pieces:

- `AIC\Controller\Domain\EventStream`: Server-Sent Events, pulled one at a time from an
  `AIC\Controller\Contracts\EventSource`.
- `AIC\Controller\Domain\FileDownload`: a file with its media type and download name; it answers a
  `Range` header with 206 or 416.

### Command

A console command, run as `bin/aic <name>`. It reads its arguments from `CommandInput` and returns
`AIC\Controller\Domain\CommandResult`: the lines to print and the exit code. The manifest names it
with `->command(`.

### Job

Background work. A job is queued by name with a payload of strings, read through `JobInput`, and
run by `bin/aic queue:work`. It answers with one of `AIC\Controller\Domain\JobDone`,
`AIC\Controller\Domain\JobRetry` (after a delay in seconds) or `AIC\Controller\Domain\JobFailed`
(with a reason). A schedule queues a job on a cron cadence. The manifest names it with `->job(`
and a schedule with `->schedule(`.

## Other kinds

These are not entry points; each has a contract or a name suffix and a folder of its own.

| Kind | Known by | Folder | What it is |
|---|---|---|---|
| Component | implements `AIC\Component\Contracts\Component` | `src/Component/` | a piece of a page as data: public readonly props and a declared template |
| Block | implements `AIC\Block\Contracts\Block` | `src/Block/` | a component placed in a region by weight and route prefix, on pages that do not build it |
| Middleware | implements `Psr\Http\Server\MiddlewareInterface` | `src/Middleware/` | a PSR-15 step in the stack, at a position the manifest names |
| Migration | implements `AIC\Schema\Contracts\Migration` | `src/Migration/` | one schema change, by version, applied once by `bin/aic migrate` |
| Notification | is `AIC\Notification\Contracts\Notification` | `src/Notification/` | something to tell a user, delivered by mail and to the database, each delivery a job |
| Repository | name ends in `Repository` | `src/Repository/` | reads and writes one entity's table |
| Form | name ends in `Form` | `src/Form/` | a form's fields and their checks |

A class of none of these kinds is plain, and sits in `src/Entity/`, `src/Service/` or
`src/Form/`. A component's declared `*.tpl.php` source lives in
`modules/<name>/templates/components/`; the build compiles it into `generated/templates/`.
Every authored source needs an explicit declaration; unsupported old sources are refused.
See [rendering.md](rendering.md) and [templates.md](templates.md) for the complete page contract.
