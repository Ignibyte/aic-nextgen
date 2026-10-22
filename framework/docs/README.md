# AIC architecture

AIC is a PHP 8.5 framework for server-rendered sites, built to be written by agents and compiled
to a native binary. An app is a set of modules; each module declares what it brings in a manifest,
and a build step turns every manifest into plain PHP wiring under `generated/`. Nothing is
discovered at run time, so the whole app compiles with elephc into one prefork HTTP server, and the
same code runs under PHP as the reference.

These pages describe AIC as it stands. They are written for the agent building an app on it: paths
are the app's (`modules/<name>/`, `config/`, `generated/`), and framework code is named by its
class (`AIC\Module\Contracts\ModuleManifest`), which sits under `vendor/aic/framework/src/`.

## Reading order

Read the first three before you plan a change; the rest when the change touches them.

1. [kinds.md](kinds.md): the five entry kinds (Page, Api, Stream, Command, Job), each one
   interface with one method and one sealed response, and the other kinds a module holds.
2. [modules.md](modules.md): a module's folder, its manifest and every declaration, the build and
   what it writes, the folder each kind lives in, exports and requires, assets.
3. [systems.md](systems.md): the ten systems of the framework, the namespaces in each, and the
   layer ladder every namespace follows.
4. [conventions.md](conventions.md): the rules every PHP file follows, each with the gate that
   refuses a breach.
5. [gates.md](gates.md): every quality gate, what it checks, what a failure means and the usual
   fix.

## Where things are

| What | Where an app sees it |
|---|---|
| Framework source | `vendor/aic/framework/src/`, namespace `AIC\` |
| These pages | `vendor/aic/framework/docs/` |
| The app's modules | `modules/<name>/`, namespace `App\<Name>\` |
| The generated wiring | `generated/`, namespace `App\Generated\`, written by `composer run build` |
| Every route and command with its kind | `build/surface.json`, written by the same build |
| Config | `config/`, each file returning a typed object |

When a gate fails, its `gate-result:` line names the gate; [gates.md](gates.md) says what it
wants. When the code and a page disagree, the code is right and the page is a defect to report.
