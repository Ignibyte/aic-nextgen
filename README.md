# AIC

AIC is a PHP 8.5 framework for server-rendered sites. It is built for coding agents to write apps
in, under strict gates, and built so the whole app can also compile to a native binary with
[elephc](https://github.com/illegalstudio/elephc). It is a working framework: Lotline, a car
dealer's site, runs on it. It is also young and opinionated, and it is public so PHP people can try
it and say what breaks.

## What to know first

- **Two runtimes, one code base.** An app runs under plain PHP 8.5 (php-fpm or FrankenPHP) and
  compiles with elephc into a prefork HTTP server. The gates hold the two to the same answers. Run
  PHP in production; the compiled runtime is experimental
  ([framework/docs/deploy.md](framework/docs/deploy.md)).
- **The compiler is a fork.** Native builds need [AIC's elephc fork](https://github.com/Ignibyte/aic_compiler):
  upstream elephc plus the fixes AIC needs, listed in its `FIXES.md` and sent upstream where they
  are about PHP in general. Running under PHP needs no compiler.
- **Nothing is discovered at run time.** A module declares what it brings in a manifest, and
  `composer run build` writes the wiring as plain PHP under `build/generated/`. Every entry point is
  one of five kinds (Page, Api, Stream, Command, Job). Markup lives only in the theme's `.phtml`
  templates, never in a PHP class.
- **Gates, not reviews.** 33 gates hold every change: PHPStan level 10, Psalm with taint analysis,
  Deptrac, PHPMD, a native compile of every class, a whole-site comparison of PHP against the
  compiled binary, and more. There are no baselines and no suppressions.
- **The browser side** is [Tessera](https://github.com/Ignibyte/tessera): server HTML morphed into
  the page by region and component, over responses and Server-Sent Events.
- **Start an app** with `composer create-project ignibyte/aic-project my-app` once the packages
  are on Packagist; until then aic-demo requires them from a checkout of this repository by path.
  [project/README.md](project/README.md) walks a created app from install to a compiled site.

## The repositories

| Repository | What it is |
|---|---|
| [Ignibyte/aic-nextgen](https://github.com/Ignibyte/aic-nextgen) (this one) | The framework (`framework/`, `ignibyte/aic-framework`), the gate suite (`gates/`, `ignibyte/aic-gates`), the agent workflow (`workflow/`, `ignibyte/aic-workflow`), the Composer plugin that writes an app's root files (`scaffold/`, `ignibyte/aic-scaffold`), the template `composer create-project` copies (`project/`, `ignibyte/aic-project`), and the fixture app at the root that the gates hold |
| [Ignibyte/aic-demo](https://github.com/Ignibyte/aic-demo) | Lotline, the demo site. Start here to see an app built on AIC, with install steps |
| [Ignibyte/tessera](https://github.com/Ignibyte/tessera) | The browser runtime |
| [Ignibyte/aic_compiler](https://github.com/Ignibyte/aic_compiler) | The elephc fork |

## This repository

The root is AIC's fixture app, the site every gate holds to code: four modules under
`modules/`. `site` has the home page, greetings, jobs, schedules and notifications,
Server-Sent Events, downloads, `/api/status` and `/health`. `note` has notes with CRUD,
templates and signed forms, and stock items with a date, a price and a flag. `account` has
sign-in, sessions and uploads. `scaffold` is what the `bin/aic make:*` commands make (pages, an
API, components, a block, a command, a job and a migration), held byte for byte by gate:31. The
compiled binary serves the app, and PHP serves the same app as the reference.

**Architecture:** [framework/docs/README.md](framework/docs/README.md), the pages an app's agent
reads before building on AIC (the kinds, the modules, the systems, the conventions, the gates).
They ship in `ignibyte/aic-framework`, so an app finds them at `vendor/ignibyte/aic-framework/docs/`.

## How the repository is laid out

```text
aic-nextgen/
├── framework/                  ignibyte/aic-framework, the package an app requires
│   ├── src/                    AIC\ … one namespace per system
│   │   ├── Http/ Routing/ Controller/ Kernel/ Sapi/ Config/ Console/ Profiler/
│   │   ├── Container/ Module/
│   │   ├── Database/ Schema/ Entity/ Storage/
│   │   ├── Component/          components, the Renderer, the compiled atom templates
│   │   ├── Middleware/ Block/ Tessera/ Form/ Session/ Secret/ Auth/
│   │   └── Queue/ Schedule/ Notification/
│   │       each system is split by layer: Contracts/ Domain/ Application/ Infrastructure/
│   ├── templates/
│   │   ├── components/         the framework's atoms (link, form, table, tabs, …) as .phtml
│   │   └── tessera/            how regions, markers and fragment answers are framed
│   └── docs/                   the architecture pages an app's agent reads
├── modules/                    the fixture app's modules
│   └── note/
│       ├── note.module.php     the manifest: routes, jobs, migrations, what it exports
│       └── src/                App\Note\ … one folder per kind
│           ├── Controller/     Page, Api and Stream entry points
│           ├── Command/ Job/ Block/ Form/ Migration/
│           └── Entity/ Repository/ Service/
├── themes/
│   └── app/                    the theme config/app.php names: all markup lives here
│       ├── layouts/            html.phtml, page.phtml and named variants
│       ├── components/         one folder per component, its .phtml template
│       │   └── NoteList/note-list.phtml
│       ├── src/                the PHP behind the templates
│       │   ├── Layouts/Page.php            the page's regions
│       │   └── Components/NoteList/        the component class and its values
│       └── assets/             CSS, images, fonts: published by the build
├── config/                     typed settings, one file per concern
├── bootstrap/serve.php         serves one request, under PHP or compiled
├── public/index.php            the PHP entry; nothing else is public
├── bin/aic                     the console
├── build/                      written by `composer run build`, never by hand
│   ├── generated/              the wiring and the escaped templates
│   ├── assets/                 the published files, fingerprinted
│   └── surface.json            every route, command, job and schedule, with its kind
├── docs/wiki/                  the code wiki, written by the build
├── tests/browser/              Playwright looks the gates keep running
├── gates/                      ignibyte/aic-gates: the 33 quality gates (dev only)
├── workflow/                   ignibyte/aic-workflow: the agent workflow engine (dev only)
├── scaffold/                   ignibyte/aic-scaffold: the Composer plugin that writes an app's root files
├── project/                    ignibyte/aic-project: the template a new app starts from
├── deploy/frankenphp/          a Caddyfile and container for running under FrankenPHP
└── .ddev/                      the local stack: nginx and php-fpm, Postgres 17
```

### What each part is for

- **`framework/`** is AIC itself, the `ignibyte/aic-framework` package. An app requires it and finds it in
  `vendor/ignibyte/aic-framework/`. Each system has its own namespace under `src/`, and every namespace has
  the same layers:
  - `Contracts/`: interfaces and values other code may depend on;
  - `Domain/`: plain typed values and rules;
  - `Application/`: the code that does the work;
  - `Infrastructure/`: the adapters to PHP and the outside world.

  gate:12 holds who may use whom. `templates/components/` holds the framework's own atoms: small
  components such as links, forms, tables and tabs that any page or theme can use. The build
  compiles them into `src/Component/Application/Templates/`. `docs/` is written for the agent
  building an app; start at [framework/docs/README.md](framework/docs/README.md).

- **`modules/`** is the app. A module is a folder with a manifest, `<name>.module.php`, that
  declares everything the module brings: routes, commands, jobs, schedules, migrations, middleware
  and what it exports to other modules. Its classes sit in `src/`, one folder per kind. Controllers
  are the five entry kinds (Page, Api, Stream, Command, Job); services, entities, repositories and
  forms sit beside them. A module holds no HTML: gate:32 refuses it.

  The fixture app has four modules:
  - `site`: the home page, greetings, jobs, schedules, events and downloads;
  - `note`: notes and stock;
  - `account`: sign-in, sessions and uploads;
  - `scaffold`: what the `bin/aic make:*` commands write.

- **`themes/<theme>/`** owns all markup. Templates are on top and the PHP behind them in `src/`:
  - `layouts/` holds the document (`html.phtml`) and the page (`page.phtml`, plus named variants a
    controller can ask for);
  - `components/<Name>/` holds each component's template;
  - `src/Components/<Name>/` holds its class, a plain typed value the controller fills;
  - `src/Layouts/Page.php` declares the page's regions;
  - `assets/` holds every public file, its stylesheets bundled in path order;
  - `frontend/` (optional) holds a CSS build's sources.

  A template is restricted PHP: gate:25 allows printing values, conditions and loops, and no
  function calls. The build escapes every `<?= ?>` by its type.

- **`config/`** holds one typed settings object per file: the app (`app.php`, which names the theme
  and pins Tessera), the database, mail, the app secret, the profiler. Environment variables are
  read here and nowhere else.

- **`bootstrap/serve.php`** serves one request. Under PHP it runs behind `public/index.php`. For
  the native build it is compiled with the classes into one HTTP server. Both serve the same code.

- **`build/`** is everything `composer run build` writes from the manifests, the theme and the
  framework: the wiring as plain PHP, the escaped templates, the published assets and
  `surface.json`. It is committed, so a deploy needs no build step. gate:24 fails when it is stale.
  Never edit it by hand.

- **`gates/`** and **`workflow/`** are development packages:
  - `ignibyte/aic-gates` runs the quality suite (`composer run gate`; `vendor/bin/aic-gate --list` names
    each gate);
  - `ignibyte/aic-workflow` is the engine an agent works tickets through (`bin/aic workflow:run`), with the
    code index and the inspect commands (`bin/aic route:list`, `code:search`, …).

  Neither ships with an app's runtime.

- **`scaffold/`** is `ignibyte/aic-scaffold`, a Composer plugin. On every install and update it
  writes the root files the installed `ignibyte/aic-*` packages declare in `extra.aic-scaffold`:
  the framework's `public/index.php`, `bootstrap/serve.php` and `bin/aic` (rewritten when they
  change), five `config/*.php` starters (written once) and its `.gitignore` lines (appended), and
  then it runs the gates' `aic-gate init`. The framework's copies live in `framework/scaffold/`;
  edit them there. `scaffold/README.md` has the declaration format and the root's overrides.

- **`project/`** is `ignibyte/aic-project`, what `composer create-project` copies: an app's
  `composer.json`, one `site` module (a home page, `/health`, `db:ping`), the `app` theme,
  `config/native-site.json`, DDEV's config and a README. The rest of an app's root files are
  written on install by their owners. AIC's gates read its classes as another app's: gate:26 holds
  `project/modules/` and `project/themes/` to the same placement rules as the root's.

- **`tests/browser/`** holds Playwright looks at pages, kept as regression checks: gate:23 runs
  them against the compiled site. AIC writes no other tests; its checks are the gates.

- **`deploy/`** and **`.ddev/`** hold the ways to run it: FrankenPHP for production, DDEV for local
  work.

When one page and one component meet, the path looks like this: the `GET /notes` route
(`modules/note/note.module.php`) reaches `App\Note\Controller\NoteListPage`. Its service,
`App\Note\Service\NoteViews`, fills `App\Theme\Components\NoteList\NoteList` with the notes, and
the controller places it in the page's `content` region. The Renderer then prints it through `themes/app/components/NoteList/note-list.phtml`, which
the build escaped into `build/generated/templates/`.

## Bring it up

You need PHP 8.5, Composer 2, DDEV 1.24.10 or later, and a user in the `docker` group.

```bash
composer install
printf 'web_environment:\n  - AIC_APP_SECRET=%s\n' "$(openssl rand -hex 32)" > .ddev/config.local.yaml
ddev start
```

The app secret signs envelopes and comes only from the environment (`AIC_APP_SECRET`, 32 bytes or
more); the app does not boot without it. DDEV keeps `.ddev/config.local.yaml` out of git, and
gate:23 makes a fresh secret for each run.

Invalid typed configuration returns a plain HTTP 500 at boot. The console prints the failure
without a stack trace and exits 1. Method exception contracts remain checked by ordinary Psalm;
the reason for disabling its incompatible global-scope check is beside the setting (#133).
Gate 13 runs ordinary analysis and then explicit taint analysis over the full project. Both
results and complete output are reported, and either failure fails the gate. PHPStan level 10
holds return types; the approved Psalm return-type policy is recorded in both configs.

`ddev start` prints the URL of the interpreted reference, `https://aic-nextgen.ddev.site` on
DDEV's default router ports. `ddev exec bin/aic migrate` creates the tables. To run the app under
FrankenPHP instead, see [framework/docs/deploy.md](framework/docs/deploy.md) and
`deploy/frankenphp/`.

The compiled site needs AIC's elephc fork: `ELEPHC_BIN`, or the first `elephc` on `PATH`. Its
native packages (pcre2, oniguruma) are cached in `ELEPHC_NATIVE_CACHE`, by default
`~/.cache/elephc-native`:

```bash
composer run native:serve        # build the compiled site and serve it on http://127.0.0.1:8090
composer run native:serve 9000   # on another port
ddev exec bin/aic migrate        # the console, inside DDEV
ddev psql                        # the Postgres 17 database
ddev exec bin/aic queue:work     # run the due jobs, then exit
ddev exec bin/aic schedule:run   # queue the schedules due this minute
```

In production a systemd timer (or cron) runs `bin/aic schedule:run` every minute, and a
service that restarts runs `bin/aic queue:work`. A second `schedule:run` in the same minute
queues nothing more. Notifications go out through the queue: DDEV's Mailpit catches the mail
(`ddev mailpit`), and `AIC_MAIL_HOST`, `AIC_MAIL_PORT` and `AIC_MAIL_FROM` point it elsewhere.

Every stylesheet, script and image lives in the theme's `assets/`. `composer run build` writes one
`build/assets/aic.css` (the theme's CSS, in path order) and copies the rest to
`build/assets/themes/<name>/`; the build output is committed. AIC's asset handler serves
`build/assets/` at `/assets/` before the app is assembled, under php-fpm and compiled alike; in
production a reverse proxy (Caddy, nginx) may map `/assets/` to `build/assets/` directly.

## Quality gates

```bash
vendor/bin/aic-gate tools   # once: gitleaks (pinned, into tools/), semgrep, shellcheck
composer run gate           # every gate, on the host; ends with one gate-summary line
composer run gate:8         # one gate alone (gate:0 to gate:32)
composer run gate:21        # the native gate: every runtime class through elephc
```

The gates combine PHPStan level 10, both full Psalm passes, style, architecture and security
checks with native class probes and fresh whole-site comparisons against PHP.
Runtime, gate-tooling and workflow source repairs are complete (#133–#135). Gate:13 now requires
both full-project Psalm passes to succeed. A failing ordinary result cannot be hidden by a
clean taint result, and either process error fails the gate.
A component's template (`*.phtml`, in the theme's `components/<Name>/`) is plain PHP read only; gate:25
holds it to that grammar, and the class tools skip templates. `composer run build` escapes it into `build/generated/templates/`, which
every gate checks, and writes the lookup the Renderer finds each component's template through: a
page is components, and only the Renderer turns them into HTML.
The native gate compiles every runtime class with [elephc](https://github.com/illegalstudio/elephc),
runs it and compares the output with PHP; it needs AIC's fork of the compiler, found as for the
compiled site above.
There are no baselines and no suppressions. The gate code lives in `gates/` and never ships with
the framework. `composer install` also installs the git hooks, which hold commit subjects to
Conventional Commits.

## The workflow in an app

An app that requires `ignibyte/aic-workflow` for development can select its client explicitly:

```bash
composer require --dev ignibyte/aic-workflow:0.1.x-dev
bin/aic workflow:init --client=codex
```

Codex gets `.agents/skills/`, its native `aic-seeker` role and hooks under `.codex/`, a committed
`.codex/aic-workflow.lock`, and managed `AGENTS.md` instructions. The operator reviews the folder
and exact hook definitions through Codex `/hooks`, then checks readiness from the operator
terminal before launching a fresh session:

```bash
bin/aic workflow:codex-check
```

The check uses the actual client's trusted, enabled callback metadata and the installed pack's
ownership. It can fail inside a tool sandbox that cannot write client state, so perform it
before launch. Start Codex at the app root. Its inspected shell commands run there without
interactive child input; use an explicit `cd` in the command for another directory. The native
seeker takes the engine's scope, and its unchanged actual return binds the review receipt.
Client callback startup failures can still fail open; see [workflow installation and limits](workflow/README.md).

Omitting `--client` keeps Claude Code as the default; `--client=claude` is its explicit form.
It installs `.claude/` skills, commands, agent and hooks with the existing Stop cap. Both clients
write `config/workflow.php` once and the ticket format. Init preserves unrelated hooks and
edited owned files, upgrades unchanged files, and allows reviewed `--force[=<path>]` only from
the operator terminal. Commit what it writes, including the selected client's lock. Set `site`
to the app's URL, write a ticket and start it with `bin/aic workflow:run <number>`.

This repository holds the package's own source, so init refuses here. Its maintainer phases
remain manual; installing an app pack does not retrofit hooks into this maintainer session.

The gates come the same way: `composer require --dev ignibyte/aic-gates:0.1.x-dev`, then
`vendor/bin/aic-gate init`, which writes the tools' root configs and prints the scripts and plugins
for composer.json. Write gate:23's requests in `config/native-site.json`, then `composer run gate`
runs every gate an app has (all but 22, 28, 29 and 31). This repository's own root configs are its
own, so this init refuses here too.

## Working on AIC

`CONSTITUTION.md` holds the binding rules. Work goes through four phases (Plan, Code, Quality
checks, Documentation) with the Claude Code commands in `.claude/commands/` (`/work`,
`/pipeline:*`, `/commit`) and the hooks in `.claude/hooks/`. `CHANGELOG.md` records what each
ticket delivered.

Maintainers keep the plan and the tickets in a private checkout at `docs/planning/`, which this
repository ignores; `(#N)` in the docs and the changelog names one of those tickets.

## License

MIT (`LICENSE`), for the app and for each package.
