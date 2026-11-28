# Systems

The framework is one package, `ignibyte/aic-framework`, with one namespace per part under `AIC\`. The parts
group into ten systems. Each section below names its namespaces, what they do and the classes an
app meets first.

| System | Namespaces |
|---|---|
| 1. HTTP, routing and the kinds | Http, HttpClient, Routing, Controller, Kernel, Sapi, Config, Console, Profiler, Mcp |
| 2. Wiring and modules | Container, Module |
| 3. Data | Database, Schema, Entity |
| 4. Components | Component |
| 5. Middleware | Middleware |
| 6. Blocks | Block |
| 7. The browser runtime | Tessera |
| 8. Form security | Form, Session, Secret |
| 9. Auth | Auth, Access, OAuth |
| 10. Background work | Queue, Schedule, Notification, Broadcast |

## 1. HTTP, routing and the kinds

- `AIC\Http`: PSR-7 messages and PSR-17 factories, with typed input. Byte ranges are parsed here.
  Its declared public asset lookup answers the reserved `/assets/` namespace before dynamic
  assembly, without sessions or database access. File and range output is shared with downloads.
  Responses default to `private, no-store`. Public assets have immutable fingerprint URLs,
  revalidated compatibility aliases and ordered conditional/range responses over verified
  bytes and same-handle metadata. Public errors and server-sent events retain `no-store`.
- `AIC\HttpClient`: calls to upstream APIs (#305). `AIC\HttpClient\Application\CurlClient` is a
  PSR-18 client over ext-curl and `CurlPool` sends many requests at once over `curl_multi`, each with
  its own time limit (`AIC\HttpClient\Contracts\RequestPool`); any status is a response, redirects
  are not followed, and nothing retries unless wrapped in `RetryingClient`. `config/app.php` names
  the upstreams (`AppConfig::$upstreams`). Module constructors take the client, the pool, the PSR-17
  factories and `Upstreams` as roots on both sides. See [http-client.md](http-client.md).
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
  request (its handler, its times, each statement's SQL, each template, each outbound call, its Tessera exchange) in
  `aic_profiles` and names it in `X-AIC-Profile` (#229). It plugs into three seams with no
  observer by default: `AIC\Kernel\Contracts\RequestObserver`, `AIC\Routing\Contracts\RouteObserver`,
  `AIC\Component\Contracts\RenderObserver` and `AIC\HttpClient\Contracts\OutboundObserver`. See [profiler.md](profiler.md).
- `AIC\Mcp`: an app's tools, resources and prompts for agents over MCP (#316).
  `AIC\Mcp\Application\McpServer` answers MCP's JSON-RPC from a module's Api route; the build writes
  each module's `src/Mcp/` into `App\Generated\<Module>Mcp`, whose `catalog()` the route serves,
  with every tool's input and output schema. Access is the route's `needs:` and each entry's own
  permission. Every tool call goes through `AIC\Mcp\Application\Capabilities` (#332), shared with an
  app's own assistant: a dry run, a call id applied once, a row of `aic_mcp_calls` per call.
  `AIC\Mcp\Application\McpStream` serves the GET stream, which tells a client when a list it may use
  changed, and a stepped tool's call reports each step as progress over an event-stream answer
  (#333). See [mcp.md](mcp.md).

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
  over PDO pgsql, typed rows and transactions, and LISTEN with its notifications on the request's own
  connection: `listen(channel)` and `notification(timeoutMs)`, over `Pdo\Pgsql::getNotify()` (#314).
  A transaction begun inside an open one is a savepoint (#332): its commit releases it and its roll back
  undoes only its own work, so a write that opens its own transaction nests inside a caller's.
  Postgres is the only database. It is the framework's
  seam: an app's modules and theme never depend on it (gate:9). They read and write tables through
  `AIC\Entity`, and ask the database about itself (whether it answers, its version, a setting)
  through `AIC\Database\Application\DatabaseStatus`.
- `AIC\Schema`: migrations. A migration describes its tables through
  `AIC\Schema\Contracts\Schema`; `AIC\Schema\Application\Migrator` applies each version once, in version order across modules, in a
  transaction, and keeps a ledger with a checksum of each, so a changed migration shows as changed.
  Besides the typed builder methods, `Schema::types()` gives `uuid()`, `uuidKey()` (a uuid key the
  database makes with `gen_random_uuid()`), `jsonb()` and `choice(list)` (text with a
  `CHECK (column IN (...))`), added with `TableBuilder::column(name, type)` (#307); a column cannot
  change into a key or a choice.
  - Declarations (#310): `Schema::expressions()` gives typed terms (`column`, `integer`, `decimal`,
    `text`, `boolean`, `calculate` with an `Arithmetic`, `concat`, `lower`, `length`) and predicates
    (`compare` with an `Operator`, `isNull`, `oneOf`, `all`, `any`, `not`), each rendered as it is
    built by `AIC\Schema\Application\PostgresExpressions`, the one class that makes a `Term` or a
    `Predicate` (gate:8, `aic.schemaExpression`); a text literal doubles its quotes and refuses NUL.
    The functions of `AIC\Schema\Contracts\Functions` are part of it (#344): the terms `trim`, `round`,
    `coalesce`, `countPresent` (how many terms are not null), `jsonKind`, `jsonLength` and `truth` (a
    predicate as a value, so two conditions compare), and the predicate `matches` (a regular expression).
    Both builders share `TableConstraints`: `unique`, `uniqueWithNulls` (two nulls are the same value),
    `references(columns, table, foreignColumns, OnDelete)` (a foreign key over several columns (#344); one
    column takes a new table's `foreignKey`), `index`, `check(name, predicate)` (the
    constraint `<table>_<name>`), `indexWhere(columns, predicate)`, `uniqueWhere(columns, predicate)`
    and `indexUsing(columns, IndexMethod)` (`Hash`, `Gin`, `Gist`, `Brin`, `Trigram` with the
    migration's `extension('pg_trgm')`, `JsonPath`); a created table refuses one that names a column
    it lacks. `types()->generated(type, term)` is a stored generated column, added with `column()` or
    `addColumn()` and never retyped into; nothing writes one (the database refuses with SQLSTATE 428C9).
    `types()->defaulted(type, term)` gives a column a default (#308), so a required column joins a
    table with rows. `Schema::defaults()` gives the common ones (#339): `now()` and `json(text)`, a
    checked JSON document as a jsonb literal; `AIC\Schema\Application\PostgresDefaults` is the one
    other class that makes a `Term`. `types()->timestampTz(precision)` keeps 0 to 6 digits of the
    seconds' fraction (`timestamptz(p)`); 6, the default, writes plain `timestamptz`.
  - Guards (#310), as triggers the DDL generates: `Schema::appendOnly(table)` refuses every UPDATE,
    DELETE and TRUNCATE (an audit trail, a ledger); `Schema::finalOnce(table, columns)` refuses
    changing a listed column once it holds a value and deleting a row where one does (a decision
    recorded once). Both raise SQLSTATE 23001, which a module sees as the `QueryException`'s message.
    A loop check or a denormalization belongs in a repository, inside `EntityStore::transaction()`.
- `AIC\Entity`: tables as entity types with typed fields (Text, Date, Numeric, Boolean, Integer,
  Reference, Uuid, Json, Choice, Timestamp, and the CreatedAt and UpdatedAt stamps the database
  sets), each nullable or not, keyed by an integer or, with `keyType: FieldType::Uuid`, a uuid. A
  module's repository builds on it (#184):
  - `AIC\Entity\Application\EntityStore` inserts (`insert`, `insertIfAbsent` on a unique field,
    `upsert` over it; `insertUuid` for a uuid-keyed type, answering the uuid the database made),
    updates and deletes rows by key (an int, or a uuid string), and runs `transaction(Closure)`,
    rolling back when the closure throws. Each written value must fit its field
    by `AIC\Entity\Domain\FieldFormats` (#307): a bool for Boolean, an int for Integer and an
    integer-keyed Reference, otherwise a string in its type's form (a uuid, a `YYYY-MM-DD` date,
    an ISO 8601 timestamp with its zone, a decimal, valid JSON, one of a Choice's values), or
    null where the field is nullable. Values stay strings: a JSON value class encodes itself, and a
    backed enum passes `->value`. A Json field reads back as Postgres's text of the document (`{"a": 1}`).
    `Row::dateTime()` reads a timestamp as a `DateTimeImmutable` in the offset the database gave.
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
  - Relations (#306): an `EntityType` declares `relations: [new Relation(...)]`, each named and
    of a `RelationKind`: `HasMany` and `HasOne` (the target's column holding the owner's key),
    `ManyToMany` (through a pivot entity type with its own fields; `key` and `otherKey` are its
    two key columns) and `Polymorphic` (an id field and a type field on the owner, over a closed
    map of type names to entity types). A target is built without relations back to its owner.
    A query takes `withCount(relation)` and `withSum(relation, field)` (columns
    `<relation>_count`, an integer, and `<relation>_sum_<field>`, text) and filters with
    `withExisting(relation, conditions)` and `withMissing(relation, conditions)`, each a
    correlated subquery, so limits still page owners; a Polymorphic relation, or one to the
    owner's own table, takes none of these. `EntityStore::related(type, relation, rows)` loads a
    page's related rows in one query per relation (one per type for a Polymorphic) as
    `RelatedRows`: `rowsOf(key)` loops like a query's rows, `one(key)` gives the first, and a
    ManyToMany's rows carry the pivot's fields as `pivot_<field>`.
  - Query forms (#311): `withColumns([...])` adds computed columns: `new Window(alias, WindowFunction,
    partitionBy, orderBy, Direction)` (row number, rank or dense rank within a partition, an integer),
    `new JsonValue(alias, jsonField, path)` (the text at a path, null where missing) and `new
    Similarity(alias, textField, text)` (pg_trgm's similarity, as text). `Comparison::Contains` keeps a
    Json field holding a JSON document, `EqualsIgnoringCase` compares lower case and `Similar` uses
    pg_trgm's `%` at its threshold. `withValuesIn(field, sourceType, sourceField, conditions)` keeps
    rows whose field is among another table's values, a subquery. `AIC\Entity\Application\EntityTrees`
    reads a tree a type keeps in a Reference to itself, `descendants(type, parentField, key)` and
    `ancestors(...)`, each row with its `depth`, live rows only, stopping at depth 64. A lock around
    a callable is `PostgresLocks::around()` (#312).
  - Queries over queries (#329): `EntityQuery::derived(outputs)` makes a `DerivedQuery` over the
    query's rows, read as a named subquery. It takes `where`, `search`, `orderBy`,
    `orderByDescending`, `limit` and `offset` on any column the query reads, a computed one included (a
    rank of 1, a similarity of `0.55` or more, a JSON value containing a text), and answers `rows()`,
    `first()` and `count()`, which ignores the page. `outputs` keeps and names the columns: `new
    Output(name, column)`, or `new Output(name, '', text)` for a fixed text. `unionAll(other)` and
    `unionDistinct(other)` join two derived queries whose columns have the same names, order and types,
    each side's own conditions, order and limit kept. A join's columns are `prefix_field` here.
    `withValuesOf(field, derivedQuery, column)` keeps an entity query's rows whose field is among a derived
    query's values. `Contains` and `Similar` compare a field in its own query, not here.
  - Lifecycle (#309): an `EntityType` takes `lifecycle: new Lifecycle(audited, deletedAt, listeners)`.
    Each write to an observed type (audited, or with a `Listener`) records its `EntityEvent` (Created,
    Updated, Deleted, Restored, Purged) for every row it changed, in the write's transaction: the
    caller's when one is open (`Database::inTransaction()`), its own otherwise. Audited, it adds a row
    to the framework's append-only `aic_entity_audit` (entity, key, event, the actor from
    `EntityStore::withActor($userId)`, the written values as jsonb, when), which
    `AIC\Entity\Application\AuditTrail::history(type, key)` reads oldest first, purged rows included.
    A `Listener` names a job its module's manifest declares; the job is queued with `entity`, `key`,
    `event` and `fields`. There is no general event bus and no observer: a listener that does work
    is a Job. `upsert` refuses an observed type. A soft-deleting type names a nullable Timestamp field
    (`deletedAt`), which no caller writes: `delete()` and `changes()->delete()` stamp it, every query,
    total, change and relation count, filter and load sees live rows only, `withDeleted()` sees all and
    `withDeleted(DeletedScope::Deleted)` only the deleted, and `changes()->restore()` and `purge()`
    bring a row back or delete it for good.
  - State machines (#308): an `EntityType` takes `stateMachine: new StateMachine(field, states,
    transitions)`, its field a required Choice of the states, the first where every insert starts;
    no caller writes it. `AIC\Entity\Application\EntityTransitions::apply(type, key, transition,
    actor)` asks the `Actor` for the `Transition`'s permission (#304), then moves the row in one
    UPDATE whose WHERE holds the edge, recorded as `Transitioned` through the lifecycle and credited
    to the actor. A refused or illegal edge is a `TransitionRefusedException` and writes nothing; a
    missing row is `EntityNotFoundException`. `upsert` refuses a type with a state machine.
  - A report is a view a module's migration creates through `Schema::view(name, select)`, the one
    place a module writes SQL; an `EntityType` marked read-only maps it and is queried like a
    table, and every write to it is refused.
  - Every value is bound, and only the entity types' own field names reach the SQL.
- `AIC\Cache`: a cache, locks and rate limits in Postgres alone (#312); there is no Redis client in core.
  `AIC\Cache\Application\PostgresCache` keeps text values by key (1 to 200 letters, digits and `:._-`,
  checked by `CacheKey`) in the unlogged `aic_cache`: `get` (null when missing or expired), `put` with
  its seconds, `forget`, `remember` (a closure computes a miss) and `increment`, one atomic upsert that
  keeps a live counter's expiry. A module encodes its own values. `PostgresLocks` takes advisory locks
  by name: `holdForTransaction` (refused outside an open transaction, held to its end), `around` (waits,
  runs the closure, always releases) and `tryAround` (runs only when the lock is free, and says so).
  `RateLimiter::hit(name, client, limit, seconds)` counts in the unlogged `aic_rate_limits`, a client's
  window opening at its first hit, and answers a `RateDecision`. `RateLimitMiddleware` (a module lists
  it, `AfterSession`) counts each `RateLimit` whose path prefix a request starts with, from the
  `RateLimits` one module's factory gives, keyed by the peer's address, and past a limit answers 429
  with `Retry-After`. `bin/aic cache:prune` and the job `aic-cache-prune` (for an app's schedule)
  remove what expired; `bin/aic rate:reset <name>` clears a limit's counts. `TableBuilder::unlogged()`
  makes such a table.
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
  that, and each use needs a grant in `config/raw-html.php` (gate:27). `HtmlSanitizer` makes
  HTML a visitor wrote safe to send as a `SanitizedHtml`, which only it makes (#337).
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
  `protectedPrefix` turns an anonymous visitor away. `AIC\Auth\Application\OidcSignIn` signs a
  person in through an OpenID Connect provider named in `AppConfig::$oidc` (#313).
- `AIC\Access`: what a signed-in user may do (#304). A module declares its permissions
  (`->permission()`), a route says what it needs (`needs:`), and roles in Postgres
  (`aic_role_permissions`, `aic_user_roles`, changed by `bin/aic role:*`) grant permissions to
  users. `AIC\Access\Application\Actors` gives the request's `Actor`; a module's
  `AIC\Access\Contracts\Policy` answers a `Decision` for one record, or before there is one.
  A bearer token (`aic_api_tokens`, `bin/aic token:*`) is an actor too, its scopes the
  permissions it holds (#313).
- `AIC\OAuth`: the app as an OAuth 2 authorization server (#331), on when `AppConfig::$oauth`
  names an issuer. The operator registers clients (`aic_oauth_clients`, `bin/aic client:*`);
  `POST /oauth/token` issues short-lived access tokens by the client credentials grant and by the
  authorization code grant with PKCE S256, and `/.well-known/oauth-authorization-server` and
  `/.well-known/oauth-protected-resource` publish the metadata, all before the session. A module
  routes the consent page (`/oauth/authorize`) to its own Page over
  `AIC\OAuth\Application\Authorizations`. An issued token is a bearer token: it reaches routes
  through `Actors` like an operator's, acting for the person who allowed it. A code exchange
  also answers a rotating refresh token (`aic_oauth_refresh_tokens`; a reused one
  revokes its family), a `resource` binds a token to one path of the site, `POST /oauth/revoke`
  and `/oauth/introspect` give a token back and report on one, and `POST /oauth/register` lets a
  client register itself when `OAuthServer::$registration` allows it (#352).

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
  `discard()` drops it. Each job is on a named queue (#336): `dispatch()` puts it on `default`, the
  site's, which the site's `queue:work` alone takes; `dispatchTo(queue, job, values, delay)` puts it on
  another, an interpreted worker's ([workers.md](workers.md)), and answers its id. `JobInput::$id`
  carries the id to the job, and `QueueRecords::state(id)` answers its `JobState`: Queued, Running,
  Failed, or Done once no record holds it (a done job is deleted, so an id never queued reads Done
  too). A failed job keeps its id, and a retry puts it back under it on its own queue.
- `AIC\Schedule`: schedules declared in manifests; `bin/aic schedule:run`, once a minute, queues
  each one that is due, and a ledger keeps a minute from firing twice. Given a UTC minute
  (`schedule:run 2026-10-29T07:00`) it queues what is due then (#208).
- `AIC\Notification`: a notification, with the site path it is about, goes to the database and by
  mail (AIC's own SMTP client), each delivery a job; a delivery whose channel cannot deliver now
  (a mail server that is down) retries after a delay and fails with the reason on its fifth attempt. `AIC\Notification\Application\Inbox` reads a
  user's kept notifications (unread count, a page newest first, one by id, the newest few after an id, up to a limit (#207))
  and marks them read, every query scoped to that user (#202); `forget(userId)` deletes them all, so
  a gate:23 pass can start the gate user's inbox afresh (#210).
- `AIC\Broadcast`: real-time push from a Job or a controller to the open streams of a channel (#314),
  on Postgres alone. `AIC\Broadcast\Application\Broadcaster::publish(Channel, data)` writes the
  event (text a module encodes) as a row of `aic_broadcasts` and notifies the one Postgres channel `aic_broadcasts` with the
  channel's name, in one statement that also removes rows older than `BroadcastConfig::$keepSeconds`;
  inside an open transaction the streams wake only when it commits. A `Channel` is lower-case letters
  and digits in parts joined by `.` or `-` (`ledger.feed`), a column value and never SQL. A Stream
  controller answers `Broadcasts::stream(Channel, BroadcastEvents, request)`, and a stream that wraps
  the source takes `Broadcasts::source()` (#333). `BroadcastSource` listens,
  sends each event after the request's `Last-Event-ID` (only new ones without it), each made by the
  module's `BroadcastEvents` with the row's id as its id, waits on the database's notifications while
  idle, sends a keep-alive after `keepAliveSeconds`, and ends the connection as a reconnect after
  `holdSeconds` (#202), so the client comes back with its last id and misses nothing. At most
  `streams` broadcast streams are open at once across the site (`StreamSlots`: session advisory locks
  on each stream's own connection, freed when its request ends; 7 by default, below one compiled worker's
  handlers, see [deploy.md](deploy.md#open-streams-and-workers)); one more is a single reconnect whose
  `retry:` is `refusedRetryMs`. A stream sends a keep-alive as soon as it listens, so its headers go out
  at once, and its hold's reconnect names the last id, so a quiet channel resumes where it began. `AppConfig::$broadcast` holds the five settings. Who may read a
  channel is its Stream route's `needs:` (#304), a person's role or a token's scope (#313). An event
  whose transaction commits after a later one's can be missed by a stream that already sent the
  later one; a module that must never miss one reads its own table on reconnect. Presence and
  two-way traffic wait on the socket server (#32).

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
