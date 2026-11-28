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
    bin/aic.php     # its runner, made with its first job
    src/            # Worker\<Name>\: Jobs in src/Job/, and the classes they use
        Generated/  # <Name>Jobs.php, written by composer run build
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
libraries it exists for. The root's `require-dev` lists each of those libraries at the same version,
so the analysers resolve them from the root's install and the runner can use it in development.

## Jobs and the runner

`bin/aic make:job <worker> <Class> --worker` writes a Job in `src/Job/`, and with the worker's first
job its runner, `bin/aic.php`. A worker has no manifest: `composer run build` reads every class in
`src/Job/` that implements Job, names each by its class (`StatementJob` is `statement`) and writes
`src/Generated/<Name>Jobs.php`, an `AIC\Queue\Domain\WorkerJobs`. A job's constructor may take only
what a worker shares with the site: `Database`, `PostgresQueue` and `PrivateFiles`. The build
refuses any other parameter, and gate:24 holds the generated class fresh.

`php workers/<name>/bin/aic.php queue:work [max]` runs the worker's jobs, interpreted, through
`AIC\Queue\Application\WorkerKernel`. It loads the worker's own `vendor/autoload.php`, or the app's
when the worker has no install, reads the app's `config/database.php`, and takes only the worker's
own queue, named for the worker.

## Queueing work for a worker

The site never names a worker's class. A module queues by the worker's name and the job's:

```php
$id = $postgresQueue->dispatchTo('report', 'statement', $statementOrder->values(), 0);
```

The payload is strings: a value class's `values()` on the site, each field read with
`JobInput::value()` in the worker. The job finds its own id in `JobInput::$id`. A result is a
Postgres row the module reads, or a file the job keeps in `PrivateFiles` under its id, which a
module's Stream sends with `download()` (404 until it is written). The page that queued the job
reads its progress with `QueueRecords::state($id)`: Queued, Running, Failed, or Done once no record
holds it.

The fixture is the `report` worker, which makes a statement PDF with FPDF, and the `statement`
module: `/statements` queues an order, `/statements/{id}` shows its job's state and
`/statements/{id}/pdf` sends the file. FPDF stamps each PDF with the time it runs, so the job
replaces that stamp with the statement's own date and the same order makes the same bytes. gate:23
runs a worker between steps with `"work": "<worker>"`.

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
- `WorkerVendor` holds every class in no other layer and no PHP built-in: a worker's library.
  Only `Worker` may use it, so a module that names FPDF fails gate:12.
- PHPCS's allowed-namespace rule skips `workers/`, which imports its libraries' namespaces.
- phpat (gate:9) refuses a `Worker\` class that implements Page, Api, Stream, Socket or Command: a worker is
  never routed and never run from the console, so its one kind is the Job.
- gate:26 admits `workers/` beside `modules/` and the theme.

## Deploying

A worker installs from its own folder, `composer install --no-dev` in `workers/<name>/`, with the
same Postgres variables as the site ([deploy.md](deploy.md#what-every-deploy-sets)), and runs
`php workers/<name>/bin/aic.php queue:work` under a service that restarts it, like the site's
`bin/aic queue:work`. It writes private files under the app's `var/storage/`, so it runs where the
site's release root is.
