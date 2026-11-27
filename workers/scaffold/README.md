# The scaffold worker

An interpreted worker, `Worker\Scaffold\`: queued work for a library that does not compile, run by PHP beside the
compiled site. It holds Jobs in `src/Job/` and the classes they use, and shares only the queue, Postgres and
private files with the site.

Its `composer.json` is what it installs and deploys with. The app's root maps `Worker\Scaffold\` in `autoload-dev`, so
every static gate reads it; the native gate does not, since it never compiles.
