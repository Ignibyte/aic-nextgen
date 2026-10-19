# AIC nextgen

The fresh start of AIC (Absolute Insanity, AI): a slim, opinionated PHP 8.5 framework on the
FrankenPHP worker runtime, one `aic/framework` package with a namespace per system. It is being
built by porting what earns its place from old AIC (`Ignibyte/aic`) and will replace it at
cutover.

So far the workspace serves a placeholder page from the FrankenPHP worker. The kernel arrives
with ticket #4.

## Layout

| Path | Holds |
|---|---|
| `framework/` | `aic/framework`, namespace `AIC\` under `framework/src/`, pulled in through a composer path repository |
| `app/` | The demo app, `aic/app`, namespace `App\` |
| `public/worker.php` | The FrankenPHP worker: booted once, then called once per request |
| `bin/aic` | The console: `bin/aic <command> [arguments]` (`migrate`, `migrate:status`, `db:ping`, `greet`); with no command it lists them. Run database commands inside DDEV: `ddev exec bin/aic migrate` |
| `bin/aic-build` | The build step for the build-time manifests (`composer run build`); nothing to build yet |
| `.ddev/` | DDEV: FrankenPHP as the web server (the `ddev/ddev-frankenphp` add-on) and Postgres 17 |

## Bring it up

You need PHP 8.5, Composer 2, DDEV 1.24.10 or later, and a user in the `docker` group.

```bash
composer install
ddev start
```

`ddev start` prints the URL. On the Z690 dev box it is `https://aic-nextgen.ddev.site:8843`: the
host's nginx owns 80 and 443, so the DDEV router listens on 8880 and 8843, and
`.ddev/config.zz-router-ports.yaml` puts the site on those ports. On another machine, change
the two ports in that file to your router's (`router_http_port` and `router_https_port` in
`~/.ddev/global_config.yaml`).

The page reports `Worker mode: yes` and when the worker booted. Reload it: the boot time stays
the same, because one long-lived worker serves request after request.

```bash
composer run build   # write the build-time manifests (none yet)
ddev psql            # the Postgres 17 database
ddev logs -f         # FrankenPHP's log
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
