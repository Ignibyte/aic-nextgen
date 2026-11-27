# Workers

Each folder here is an interpreted worker: queued work for a library that does not compile (document generation,
an LLM agent library), run by PHP beside the compiled site. A worker is a Composer project of its own,
`workers/<name>/composer.json` with its classes in `src/` under `Worker\<Name>\`, and it holds Jobs only. Make one
with `bin/aic make:worker <name>`, which prints the line the root composer.json's `autoload-dev` needs and the
phpcs.xml.dist root, so every static gate reads it.

A worker shares only the queue, Postgres and private files with the site: no module, theme or framework class may
use a worker class, and a worker uses only the framework.
