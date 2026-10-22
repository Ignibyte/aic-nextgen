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
  templates/components/      one *.html.php per component
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
- the lookup from each component to its compiled template class.

It also writes `build/surface.json` (every route, command and job with its kind) and
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
| `src/Component/` | Component, and the template classes the build compiles beside them |
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

## Assets

The site has one theme, CSS only, in the app's top-level `assets/`. `composer run build` bundles
each module's `modules/<name>/assets/*.css`, in requires order, then the theme last, into
`public/assets/aic.css`. Any other file in a module's `assets/` (scripts, images, fonts) is copied
to `public/assets/<name>/`. The build owns `public/assets/`.
