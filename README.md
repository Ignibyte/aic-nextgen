# AIC nextgen

The fresh start of AIC (Absolute Insanity, AI): a slim, opinionated PHP 8.5 framework that
compiles natively with elephc and runs as the compiled `elephc --web` server, one `aic/framework`
package with a namespace per system. It is being
built by porting what earns its place from old AIC (`Ignibyte/aic`) and will replace it at
cutover.

So far the demo app has a placeholder page, a greeting form, `/api/status`, a download and
notes with CRUD on Postgres, served by the compiled binary and, as the reference, by PHP.

## Layout

| Path | Holds |
|---|---|
| `framework/` | `aic/framework`, namespace `AIC\` under `framework/src/`, pulled in through a composer path repository |
| `app/` | The demo app, `aic/app`, namespace `App\` |
| `bootstrap/serve.php` | Serves one request: the wiring, the request in, the response out; compiled into the site or run under PHP |
| `public/index.php` | The interpreted entry (php-fpm in DDEV, `php -S`): the autoloader, then `serve.php` |
| `bin/aic` | The console: `bin/aic <command> [arguments]` (`migrate`, `migrate:status`, `db:ping`, `greet`); with no command it lists them. Run database commands inside DDEV: `ddev exec bin/aic migrate` |
| `bin/aic-build` | The build step for the build-time manifests (`composer run build`); nothing to build yet |
| `.ddev/` | DDEV: nginx-fpm serving the interpreted reference, and Postgres 17 |

## Bring it up

You need PHP 8.5, Composer 2, DDEV 1.24.10 or later, and a user in the `docker` group.

```bash
composer install
ddev start
```

`ddev start` prints the URL of the interpreted reference. On the Z690 dev box it is
`https://aic-nextgen.ddev.site:8843` (the router's ports there are 8880 and 8843).

The compiled site needs the elephc fork (`/srv/stacks/elephc`, or `ELEPHC_BIN`):

```bash
composer run native:serve        # build the compiled site and serve it on http://127.0.0.1:8090
composer run native:serve 9000   # on another port
ddev exec bin/aic migrate        # the console, inside DDEV
ddev psql                        # the Postgres 17 database
```

## Quality gates

```bash
bash bin/install-tools.sh   # once: gitleaks (pinned, into tools/), semgrep, shellcheck
composer run gate           # every gate, on the host; ends with one gate-summary line
composer run gate:8         # one gate alone (gate:0 to gate:21)
composer run gate:21        # the native gate: every runtime class through elephc
```

The gates are static analysis only: PHPStan level 10, Psalm with taint analysis, PHPMD, PHPCS,
Pint, Rector, Deptrac and phpat for the architecture, semgrep, gitleaks and the composer checks.
The native gate compiles every runtime class with [elephc](https://github.com/illegalstudio/elephc),
runs it and compares the output with PHP; it needs the compiler checkout at
`/srv/stacks/elephc` (or `ELEPHC_BIN`).
There are no baselines and no suppressions. The gate code lives in `gates/` and never ships with
the framework. `composer install` also installs the git hooks, which hold commit subjects to
Conventional Commits.

## Working on AIC

`CONSTITUTION.md` holds the binding rules. Work goes through four phases (Plan, Code, Quality
checks, Documentation) with the Claude Code commands in `.claude/commands/` (`/work`,
`/pipeline:*`, `/commit`) and the hooks in `.claude/hooks/`. `CHANGELOG.md` records what each
ticket delivered.

Maintainers keep the plan and the tickets in a private checkout at `docs/planning/`, which this
repository ignores.
