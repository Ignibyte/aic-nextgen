# Systems

The framework is one package, `aic/framework`, with one namespace per part under `AIC\`. The parts
group into ten systems. Each section below names its namespaces, what they do and the classes an
app meets first.

| System | Namespaces |
|---|---|
| 1. HTTP, routing and the kinds | Http, Routing, Controller, Kernel, Sapi, Config, Console, Profiler |
| 2. Wiring and modules | Container, Module |
| 3. Data | Database, Schema, Entity |
| 4. Components | Component |
| 5. Middleware | Middleware |
| 6. Blocks | Block |
| 7. The browser runtime | Tessera |
| 8. Form security | Form, Session, Secret |
| 9. Auth | Auth |
| 10. Background work | Queue, Schedule, Notification |

## 1. HTTP, routing and the kinds

- `AIC\Http`: PSR-7 messages and PSR-17 factories, with typed input. Byte ranges are parsed here.
  Its declared public asset lookup answers the reserved `/assets/` namespace before dynamic
  assembly, without sessions or database access. File and range output is shared with downloads.
  Responses default to `private, no-store`. Public assets have immutable fingerprint URLs,
  revalidated compatibility aliases and ordered conditional/range responses over verified
  bytes and same-handle metadata. Public errors and server-sent events retain `no-store`.
- `AIC\Sapi`: turns one server request into a PSR-7 request, uploads included, and emits the
  response. `AIC\Sapi\Infrastructure\SapiRequestExtractor` is the one place superglobals are read.
- `AIC\Kernel`: `AIC\Kernel\Application\Kernel` takes a request across that boundary and hands it
  to the middleware stack.
- `AIC\Routing`: `AIC\Routing\Application\Router` matches a path against the route table built
  from the manifests and passes typed path parameters as request attributes; route errors become
  404 and 405 answers.
- `AIC\Controller`: the five kind interfaces, their sealed responses, and
  `AIC\Controller\Application\Psr7ResponseWriter`, which turns a response into PSR-7. See
  [kinds.md](kinds.md).
- `AIC\Config`: `AIC\Config\Domain\AppConfig`, the typed object `config/app.php` returns.
- `AIC\Console`: `AIC\Console\Application\Console` runs `bin/aic <name>`; a missing or unknown
  name prints the usage and exits 2.
- `AIC\Profiler`: with `AIC_PROFILER=1`, `AIC\Profiler\Application\Profile` records each dynamic
  request (its handler, its times, each statement's SQL, each template, its Tessera exchange) in
  `aic_profiles` and names it in `X-AIC-Profile` (#229). It plugs into three seams with no
  observer by default: `AIC\Kernel\Contracts\RequestObserver`, `AIC\Routing\Contracts\RouteObserver`
  and `AIC\Component\Contracts\RenderObserver`. See [profiler.md](profiler.md).

See [native.md](native.md) for the compiled server and PHP comparison.

## 2. Wiring and modules

- `AIC\Module`: the manifest (`AIC\Module\Contracts\ModuleManifest`), the `#[Tagged]` attribute,
  and the kernels `AIC\Module\Application\AppKernel` and `AIC\Module\Application\ConsoleKernel`,
  which compose the framework's own services around the generated wiring. See
  [modules.md](modules.md).
- `AIC\Container`: a PSR-11 container that service providers fill at boot; it is frozen after
  boot and never changes during a request.

The development engine and its guard are described in [workflow.md](workflow.md).

## 3. Data

- `AIC\Database`: `AIC\Database\Contracts\Database`, parameterised queries with `?` placeholders
  over PDO pgsql, typed rows and transactions. Postgres is the only database. It is the framework's
  seam: an app's modules and theme never depend on it (gate:9). They read and write tables through
  `AIC\Entity`, and ask the database about itself (whether it answers, its version, a setting)
  through `AIC\Database\Application\DatabaseStatus`.
- `AIC\Schema`: migrations. A migration describes its tables through
  `AIC\Schema\Contracts\Schema`; `AIC\Schema\Application\Migrator` applies each version once, in version order across modules, in a
  transaction, and keeps a ledger with a checksum of each, so a changed migration shows as changed.
- `AIC\Entity`: tables as entity types with typed fields (Text, Date, Numeric, Boolean, Integer,
  Reference, and the CreatedAt and UpdatedAt stamps the database sets), each nullable or not. A module's
  repository builds on it (#184):
  - `AIC\Entity\Application\EntityStore` inserts (`insert`, `insertIfAbsent` on a unique field,
    `upsert` over it), updates and deletes rows by key, and runs `transaction(Closure)`, rolling
    back when the closure throws. Each written value must fit its field: a string, an int, a
    bool, or null where the field is nullable.
  - `AIC\Entity\Application\EntityQuery` takes conditions (`where` with an
    `AIC\Entity\Domain\Comparison`, null for `IS NULL`; `whereIn`; `whereAny` over
    `AIC\Entity\Domain\Condition`s; `search`, a case-insensitive contains match across text
    fields with `%` and `_` taken literally), `orderBy`, `orderByDescending`, `limit`, `offset`
    and `forUpdate`, and answers `rows`, `one` or `first`. `rows()` (like the database's
    `fetchAll()`) is an `AIC\Database\Contracts\Rows`: loop over it and each element is a `Row`
    in the compiled site too; array functions and `count()` take `all()`, and `first()` the
    first row or null (#201).
  - `totals()` answers `count`, `sum`, `max` and the distinct `values` of a field over the
    matching rows, as text where money is involved; `changes()` updates or deletes the matching
    rows and answers how many, refusing to run without a condition.
  - A `Reference` field names another entity type and holds its key. `with(field, prefix,
    optional)` joins through it (inner, or left when optional); the joined fields are
    `prefix.field` in conditions, search, order and totals, and its columns come back as
    `prefix_column`. Every column is qualified by its table; changes by query refuse a join.
  - `totals()` also groups: `countBy(field)`, `sumBy(field, group)` and `averageBy(field, group,
    scale)` (#209), keyed by the group's text, largest first; an average is rounded to `scale`
    places in SQL and comes back as text.
  - A report is a view a module's migration creates through `Schema::view(name, select)`, the one
    place a module writes SQL; an `EntityType` marked read-only maps it and is queried like a
    table, and every write to it is refused.
  - Every value is bound, and only the entity types' own field names reach the SQL.
- `AIC\Storage`: the app's stored files (#213), in two stores under `<root>/var/storage/`, which a
  deploy keeps across releases. `AIC\Storage\Application\PublicFiles` holds what any visitor may
  fetch; `AIC\Storage\Application\PublicFileHandler` serves it at `/files/<key>` after the assets
  and before any session or database work. `AIC\Storage\Application\PrivateFiles` holds what only
  a module's own Stream sends, through `download()` (a `FileDownload`), after its own check; a
  private file has no URL. Both take `put`, `putUpload` (the upload's bytes are copied, since
  `moveTo()` is a rename across file systems), `exists` and `delete`; a write goes to a temporary
  file beside its target and is renamed over it. A key (`AIC\Storage\Domain\StorageKey`) is
  `/`-joined segments of letters, digits, `.`, `_` and `-`, none starting with a dot. A page,
  block, command or job asks for either in its constructor; `bin/aic` passes `ConsoleKernel` the
  app's root for the console's. `AIC\Storage\Application\PageFiles` keeps a rendered page as a
  private file (#265): `put(key, PageDocument)` has the document write itself through the response
  writer, the one reader of rendered markup, and stores the body, so a job can keep a page in the
  theme's layout for a Stream to send later. A kept page carries no CSRF token and no block a route
  places, and its assets keep their canonical URLs, which resolve when the site serves it.

## 4. Components

- `AIC\Component`: `AIC\Component\Contracts\Component` (a piece of a page as data), layouts and
  regions, the catalog atoms in `AIC\Component\Domain` (links, buttons, inputs, forms, alerts and
  the rest), and `AIC\Component\Application\Renderer`, the only class that makes HTML. Every value
  a template writes is escaped by its type; `AIC\Component\Domain\RawHtml` is the one way round
  that, and each use needs a grant in `config/raw-html.php` (gate:27).
  The theme's HTML, page and component templates are escaped into `build/generated/templates/` and
  required by the generated lookups; no PHP class holds markup. A generated
  `AIC\Component\Contracts\PageLayouts` implementation constructs the theme's document, in the
  layout an `AIC\Component\Contracts\LayoutName` names;
  data-only `AIC\Component\Domain\ThemeLayout` and `AIC\Component\Domain\ThemePage` preserve
  the same region tree used by full and fragment rendering. See [modules.md](modules.md#the-theme).

See [rendering.md](rendering.md) and [templates.md](templates.md) for the complete rendering contract.

## 5. Middleware

- `AIC\Middleware`: one step of a PSR-15 stack. The stack's order is fixed: the browser runtime's
  protocol check and redirects, the request body cap (413), route errors, the session, the modules'
  middleware, the form guard, the browser runtime's write ledger, then the router.

## 6. Blocks

- `AIC\Block`: a block (`AIC\Block\Contracts\Block`) gives its placement (a region, a weight and a
  route prefix) and the component it shows. On every page whose path matches, it joins the region
  after the page's own components, lighter weights first. A block's constructor asks for services as a
  controller's does; the build wires them, and the handler context is built in two steps so the
  blocks' services have their roots (#203).

## 7. The browser runtime

- `AIC\Tessera`: the server side of Tessera, the browser runtime AIC pins and serves from
  `build/assets/`. It answers an enhanced request with the regions that changed instead of a whole
  document, turns redirects into a header the runtime follows, applies each write once by its id,
  and verifies the signed props a keyed component carries.

See [tessera.md](tessera.md) for markers, requests, replies and streams.

## 8. Form security

- `AIC\Form`: `AIC\Form\Application\FormGuardMiddleware` checks the form token and a honeypot on
  every unsafe request; `AIC\Form\Application\EnvelopeCodec` signs values a form carries;
  `AIC\Form\Domain\SameOriginPath` keeps redirects on the site; `AIC\Form\Application\UploadCheck`
  accepts an upload by its content, not its name.
- `AIC\Session`: a session in Postgres behind the `__Host-aic_session` cookie, string values by
  key, on the request as the `aic.session` attribute. It is written back only when the request
  changed its id or values; otherwise `SessionStore::touch()` extends its expiry alone, so a request
  that only read it cannot overwrite what another request saved (#207).
- `AIC\Secret`: `AIC\Secret\Domain\AppSecret`, the key every signature uses. It comes only from
  the `AIC_APP_SECRET` environment variable, and the app does not boot without 32 bytes or more.

## 9. Auth

- `AIC\Auth`: `AIC\Auth\Application\Authenticator` signs a user in and out, with the user's id in
  the session; passwords are hashed by Postgres pgcrypto (bcrypt). Users come from the app through
  `AIC\Auth\Contracts\UserProvider`, so there is no user entity in the framework; a sign-in hands
  back what the provider read as a `SignedInUser` (#238). A manifest's
  `protectedPrefix` turns an anonymous visitor away.

See [security.md](security.md) for sessions, form guards, uploads, auth and secret handling.

## 10. Background work

- `AIC\Queue`: a job queue in Postgres; `bin/aic queue:work` takes jobs with `FOR UPDATE SKIP
  LOCKED`, so several workers can share it, and a job whose worker died is due again after ten
  minutes. A job is queued through the request's one database connection, the one
  `EntityStore::transaction()` holds, so a job dispatched inside the transaction commits with the
  write or is rolled back with it (#225): queue a notification of a write inside that write's
  transaction, never after it. `QueueRecords` reads both tables as typed records, newest first
  (#262): `queued()` gives each `QueuedJob` with its `QueueState` (waiting, running or retrying, by
  the ten-minute rule `reserve()` uses) and its due time, and `failed()` each `FailedJob` with its
  reason. Times are ISO 8601 in UTC. `PostgresQueue::retry()` queues a failed job again, and
  `discard()` drops it.
- `AIC\Schedule`: schedules declared in manifests; `bin/aic schedule:run`, once a minute, queues
  each one that is due, and a ledger keeps a minute from firing twice. Given a UTC minute
  (`schedule:run 2026-10-29T07:00`) it queues what is due then (#208).
- `AIC\Notification`: a notification, with the site path it is about, goes to the database and by
  mail (AIC's own SMTP client), each delivery a job; a delivery whose channel cannot deliver now
  (a mail server that is down) retries after a delay and fails with the reason on its fifth attempt. `AIC\Notification\Application\Inbox` reads a
  user's kept notifications (unread count, a page newest first, one by id, the newest few after an id, up to a limit (#207))
  and marks them read, every query scoped to that user (#202); `forget(userId)` deletes them all, so
  a gate:23 pass can start the gate user's inbox afresh (#210).

## The layer ladder

Every namespace above has the same layers, and gate:12 (Deptrac) holds who may use whom. A class
directly under a namespace, outside these layers, fails the gate.

| Layer | May use |
|---|---|
| `Contracts` | PSR interfaces |
| `Domain` | Contracts, Attribute, PSR |
| `Exception` | Contracts, PSR |
| `Attribute` | Contracts, PSR |
| `Application` | Contracts, Domain, Exception, Attribute, PSR |
| `Infrastructure` | everything above |

An app's module code may use every layer but Infrastructure: it builds on the framework's seams,
never on its adapters. Where a seam is crossed between systems, the type is a PSR interface:
PSR-7 and PSR-17 for messages, PSR-15 for middleware and handlers, PSR-11 for the container.
