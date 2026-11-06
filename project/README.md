# An AIC app

This app was made with `composer create-project ignibyte/aic-project`. It has one module,
`site` (a home page, a health check and `db:ping`), and one theme, `app`. It also has AIC's
gate suite and agent workflow for development. AIC compiles the whole site into one native
binary with elephc, and DDEV runs the same code under PHP as the reference.

The framework's own docs are in `vendor/ignibyte/aic-framework/docs/`, starting at its
`README.md`. Issues and questions go to
[Ignibyte/aic-nextgen](https://github.com/Ignibyte/aic-nextgen/issues).

## What you need

- PHP 8.5 CLI with `pdo_pgsql`, and Composer 2.
- Postgres 17. DDEV gives you one (`.ddev/config.yaml`), and any other Postgres works through
  the `PG*` variables.
- A C toolchain (an assembler, a linker and `cc`; `build-essential` on Debian and Ubuntu), with
  the bzip2 and zlib development libraries. The compiler links the site with them.
- On Linux x86_64, `aic-gate tools` installs AIC's compiler, its fork of elephc. Anywhere else,
  build [Ignibyte/aic_compiler](https://github.com/Ignibyte/aic_compiler) from source and put
  `elephc` on `PATH` or name it in `ELEPHC_BIN`.
- `curl`, which the build uses to fetch the Tessera release that `config/app.php` pins.

## From create-project to a compiled site

```sh
composer create-project ignibyte/aic-project my-app
cd my-app
git init
composer install
```

The first install writes the app's root files. `ignibyte/aic-scaffold` copies the framework's
`public/index.php`, `bootstrap/serve.php`, `bin/aic`, five `config/*.php` starters and its
`.gitignore` lines. Then `aic-gate init` writes the tools' configs. The gates read the files
git lists, and the second install, run inside the new repository, adds the commit hooks.
Every later `composer install` or `update` keeps the framework's entry files current and
leaves your config files alone.

Start the database and give the app its secret (32 bytes or more, from the environment only):

```sh
printf 'web_environment:\n    - AIC_APP_SECRET=%s\n' "$(openssl rand -hex 32)" > .ddev/config.local.yaml
ddev start
export PGHOST=127.0.0.1 PGPORT="$(ddev describe -j | jq -r '.raw.dbinfo.published_port')"
export PGUSER=db PGPASSWORD=db PGDATABASE=db
export AIC_APP_SECRET="$(sed -n 's/.*AIC_APP_SECRET=//p' .ddev/config.local.yaml)"
```

Build, migrate and run the gates:

```sh
composer run build      # the wiring, the templates and the assets, into build/
bin/aic migrate         # the framework's tables on an empty database
vendor/bin/aic-gate tools
composer run gate       # every gate; it ends with one gate-summary line
```

`aic-gate tools` installs gitleaks, semgrep and shellcheck, which three gates run. It also
installs the compiler release the gates pin, checked by its SHA-256, and links it at
`tools/elephc`. `composer run gate` compiles the site, serves it beside PHP and compares each step in `config/native-site.json`.
Its last line reads `gate-summary: 29 passed, 0 deferred. Full coverage.`

Serve the compiled site:

```sh
composer run native:serve    # http://127.0.0.1:8090/
```

DDEV serves the same pages under PHP at the URL `ddev describe` prints.

## The agent workflow

```sh
bin/aic workflow:init
```

This installs the agent's skills, commands and hooks, `config/workflow.php` and `tickets/`.
Commit what it writes. Set `site` in `config/workflow.php` to the app's URL, write a ticket
in `tickets/`, and start it with `bin/aic workflow:run <number>`. The framework's
`docs/README.md` has the rest.

## Where things go

- `modules/<name>/`: a module's manifest (`<name>.module.php`) and its classes under `src/`,
  one folder per kind: `Controller/`, `Command/`, `Job/`, `Service/` and the rest. Add a
  module's PSR-4 line to `composer.json`, then `composer dump-autoload`. The build prints the
  line when it is missing.
- `themes/app/`: the layouts, the components (each a `.phtml` with its class under `src/`) and
  `assets/`. Markup lives only here.
- `config/`: the app's settings, one typed object per file. `native-site.json` lists the
  requests gate:23 compares; a new GET route needs a step there.
- `build/`: what `composer run build` writes. Commit it, and never edit it by hand.
