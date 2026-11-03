# Modules

An app is a set of modules and a theme. A module is a folder, `modules/<name>/`, holding its
classes and one manifest that says what it brings to the app; the theme holds every component,
template and asset. Nothing outside `modules/` and the theme holds app code; the app's own shell
is config, the entry (`public/index.php`), everything the build writes (under `build/`) and the
tool settings.

## A module's folder

```text
modules/note/
  note.module.php            the manifest
  src/<Kind>/                the classes, namespace App\Note\<Kind>\
```

A new module needs one PSR-4 line in `composer.json` (`"App\\Note\\": "modules/note/src/"`, then
`composer dump-autoload`); the build prints the line when it is missing.

## The manifest

`modules/<name>/<name>.module.php` returns an `AIC\Module\Contracts\ModuleManifest`, built
fluently. It names only what the module has:

```php
return (new ModuleManifest('note'))
    ->requires('site')
    ->route(RouteMethod::Get, '/notes', NoteListPage::class)
    ->route(RouteMethod::Post, '/notes', NoteCreatePost::class)
    ->route(RouteMethod::Get, '/notes/{id:int}', NoteShowPage::class)
    ->block(NoteTipsBlock::class)
    ->migration(CreateNotesTable::class);
```

Nothing reads a manifest at run time. `composer run build` reads every manifest, checks it, and
writes the wiring as PHP under `build/generated/`. That is why the app compiles natively: the wiring is
ordinary code with literal class names.

### Declarations

| Declaration | What it adds |
|---|---|
| `->requires('site')` | a module this one uses; only an exported class of a required module may be used |
| `->export(Foo::class)` | a class other modules may use |
| `->route(RouteMethod::Get, '/path', Handler::class)` | a route to a Page, Api or Stream; `RouteMethod` is `Get`, `Post`, `Put`, `Patch` or `Delete` (#242; a GET route answers HEAD too, and a path asked with a method it lacks answers 405 with `Allow`); parameters are typed (`{id:int}`) and reach the handler as request attributes |
| `->binding(Iface::class, Impl::class)` | the readonly class a constructor gets when it asks for the interface |
| `->plugin(Type::class, Impl::class)` | a plugin of a type; a constructor list parameter marked `#[Tagged(Type::class)]` receives every plugin of that type |
| `->factory(Factory::class)` | a readonly class whose methods build services from values; a constructor asking for a method's return type gets its product |
| `->block(Block::class)` | a block, placed by its own `placement()`; its constructor's services are wired as a controller's (#203) |
| `->middleware(Mw::class, MiddlewarePosition::AfterSession)` | a PSR-15 middleware at a position in the fixed stack |
| `->protectedPrefix('/account')` | a path prefix only a signed-in visitor may open |
| `->command('note:prune', Cmd::class)` | a Command, run as `bin/aic note:prune` |
| `->job('prune', PruneJob::class)` | a Job, queued by that name |
| `->schedule('prune-nightly', 'prune', ['days' => '30'], '0 3 * * *')` | a job queued on a five-field UTC cron cadence, with its payload |
| `->migration(CreateNotesTable::class)` | a migration; `bin/aic migrate` applies every module's migrations in version order (#228), so one that names another module's table needs a later version than the migration creating it |

A manifest declares no presentation: components, layouts and assets live in the theme (see
[The theme](#the-theme)). Everything else a class needs comes through its constructor. A constructor asks for what it uses
by type; the generated wiring passes it. There is no container lookup in app code and no `new`
of a service.

### What the build writes

`composer run build` writes everything under `build/` (#167). Under `build/generated/` (namespace
`App\Generated\`):

- per module, the classes for its routes, handlers, middleware, blocks, commands, jobs, provider
  (its plugins), migrations, schedules and protected prefixes;
- the aggregators that join every module's pieces, and the facades `WebModules` and
  `ConsoleModules` that the framework's `AIC\Module\Application\AppKernel` and
  `AIC\Module\Application\ConsoleKernel` take;
- the lookup from each component to its escaped template (`ThemeTemplates` and its parts);
- the theme's escaped templates under `build/generated/templates/`, and the theme's document factory
  and transparent page adapter;
- the exact public asset lookup and its data parts, joined with the template lookup by the
  generated `Presentation` factory.

It also writes `build/surface.json` (every route, command and job with its kind),
`build/assets.json` (the private public-asset inventory) and `build/assets/`, the published files
AIC's asset handler serves at `/assets/`. `public/` holds only `index.php`. Never edit what the
build writes: change the manifest or the source and build again. gate:24 fails when any of it is
stale, and when `build/generated/` or `build/assets/` holds a file the build would not write.

## Folders

A class's kind decides its folder under `src/`, and gate:26 refuses a class in the wrong one. The
kinds are described in [kinds.md](kinds.md).

| Folder | Holds |
|---|---|
| `src/Controller/` | Page, Api and Stream |
| `src/Command/` | Command |
| `src/Job/` | Job |
| `src/Block/` | Block |
| `src/Middleware/` | Middleware |
| `src/Migration/` | Migration |
| `src/Notification/` | Notification |
| `src/Repository/` | classes named `…Repository` |
| `src/Form/` | classes named `…Form`, and plain form helpers |
| `src/Entity/` | plain classes: entities and values |
| `src/Service/` | plain classes: services and helpers |

A module's root holds its manifest, an optional `README.md`, `src/` and `resources/` (download
sources). A Component class, a template or a public asset in a module is refused: they live in the
theme. A folder holds at most 15 files; `src/Migration/` has no cap. A sixteenth file means the module is
doing too much: split it into a second module, or group the work into fewer classes.

## No markup

A module holds no HTML at all. gate:32 reads every file under `modules/` on disk and refuses a
template or markup file by its name (`.phtml`, `.tpl.php`, `.html`, `.svg`, `.twig` and the rest), inline
output outside `<?php`, an HTML tag, markup declaration or character reference in any string
literal or text file, a `<` left for a computed tag, and the HTML functions; in `src/Controller/`
any `<` fails. Markup belongs in the theme, built from components by the Renderer. A command's
usage placeholder named like an element (`<table>`) is refused: write `{table}`.

## Exports and requires

A module may use its own classes, the framework's, and the classes another module exports, if it
requires that module. gate:26 holds three rules:

- a class from another module must be exported there (`->export(`) and its module required here
  (`->requires(`); the message names the line to add;
- requires name modules that exist and form no cycle;
- nothing outside `modules/` but the shell.

A module's tables are its own. A table belongs to the module whose migration creates it (`create`,
or `view` for a report); an entity type (`new EntityType('notes', ...)`) and an alteration name only
their module's tables, written as literals, and one table has one owner. Another module reads or
writes the table through the owner's exported repository. gate:26 holds this, and gate:9 keeps
modules off the raw `Database`, so no query reaches a table an entity type does not name.

`bin/aic migrate` applies every module's migrations in version order (#228), whatever their modules'
order. A migration may name another module's table in a foreign key or in a view's SQL, and then needs
a later version than the migration that creates it; gate:26 holds this, and a migration's
`version()` returns a string literal.

## The theme

`config/app.php` names the theme (`theme: 'app'`); the build reads `themes/<theme>/` and no other
folder, and nothing reads it while serving. The templates sit on top, where people look; the PHP
behind them sits in `src/` (#167):

```
themes/app/
  layouts/     html.phtml  page.phtml  page--focus.phtml
  components/  Greetings/greetings.phtml  NoteCard/note-card.phtml  ...
  src/
    Layouts/     Page.php  FocusPage.php
    Components/  Greetings/Greetings.php  NoteCard/NoteCard.php  ...
  assets/      10-tokens.css  site.css  favicon.svg  ...
  frontend/    (optional) the sources of a front-end build
```

Each `components/<Name>/` holds only the template `<name>.phtml` (the class name in lower kebab
case, `line-list.phtml` for `LineList`). Its twin `src/Components/<Name>/` holds the final
Component `App\Theme\Components\<Name>\<Name>` and any value classes of the same namespace that
are not Components. The template's typing line names its component's class:

```php
<?php /** @var \App\Theme\Components\Greetings\Greetings $greetings */ ?>
```

Nothing declares a component: the folders are the declaration. A template folder without its
class folder or the reverse, a `.php` file under `components/` or `layouts/`, a class whose name or
namespace does not match its folder, a second Component in a folder, a stray file, a link, or a
`.phtml` anywhere outside the theme fails the build before publication. gate:25 checks every template against the restricted PHP grammar: no functions,
calls or runtime includes. Static types determine automatic escaping.

`layouts/` holds exactly the document and the page templates; the page's region data is
`src/Layouts/Page.php`, which declares `App\Theme\Layouts\Page`: final, readonly, one public
promoted nonnullable Region per region. The regions are its constructor's parameters, named after their properties, in order; renaming a
property renames a Tessera region. The page template reads them (`$page->header`), and each
must render exactly once on every branch: a loop or conditional omission is refused. The HTML
template takes `AIC\Component\Domain\ThemeLayout`, reads title, identity and CSRF data, and
places its page and tracked assets exactly once. HTML and page wrappers render transparently
through the same pass. Region names and keyed component paths are shared with full pages and
Tessera fragments.

Modules build theme components and never the reverse: gate:12's Theme layer lets a theme class
use only the framework's contracts, domain values, exceptions and attributes. A component that
needs a module's data takes its values as properties.

The app maps the theme's two namespaces in Composer and PHPCS, and the build prints any line that
is missing:

```
"App\\Theme\\Components\\": "themes/app/src/Components/",
"App\\Theme\\Layouts\\": "themes/app/src/Layouts/"
<element key="themes/app/src/Components" value="App\Theme\Components"/>
<element key="themes/app/src/Layouts" value="App\Theme\Layouts"/>
```

Named layouts sit beside the default ones: `html--<name>.phtml`, `page--<name>.phtml`
or both, where `<name>` is lowercase words joined by single hyphens (not `class`). A named page may
read its own page data, a class in `src/Layouts/` that its first line names (`FocusPage`), with its own
regions, which must include `content`; a page data class no page reads is refused. The build
generates `App\Generated\ThemeLayoutName`, a case per name (`focus` gives `Focus`), and refuses two
names that give the same case. A controller passes the case to `PageDocuments`; a file the named
layout does not ship falls back to `html.phtml` or `page.phtml`. Its Tessera identity is
`<theme>-<name>`.

The generated implementation of `AIC\Component\Contracts\PageLayouts` creates documents.
`AIC\Component\Domain\ThemePage` carries only the filled Regions; a compiled adapter constructs
the theme's typed page and renders its page template. Render data holds no factory service.
The existing Renderer remains the only HTML constructor. No reflection or template include runs
while serving a request.

Templates run as templates: the build escapes each into `build/generated/templates/`, where its lookup
requires it, and no Composer mapping names them. The build audits nested generated outputs,
removes orphans and checks freshness without writes.
`build/surface.json` carries the theme as a top-level `theme` object, and the code wiki's index
has a Theme section.

## Generate theme files

With the workflow development package installed, `bin/aic make:component <Class>` writes the
component's template into the theme's `components/<Class>/` and its class into
`src/Components/<Class>/`, and edits no manifest.
`bin/aic make:page <module> <Class> <path>` writes the controller in the module, routes it in the
module's manifest, and writes its own view into the theme; `--component=<Class>` reuses a theme
component instead, and `--layout=<name>` makes the page pick one of the theme's named layouts.
`bin/aic make:layout <name>` writes a named page layout: its page data in `src/Layouts/`, with the
content region only, and `layouts/page--<name>.phtml`; the document falls back to `html.phtml`, and the next
`composer run build` adds its case to `ThemeLayoutName`.

`bin/aic make:entity <module> <Class> <table> --fields=<name>:<type>,…` writes a table slice in the
module: the entity (one property per field and its row as one line), its repository (the newest
rows, read with a limit), its create-table migration and a list page at the table's path (`_`
becomes `-`) that shows the newest 50 rows in a heading and paragraphs. Types are `text`,
`numeric`, `date` and `boolean`; every table also has `id` and `created_at`. One manifest edit
routes the page and runs the migration; run `bin/aic migrate` before opening the page.

For an app without a theme, run `bin/aic make:theme <name>`. It writes `themes/<name>/layouts/`
(the HTML and page templates), `src/Layouts/Page.php` (four regions: header, content, nav, footer)
and a stylesheet, then prints the `config/app.php`, Composer and PHPCS lines to add. No favicon or feature module
is assumed. Names are lowercase words joined by hyphens, at most 32 bytes.

Map `App\Theme\Components\` (to `src/Components/`) in Composer and run
`composer dump-autoload` first. Use `--dry-run --json` to inspect every path before declaring it
in an open workflow's spec. Every target passes the normal write guard; the make records the files
it wrote. Everything under `build/` and the wiki belong to `composer run build`.
Existing files refuse without writes; rerunning a make refuses rather than replacing source.

## Assets

Every public asset lives in the theme's `assets/` folder, and every file there is published: no
declaration names it. Its role comes from its extension: `.css` is a stylesheet, `.js` a script,
any other supported type a file. The catalog identity is `theme_<theme>/<path>`, such as
`theme_app/site.css`. Nested portable paths are supported. Symlinks, nonportable names and
unsupported extensions fail the build.

Stylesheets are published individually and bundled in path order into `build/assets/aic.css`;
number a file's name (`10-tokens.css`) to order the cascade. Scripts, images, maps, fonts and
other files keep their bytes under `build/assets/themes/<theme>/`. AIC's asset handler serves
`build/assets/` at `/assets/` under PHP and compiled alike; in production Caddy may map `/assets/`
there directly. Publishing a script does not
select it, with one exception: a script named `*.page.js` is a page script (#239). Every layout
loads the theme's page scripts after the pinned Tessera script, deferred, in path order, each by
its canonical URL with `data-track` through `TrackedAssets`, so a fragment answer carries their
copies and a changed one makes Tessera load the page whole. Any other script a page or component
must load itself. Every asset shares the same fingerprint and HTTP policy.

A theme whose stylesheet is built (Tailwind, for one) keeps the build's sources in its own
`frontend/` folder, as a Drupal theme does: its `package.json`, lockfile and input CSS and
scripts, with `node_modules/` ignored. Nothing in it is published or read as a template. The build writes its output into the theme's
`assets/`, where it is committed and published like any other stylesheet; rerun it after changing
a template's classes, then `composer run build`.

The generated `AIC\Http\Contracts\PublicAssets` lookup resolves logical names to canonical
fingerprinted URLs. Its exact URL lookup accepts that URL or its declared compatibility alias.
The private `build/assets.json` inventory describes both addresses for each representation.
The SHA-256 of the sorted complete asset set names the canonical directory, so a byte, identity
or publication change invalidates the set. Project roots and timestamps do not enter that hash.
Aliases retain their bytes, and the pinned Tessera script and source map stay adjacent under
both addresses. Request text never selects a filesystem path. The framework reserves
`/assets` and `/assets/`; module routes cannot claim that namespace. GET, HEAD, single byte
ranges and public failures are handled before module factories, plugins, handlers, sessions
or database work. Public responses carry no session cookie or Tessera-specific variation.

Canonical responses use `Cache-Control: public, max-age=31536000, immutable`; aliases use
`public, no-cache` and must revalidate. Errors use `no-store`. An opened file supplies bounded
verified bytes and modification time together. Responses use a strong content ETag, Last-Modified
clamped to Date, and ordered HTTP preconditions before ranges. GET and HEAD can return a
bodyless 304; HEAD ignores ranges and keeps the full representation's length. A matching strong
If-Range tag permits a range. Weak tags, mismatches and date-form If-Range return the whole
representation because publication does not prove a strong date history.

The build owns every output under `build/assets/`; edit declarations and source files, then
rebuild. It removes old fingerprint directories. Deploy the native binary, catalog and assets
tree together in isolated release roots, retaining old releases while clients can reference
them. Building in place under an old running binary is not an atomic deployment. The direct
application implements this HTTP policy; a static proxy needs equivalent publication selection,
validators and cache headers before replacing it.
