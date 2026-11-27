# Interpreted workers

A library with no native path goes one of two ways. When a request waits on it, it goes native: a
compiler bridge or the app's own PHP. When it is queued work (document generation, an LLM agent
library), it goes to an interpreted worker: PHP code beside the compiled site that never compiles.
A worker is the one place such a library may be required.

## The folder

```text
workers/<name>/
    composer.json   # app/worker-<name>: its own require, PSR-4 Worker\<Name>\ → src/
    README.md
    src/            # Worker\<Name>\: Jobs in src/Job/, and the classes they use
    vendor/         # its own install, ignored by git
```

`bin/aic make:worker <name>` writes the manifest and the README. The name follows a module's rule
(lower-case words joined by `_`). It prints the two lines the root needs and does not write them:

- in the root `composer.json`'s `autoload-dev.psr-4`, `"Worker\\<Name>\\": "workers/<name>/src/"`,
  then `composer dump-autoload`, once `src/` holds a class: PHPStan refuses an autoload path that is
  not there;
- in `phpcs.xml.dist`'s `rootNamespaces`, `<element key="workers/<name>/src" value="Worker\<Name>"/>`.

The root maps a worker in `autoload-dev`, never `autoload`, so the root's runtime require gates never
read what a worker requires. A worker's `require` is its own: `ignibyte/aic-framework` and the
libraries it exists for.

## What the gates read

Every static gate reads `workers/` as it reads `modules/`: Pint, Rector, PHPStan, phpat, PHPMD,
PHPCS, Deptrac, Psalm, phpcpd, semgrep and gitleaks. gate:1 normalizes each worker's
`composer.json`, and gate:0 audits each worker that has a `composer.lock`, by its lock. Each tool
skips `workers/*/vendor/`.

These gates do not read a worker: gate:21 and gate:23, which compile the site (a worker never
compiles), gate:16 (a dynamic `require` is a native hazard), and gates 2 to 4, which judge the root's
own `require`.

## The boundary

A worker shares only the queue, Postgres and private files with the site.

- Deptrac's `Worker` layer may use the framework's Contracts, Domain, Exception, Attribute and
  Application layers and PSR. No other layer lists it, so a module, theme or framework class that
  names a worker class fails gate:12, as does a worker class that names a module's.
- phpat (gate:9) refuses a `Worker\` class that implements Page, Api, Stream or Command: a worker is
  never routed and never run from the console, so its one kind is the Job.
- gate:26 admits `workers/` beside `modules/` and the theme.

## Deploying

A worker installs from its own folder, `composer install --no-dev` in `workers/<name>/`, with the
same Postgres variables as the site ([deploy.md](deploy.md#what-every-deploy-sets)). Nothing hands a
worker queued work yet: the framework's queue runs only the site's jobs, so a worker's Jobs have no
runner, and the gates refuse a Job nothing builds as dead code.
