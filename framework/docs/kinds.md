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
- A Page answers a document from `AIC\Controller\Application\PageDocuments`, naming its title and
  optionally a theme layout (`ThemeLayoutName::Focus`); see [rendering.md](rendering.md).
- `composer run build` lists every route and command with its kind in `build/surface.json`, and
  gate:24 fails when that file is stale.

### Page

An HTML answer to a GET, or to a POST, PUT, PATCH or DELETE (#242). Form handlers are Pages too. A Page returns one of:

- `AIC\Controller\Domain\PageDocument`: a whole document, a `Layout` holding components, with a
  status: 200 (the default), 400 for an altered form, 403 for a signed-in user who may not do what
  was asked, 404, 409 for a conflicting write, or 422 for a form shown again with its errors.
- `AIC\Controller\Domain\PageFragments`: some regions of that layout, by target, for a request the
  browser runtime sent; a 422 that redraws a form in place is one.
- `AIC\Controller\Domain\PageRedirect`: a location, which must be a path on this site, and a
  status (303 by default).
- `AIC\Controller\Domain\PageFullVisit`: tells the browser runtime to load a location as a full
  page.
- `AIC\Controller\Domain\PageUnchanged`: a `204`, for a poll of one component whose data has not
  changed; see [tessera.md](tessera.md#markers-and-fragments).

A Page never returns markup. It builds components, puts them in a layout and returns the layout;
the Renderer writes the HTML.

### Api

A JSON or an XML answer. SOAP is not one of them.

- `AIC\Controller\Domain\JsonResponse` takes a `JsonSerializable` and a status.
- `AIC\Controller\Domain\XmlResponse` takes an `XmlDocument` and a status, and answers
  `application/xml; charset=utf-8` with an XML declaration (#233). The document is typed data:
  one root `XmlElement`, each element a name, its `XmlAttribute`s in order, its text and its child
  elements, the text written before the children. A name must be an XML name (letters, digits,
  `.`, `_` and `-`, led by a letter or `_`, with one optional `prefix:`; declare the prefix with an
  `xmlns:prefix` attribute), and text and values may not hold a character XML 1.0 forbids or bytes
  that are not UTF-8; either is refused when the value is made. `XmlDocument` escapes `&`, `<`,
  `>`, `"` and `'` when it writes, and nothing else writes XML: gate:32 refuses XML written as text
  under `modules/`. It is written by hand, not with `XMLWriter`, which would make libxml2 a native
  dependency of every app. The fixture's `/stock.xml` is the stock list as a feed.

### Stream

An answer sent over time or in pieces:

- `AIC\Controller\Domain\EventStream`: Server-Sent Events, pulled one at a time from an
  `AIC\Controller\Contracts\EventSource`.
- `AIC\Controller\Domain\FileDownload`: a file with its media type, download name and the request; it
  answers a `Range` header with 206 or 416. It is validated: a strong `ETag` from the file's
  modification time and size, and `Last-Modified`; `If-None-Match` or `If-Modified-Since` answers 304,
  `If-Match` or `If-Unmodified-Since` 412, and a stale `If-Range` the whole file. Downloads answer
  `Cache-Control: private, no-cache`, so a browser revalidates instead of fetching again.
- `AIC\Controller\Domain\CsvDocument` is not an answer but what an export puts in its file (#209):
  a header and rows as RFC 4180 text, CRLF line ends, a field quoted when it holds a comma, quote,
  CR or LF. A field starting with `=`, `+`, `-`, `@`, a tab or CR gets a leading `'` so a
  spreadsheet never runs it as a formula; a plain number such as `-12` stays a number. A job (or a
  Stream) writes `text()` to a file outside `public/`, through `tempnam()` and `rename()` so a
  download never reads half a file, and a Stream sends it as a `FileDownload`.

### Command

A console command, run as `bin/aic <name>`. It reads its arguments from `CommandInput` and returns
`AIC\Controller\Domain\CommandResult`: the lines to print and the exit code. The manifest names it
with `->command(`.

### Job

Background work. A job is queued by name with a payload of strings, read through `JobInput`, and
run by `bin/aic queue:work`. It answers with one of `AIC\Controller\Domain\JobDone`,
`AIC\Controller\Domain\JobRetry` (after a delay in seconds, within the attempts it allows, three by
default) or `AIC\Controller\Domain\JobFailed` (with a reason). `bin/aic queue:failed` lists the
failed jobs with their ids, and `bin/aic queue:retry <id>` queues one again (#208). A page that
shows the queue reads it through `AIC\Queue\Application\QueueRecords`, never SQL of its own (#262). A schedule queues a job on a cron cadence. The manifest names it with `->job(`
and a schedule with `->schedule(`.

A command or a job may ask for `AIC\Controller\Application\PageDocuments` and
`AIC\Storage\Application\PageFiles` in its constructor to make a page in the app's theme and keep
it (#265). The console reads the theme from the generated `ConsoleModules` and the app's name from
`config/app.php`, which `bin/aic` passes `ConsoleKernel` after the root; without it, asking for a
page fails with a `LogicException` that says so.

## Other kinds

These are not entry points; each has a contract or a name suffix and a folder of its own.

| Kind | Known by | Folder | What it is |
|---|---|---|---|
| Component | implements `AIC\Component\Contracts\Component` | `themes/<theme>/src/Components/<Name>/` | a piece of a page as data: public readonly props; its template sits in the theme's `components/<Name>/` |
| Block | implements `AIC\Block\Contracts\Block` | `src/Block/` | a component placed in a region by weight and route prefix, on pages that do not build it |
| Middleware | implements `Psr\Http\Server\MiddlewareInterface` | `src/Middleware/` | a PSR-15 step in the stack, at a position the manifest names |
| Migration | implements `AIC\Schema\Contracts\Migration` | `src/Migration/` | one schema change, by version, applied once by `bin/aic migrate` |
| Notification | is `AIC\Notification\Contracts\Notification` | `src/Notification/` | something to tell a user, delivered by mail and to the database, each delivery a job |
| Repository | name ends in `Repository` | `src/Repository/` | reads and writes one entity's table |
| Form | name ends in `Form` | `src/Form/` | a form's fields and their checks |

A class of none of these kinds is plain, and sits in `src/Entity/`, `src/Service/` or
`src/Form/`. A component is the one kind that never sits in a module: it lives in the theme, its
class under `src/Components/<Name>/` and its `*.phtml` under `components/<Name>/`, and the build
escapes the template into `build/generated/templates/`.
See [rendering.md](rendering.md) and [templates.md](templates.md) for the complete page contract.
