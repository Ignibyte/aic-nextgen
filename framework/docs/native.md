# Native runtime

An app compiles with elephc into a prefork HTTP server. The same entry runs under PHP, which is the
production runtime until the compiled one has earned that place ([deploy.md](deploy.md)). Build-time declarations produce literal wiring, template classes and asset
catalogs; serving a request does not discover modules or interpret authored templates.

## Compiled site

`bootstrap/serve.php` handles one request. `public/index.php` invokes it under PHP-FPM, and the
native build combines it with the runtime class closure. Use `elephc --web --web-isolation=pool`.
Pool workers keep handler processes alive while each request starts with fresh PHP state. Do not
rely on a singleton or static cache to carry data between requests. Sessions, jobs and write records
live in Postgres, rather than in a handler's memory.

`composer run native:serve` builds and serves the configured app. Put TLS and the production
listener policy in a reverse proxy. The framework's early asset handler serves the compiled public
catalog before application factories, sessions and database work. Deploy binary, catalog and public
files from the same release root; retain previous release roots for clients with older URLs.
See [modules.md](modules.md#assets) for asset publication and cache policy.

## Write code with concrete types

These are source conventions used by the framework, not a list of compiler features guaranteed for
every PHP expression:

- Use typed properties, parameters and returns. Prefer small readonly values to associative bags.
  A PHPDoc `array<K, V>` serves analysis; the native compiler also needs concrete types in code.
- Build registries, wiring and template lookups before runtime. Use literal class references and
  literal require paths. Gate:16 refuses runtime-dynamic includes/requires.
- Narrow untrusted input at its boundary with actual predicates. Keep those checks even when
  the native build accepts the input: PHPStan and Psalm check the same source.
- Read a collection element into a concretely typed local or pass it through a typed helper before
  making a compound expression. Existing framework adapters use typed closures and explicit values
  where the compiler cannot infer a PHPDoc-only element type.
- Build a value in a local before appending it to a property: natively
  `$this->list[] = $this->name($x) . implode(', ', $parts)` left the property null (#184), while
  `$value = …; $this->list[] = $value;` holds.
- A closure takes what it uses through `use`, the object's own services too: natively a closure
  does not bind `$this`, so `$this->store` inside one is null (#184). Copy the property into a
  local first and capture the local, as `EntityStore::transaction()` callers do.
- Read PSR-7 bodies as streams using rewind/getContents where appropriate. Use the response builder
  for headers so methods retain the response type across PSR-7 withers.
- Keep resource ownership and cleanup explicit. The runtime adapters use typed resource slots and
  named operations; a failed native run must still reap its child servers and close its handles.
- Keep services immutable and request values local. A generated composition root constructs the
  services; business methods use constructor dependencies rather than runtime reflection or a
  container lookup.

Do not infer that an unlisted function is unsupported, or copy an old workaround without checking
current source and behavior. If legal PHP fails or behaves differently under the configured compiler,
report the smallest reproducer, source/toolchain identity and actual outputs to AIC. A source rewrite
must preserve behavior under both runtimes. Do not change a gate threshold to accept a mismatch.

## Class closure and cache

Gate:21 compiles, links and runs every runtime class with its dependencies and compares its probe
against PHP. Its cache key includes the closure's source bytes, toolchain and bridge libraries,
host PHP, pinned packages and probe program. A green cached closure has the same inputs as its
previous passing probe. Changed inputs are probed again in parallel; a failure is never cached.
The workflow and gate tooling execute under PHP and are outside the runtime closure.

The emitted status vocabulary is checked against the gate's actual enum:

| Status | Meaning |
|---|---|
| `NativeProbeStatus::Ok = OK` | fresh compile/link/run comparison passed |
| `NativeProbeStatus::Cached = CACHED` | unchanged previously passing closure |
| `NativeProbeStatus::CompileFail = COMPILE_FAIL` | compiler failed |
| `NativeProbeStatus::LinkFail = LINK_FAIL` | linker failed |
| `NativeProbeStatus::RunFail = RUN_FAIL` | program execution failed |
| `NativeProbeStatus::Mismatch = MISMATCH` | native output differs from PHP |

A class probe is not a complete HTTP scenario. Keep gate:23's whole-site comparison as well.

## Whole-site comparison and diagnosis

Gate:23 builds a fresh whole app, serves native and PHP entries beside each other, and executes
`config/native-site.json` in order. It compares response status, content type, location and normalized
body. Session/form workflows retain their own cookies and posted values. Asset, download, stream,
fragment and error cases belong in that configured sequence when the app exposes them. Both servers
are started to let the app's request body cap through (`AppConfig::$maxBodyBytes`, see
[security.md](security.md#the-request-body-cap)), so an oversized body is the app's 413 on each.
The compiled program's heap is fixed at build time (elephc's default is 8 MB), so gate:23 and
`aic-gate serve` build the site with `--heap-size` of 64 MiB plus four times the cap: room for the raw
body, its parsed form and the app's own handling. The heap is reserved, not touched, so pages a request
never uses cost no memory. A deployment builds its binary the same way. An open event stream holds
a handler until its connection ends, and when a page is left its handler runs on until the source's
own bound, so gate:23 and `aic-gate serve` start the site with 2 workers of 8 handlers (#206); a
deployment sizes `--workers` and `--handler-concurrency` for the pages that may be open at once.

When the database does not answer `bin/aic db:ping`, the whole gate defers and says why; running a
small database-free subset would not establish site coverage. A suite with deferrals is incomplete.
Fix the prerequisite, then rerun the real gate.

For a mismatch preserve the failing binary, assembly, source and toolchain hashes, full logs and
sequence of requests. Keep tokens, cookies and database values private. Identify the first bad
boundary before proposing a fix. A later passing retry is evidence about that retry, not proof that
an intermittent defect was repaired. Compare the interpreted oracle with the same inputs, and avoid
rebuilding the only failed artifact before collecting its evidence.
