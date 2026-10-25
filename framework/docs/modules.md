# Modules

An app is a set of modules. A module is a folder, `modules/<name>/`, holding its classes, its
templates, its assets and one manifest that says what it brings to the app. Nothing outside
`modules/` holds app code; the app's own shell is config, the entry points, the build's output and
the tool settings.

## A module's folder

```text
modules/note/
  note.module.php            the manifest
  src/<Kind>/                the classes, namespace App\Note\<Kind>\
  templates/components/      explicitly declared *.tpl.php sources
  assets/                    CSS bundled into the site, other files copied
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
writes the wiring as PHP under `generated/`. That is why the app compiles natively: the wiring is
ordinary code with literal class names.

### Declarations

| Declaration | What it adds |
|---|---|
| `->requires('site')` | a module this one uses; only an exported class of a required module may be used |
| `->export(Foo::class)` | a class other modules may use |
| `->route(RouteMethod::Get, '/path', Handler::class)` | a route to a Page, Api or Stream; `RouteMethod` is `Get` or `Post`; parameters are typed (`{id:int}`) and reach the handler as request attributes |
| `->binding(Iface::class, Impl::class)` | the readonly class a constructor gets when it asks for the interface |
| `->plugin(Type::class, Impl::class)` | a plugin of a type; a constructor list parameter marked `#[Tagged(Type::class)]` receives every plugin of that type |
| `->factory(Factory::class)` | a readonly class whose methods build services from values; a constructor asking for a method's return type gets its product |
| `->block(Block::class)` | a block, placed by its own `placement()` |
| `->middleware(Mw::class, MiddlewarePosition::AfterSession)` | a PSR-15 middleware at a position in the fixed stack |
| `->protectedPrefix('/account')` | a path prefix only a signed-in visitor may open |
| `->command('note:prune', Cmd::class)` | a Command, run as `bin/aic note:prune` |
| `->job('prune', PruneJob::class)` | a Job, queued by that name |
| `->schedule('prune-nightly', 'prune', ['days' => '30'], '0 3 * * *')` | a job queued on a five-field UTC cron cadence, with its payload |
| `->migration(CreateNotesTable::class)` | a migration, applied in order by `bin/aic migrate` |
| `->with(new ModuleTemplate(Card::class, 'templates/components/card.tpl.php'))` | an owned component paired with its explicit typed template source |
| `->with(new ModuleTheme(...))` | the site module's selected HTML/page templates, typed page data, regions and assets |
| `->with(new ModuleAsset('styles', 'assets/site.css', ModuleAssetKind::Style))` | an explicitly typed declaration; assets name their local identity, owner-relative source and publication role |

Everything else a class needs comes through its constructor. A constructor asks for what it uses
by type; the generated wiring passes it. There is no container lookup in app code and no `new`
of a service.

### What the build writes

`composer run build` writes, under `generated/` (namespace `App\Generated\`):

- per module, the classes for its routes, handlers, middleware, commands, jobs, provider,
  migrations, schedules and protected prefixes;
- the aggregators that join every module's pieces, and the facades `WebModules` and
  `ConsoleModules` that the framework's `AIC\Module\Application\AppKernel` and
  `AIC\Module\Application\ConsoleKernel` take;
- the lookup from each component to its compiled template class;
- declared template classes under `generated/templates/` (`App\Generated\Templates`), and
  the selected-theme factory and transparent page adapter;
- the exact public asset lookup and its data parts, joined with the template lookup by the
  generated `Presentation` factory.

It also writes `build/surface.json` (every route, command and job with its kind),
`build/assets.json` (the private public-asset inventory) and
`public/assets/`. Never edit what the build writes: change the manifest or the source and build
again. gate:24 fails when any of it is stale, and when `generated/` or `public/assets/` holds a file
the build would not write.

## Folders

A class's kind decides its folder under `src/`, and gate:26 refuses a class in the wrong one. The
kinds are described in [kinds.md](kinds.md).

| Folder | Holds |
|---|---|
| `src/Controller/` | Page, Api and Stream |
| `src/Command/` | Command |
| `src/Job/` | Job |
| `src/Component/` | authored Component data; bounded legacy compiled templates remain here until migration |
| `src/Block/` | Block |
| `src/Middleware/` | Middleware |
| `src/Migration/` | Migration |
| `src/Notification/` | Notification |
| `src/Repository/` | classes named `…Repository` |
| `src/Form/` | classes named `…Form`, and plain form helpers |
| `src/Entity/` | plain classes: entities and values |
| `src/Service/` | plain classes: services and helpers |

A folder holds at most 15 files; `src/Migration/` has no cap. A sixteenth file means the module is
doing too much: split it into a second module, or group the work into fewer classes.

## Exports and requires

A module may use its own classes, the framework's, and the classes another module exports, if it
requires that module. gate:26 holds three rules:

- a class from another module must be exported there (`->export(`) and its module required here
  (`->requires(`); the message names the line to add;
- requires name modules that exist and form no cycle;
- nothing outside `modules/` but the shell.

## Declared templates and themes

Declarations use the manifest's existing `with()` seam. Feature modules declare
`AIC\Module\Contracts\ModuleTemplate` with a final owned Component class and a source path
relative to their module, under `templates/components/`. The typing line names that exact class:

```php
<?php /** @var \App\Site\Component\Greetings $greetings */ ?>
```

Only declared `.tpl.php` files are selected. Missing, duplicate, foreign, unclaimed, linked or
nonportable sources fail the build before publication. gate:25 checks these sources and the
bounded legacy `.html.php` sources against the same restricted PHP grammar: no new functions,
calls or runtime includes. Static types determine automatic escaping.

The site module selects one `AIC\Module\Contracts\ModuleTheme`:

```php
->with(new ModuleTheme('app', 'layouts/html.tpl.php', 'layouts/page.tpl.php', Page::class,
    ['header' => 'header', 'content' => 'content', 'nav' => 'nav', 'footer' => 'footer'],
    [new ModuleAsset('base', 'assets/theme.css', ModuleAssetKind::Style)]))
```

These paths are relative to `themes/app/`. `Page` is authored module Component data: final,
readonly, with public promoted nonnullable Region properties in declaration order. Its page
template reads properties such as `$page->header`. Every declared region must render exactly
once on every branch; a loop or conditional omission is refused. The HTML template takes
`AIC\Component\Domain\ThemeLayout`, reads title, identity and CSRF data, and places its page
and tracked assets exactly once. HTML/page wrappers render transparently through the same pass.
Region names and keyed component paths are shared with full pages and Tessera fragments.

The generated implementation of `AIC\Component\Contracts\PageLayouts` creates documents.
`AIC\Component\Domain\ThemePage` carries only the filled Regions; a compiled adapter constructs
the authored typed page and renders its compiled template. Render data holds no factory service.
The existing Renderer remains the only HTML constructor. No manifest, reflection or template
include runs while serving a request.

The dedicated Composer mapping `"App\\Generated\\Templates\\": "generated/templates/"` is
required on case-sensitive filesystems. Compiled classes remain in static/native analysis.
The build audits nested generated outputs, removes orphans and checks freshness without writes.
`module:show` and the generated wiki report actual source and generated identities.

The reference app currently declares its app theme and Greetings component. Existing `.html.php`
inputs use one bounded `TemplateBuild::legacyTemplates` bridge while the rest of the reference
components migrate in the following work. Old layouts and root CSS remain during
that transition; this does not describe the final migration as complete.

## Generate authored rendering files

With the workflow development package installed, use `bin/aic make:component <module> <Class>`
or `bin/aic make:page <module> <Class> <path>`. Both create lower-kebab `.tpl.php` sources and
add their `ModuleTemplate` declarations to the owning manifest. `--export` adds the component's
export in the same edit. A page's `--component=<Class>` reuses a declared local component and
adds only its route; a legacy component needs an explicit declaration before reuse.

For a site module without a selected theme or an existing layout service, run
`bin/aic make:theme <name>`. It creates typed page-region data, the HTML/page templates under
`themes/<name>/layouts/`, a declared stylesheet and an exported `HtmlLayout` service consuming
the compiled `PageLayouts`. The four regions are header, content, nav and footer. No favicon or
feature module is assumed. Names are lowercase words joined by hyphens, at most 32 bytes.

Set the exact compiled-template Composer prefix above and run `composer dump-autoload` first.
Use `--dry-run --json` to inspect all authored paths and the manifest edit before declaring
them in an open workflow's spec. Every target and the complete manifest edit pass the normal
write guard; the make records the files it wrote. Generated classes, public assets and wiki
output belong to `composer run build`. Existing files, a selected theme, conflicting literal
declarations or ambiguous imports refuse without writes. Rerunning a make refuses rather than
replacing source. The reference app's remaining legacy migration is separate from these writers.

## Assets

Declare every module asset in its module manifest through `->with(new ModuleAsset(...))`.
`AIC\Module\Contracts\ModuleAssetKind` distinguishes `Style` (CSS), `Script` (JavaScript)
and `File` (other supported public types). A local name such as `styles` becomes the catalog
identity `site/styles`. Its source starts with `assets/`, relative to the owning module.
Nested portable paths are supported. Missing files, undeclared files, symlinks, duplicate
identities or paths, unsupported extensions and mismatched roles fail the build.

Declared styles are published individually and bundled in module dependency and declaration
order into `public/assets/aic.css`, followed by declared selected-theme styles and the temporary
top-level `assets/` CSS input. The remaining migration removes that root input. Scripts, images, maps,
fonts and other declared files retain their bytes under `public/assets/<module>/`. Declaring
a script publishes it; a page or component must still select it when needed. Theme assets use
the same ModuleAsset roles and validation, logical names such as `theme_app/base` and aliases
under `public/assets/themes/app/`. They share the same fingerprint and HTTP policy.

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

The build owns every output under `public/assets/`; edit declarations and source files, then
rebuild. It removes old fingerprint directories. Deploy the native binary, catalog and public
tree together in isolated release roots, retaining old releases while clients can reference
them. Building in place under an old running binary is not an atomic deployment. The direct
application implements this HTTP policy; a static proxy needs equivalent publication selection,
validators and cache headers before replacing it.
