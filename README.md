# AIC nextgen

The fresh start of AIC (Absolute Insanity, AI): a slim, opinionated PHP 8.5 framework that
compiles natively with elephc and runs as the compiled `elephc --web` server, one `aic/framework`
package with a namespace per system. It is built by porting what earns its place from old AIC
(`Ignibyte/aic`). AIC stays in `Ignibyte/aic-nextgen`; old AIC is the archive.

The root is AIC's fixture app, the site every gate holds to code: four modules under
`modules/`. `site` has the home page, greetings, jobs, schedules and notifications,
Server-Sent Events, downloads, `/api/status` and `/health`. `note` has notes with CRUD,
templates and signed forms, and stock items with a date, a price and a flag. `account` has
sign-in, sessions and uploads. `scaffold` is what the `bin/aic make:*` commands make (pages, an
API, components, a block, a command, a job and a migration), held byte for byte by gate:31. The
compiled binary serves the app, and PHP serves the same app as the reference. The ten-module demo it was cut from is the tag `demo-reference`.
The proof app will be Lotline, built fresh in `/srv/stacks/aic-demo` with the packaged workflow and gates (#128, #132).

**Architecture:** [framework/docs/README.md](framework/docs/README.md), the pages an app's agent
reads before building on AIC (the kinds, the modules, the systems, the conventions, the gates).
They ship in `aic/framework`, so an app finds them at `vendor/aic/framework/docs/`.

## Layout

| Path | Holds |
|---|---|
| `framework/` | `aic/framework`, namespace `AIC\` under `framework/src/`, its architecture pages in `framework/docs/`, pulled in through a composer path repository |
| `modules/` | The fixture app, `aic/app`: four modules, `modules/<name>/<name>.module.php` (its manifest) and `src/` (`App\<Name>\`) |
| `generated/` | The wiring `composer run build` writes from the manifests (`App\Generated\`); never edited by hand |
| `docs/wiki/` | The code wiki `composer run build` writes from the surface and the code index: an index and a page per module; never edited by hand (notes go in `modules/<m>/README.md`) |
| `templates/layouts/` | The page layouts' templates |
| `bootstrap/serve.php` | Serves one request: the wiring, the request in, the response out; compiled into the site or run under PHP |
| `public/index.php` | The interpreted entry (php-fpm in DDEV, `php -S`): the autoloader, then `serve.php` |
| `bin/aic` | The console: `bin/aic <command> [arguments]` (`migrate`, `migrate:status`, `db:ping`, `greet`); with no command it lists them. Run database commands inside DDEV: `ddev exec bin/aic migrate` |
| `gates/` | `aic/gates`, the gate suite (#116): a dev-only package with every gate's code and entry, the runner, the build (`vendor/bin/aic-build`, behind `composer run build`; `--check` is gate:24) and each tool's base config. `vendor/bin/aic-gate --list` names the gates and `aic-gate N` runs one |
| `build/surface.json` | The app's surface: every route (method, path, handler, kind) and console command (name, class, kind) |
| `workflow/` | `aic/workflow`, the agent workflow's engine (`AIC\Workflow\`): a dev-only package. `bin/aic workflow:run <ticket>` runs one phase of a ticket per call; `workflow:status` shows the run; `workflow:reset` is the operator's, from a terminal. At each phase exit the engine runs the gates and the spec's audits itself (#101) and keeps the verdicts in `var/workflow/evidence.sqlite`. `workflow/bin/aic-guard.sh`, wired by `workflow/pack/hooks.json`, is the Claude Code hook that holds the agent to the open run (#102). House rules (`workflow/rules/`, #104) judge each run's change from files frozen at begin; gate:28 holds them to their probes. `bin/aic code:tree`, `code:search`, `code:refs` and `code:index` answer from the code index in `var/index/code.sqlite` (#106). Plan cannot pass until its grounding check finds every declared path and class real and the run's own lookups show the agent looked (#118). `bin/aic route:list`, `command:list`, `job:list`, `schedule:list`, `module:list`, `module:show <name>` and `kind:list` answer what the app declares from `build/surface.json`, as text or with `--json` (#109). `bin/aic make:module`, `make:page`, `make:api`, `make:component`, `make:block`, `make:command`, `make:job` and `make:migration` write a new class that passes every gate and add its manifest call (#122). `bin/aic workflow:probe` replays the engine's planted cases in throwaway apps, from `workflow/probes/sweep/`, and exits 0 only when every expectation held (#119). `bin/aic workflow:init` installs it in an app (#105; `workflow/pack/`). Once per run, at code, an independent `aic-seeker` subagent checks the run's change against the AIC checklist; `bin/aic workflow:seeker-brief` gives it its scope (the diff plus one hop) and `bin/aic workflow:seeker-report` files its ledger, whose findings are advisory (#103, #127). Its state is `var/workflow/` (gitignored) |
| `.ddev/` | DDEV: nginx-fpm serving the interpreted reference, and Postgres 17 |

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

`ddev start` prints the URL of the interpreted reference. On the Z690 dev box it is
`https://aic-nextgen.ddev.site:8843` (the router's ports there are 8880 and 8843).

The compiled site needs the elephc fork (`/srv/stacks/elephc`, or `ELEPHC_BIN`):

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

The site theme lives in `assets/`, and each module's styles, scripts and images in its own
`modules/<name>/assets/`. `composer run build` writes one `public/assets/aic.css` (the modules' CSS,
then the theme) and copies the rest to `public/assets/<module>/`; the build output is committed. DDEV's nginx serves `public/` as static files. The compiled server
serves no static files, so in production a reverse proxy (nginx, Caddy) serves `public/` and
forwards the rest. Without a proxy, the app's `/assets/{file}` route answers the built assets.

## Quality gates

```bash
vendor/bin/aic-gate tools   # once: gitleaks (pinned, into tools/), semgrep, shellcheck
composer run gate           # every gate, on the host; ends with one gate-summary line
composer run gate:8         # one gate alone (gate:0 to gate:31)
composer run gate:21        # the native gate: every runtime class through elephc
```

The gates are static analysis only: PHPStan level 10, both ordinary and taint Psalm analysis, PHPMD, PHPCS,
Pint, Rector, Deptrac and phpat for the architecture, semgrep, gitleaks and the composer checks.
Runtime, gate-tooling and workflow source repairs are complete (#133–#135). Gate:13 now requires
both full-project Psalm passes to succeed. A failing ordinary result cannot be hidden by a
clean taint result, and either process error fails the gate.
A component template (`*.html.php`) is plain PHP read only; gate:25 holds it to that grammar, and
the class tools skip it. `composer run build` compiles it into a class beside its component, which
every gate checks, and writes the lookup the Renderer finds each component's template through: a
page is components, and only the Renderer turns them into HTML.
The native gate compiles every runtime class with [elephc](https://github.com/illegalstudio/elephc),
runs it and compares the output with PHP; it needs the compiler checkout at
`/srv/stacks/elephc` (or `ELEPHC_BIN`).
There are no baselines and no suppressions. The gate code lives in `gates/` and never ships with
the framework. `composer install` also installs the git hooks, which hold commit subjects to
Conventional Commits.

## The workflow in an app

An app built on aic/framework gets the gated workflow with one command, once `aic/workflow` is in its
`require-dev` (`composer require --dev aic/workflow:0.1.x-dev`):

```bash
bin/aic workflow:init
```

It writes the agent's skills and commands into `.claude/` (`aic-workflow-*`,
`/aic:workflow:start`) and the `aic-seeker` agent, `config/workflow.php` (once; every setting written out with its comment),
`tickets/README.md` (the ticket format), a block in `AGENTS.md` and an `@AGENTS.md` import in
`CLAUDE.md`, and the guard's hooks with the Stop cap in `.claude/settings.json`. It ends with the
settings as the engine reads them. Commit what it wrote, `.claude/aic-workflow.lock` included:
the lock is how the next init tells your edits from its own writes. Run it again after upgrading
the package: unedited files are upgraded, edited ones kept, and only the operator's
`--force[=<path>]` from a terminal takes the shipped bytes back. Then set `site` to the app's URL,
write a ticket and start it with `bin/aic workflow:run <number>`.

This repository holds the package's own source, so init refuses to run here.

The gates come the same way: `composer require --dev aic/gates:0.1.x-dev`, then
`vendor/bin/aic-gate init`, which writes the tools' root configs and prints the scripts and plugins
for composer.json. Write gate:23's requests in `config/native-site.json`, then `composer run gate`
runs every gate an app has (all but 22, 28 and 29). This repository's own root configs are its
own, so this init refuses here too.

## Working on AIC

`CONSTITUTION.md` holds the binding rules. Work goes through four phases (Plan, Code, Quality
checks, Documentation) with the Claude Code commands in `.claude/commands/` (`/work`,
`/pipeline:*`, `/commit`) and the hooks in `.claude/hooks/`. `CHANGELOG.md` records what each
ticket delivered.

Maintainers keep the plan and the tickets in a private checkout at `docs/planning/`, which this
repository ignores.
