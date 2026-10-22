# Conventions

Every PHP file in a module follows these rules. Each names the gate that refuses a breach; see
[gates.md](gates.md) for what each gate runs.

## Files and declarations

| Rule | Held by |
|---|---|
| `declare(strict_types=1);` right after the opening tag | gate:6, gate:11 |
| One class, interface, enum or trait per file, named as the file | gate:11 |
| `final` by default; `abstract` only for a class designed to be extended | gate:8, gate:11 |
| Properties typed, and `readonly` unless the object must change | gate:8, gate:11 |
| Every parameter and return typed, `void` and `never` included | gate:8, gate:11 |
| A class docblock that says what the class is for | gate:11 |
| A docblock on every public and protected method, with `@param` for each parameter, `@return` and `@throws` for each exception that can escape | gate:11 |

## Types

- **No `mixed`.** Say what the value is. The one exception is a method marked `#[Override]` whose
  PSR interface dictates `mixed`, such as PSR-7's `getAttribute()`. gate:8, gate:11 and gate:15
  hold it.
- **No untyped arrays.** Write the shape in phpdoc: `list<Note>`, `array<string, string>`.
- **Give the compiler its types in code.** elephc reads native types, not phpdoc. Prefer typed
  properties, typed returns and small readonly value objects to arrays of arrays; a value that
  needs a shape deserves a class. gate:21 is where a loose shape fails.

## State and wiring

- **No static state.** No static properties used as caches, no globals, no singletons: state lives
  in the objects the generated wiring builds. gate:15 refuses them.
- **No `new` of a service in business logic.** A class asks for what it uses in its constructor;
  the wiring under `generated/` passes it. Value objects and components are built where they are
  needed. A Page builds the components it returns.
- **Config through `config/`.** Read the environment only in `config/` files, which return typed
  objects; runtime code never calls `getenv()` or `env()`. gate:15 holds it.
- **No superglobals.** `$_GET`, `$_POST`, `$_SERVER` and the rest are read only by the framework's
  SAPI adapter. A controller reads the PSR-7 request. gate:15 holds it.
- **Literal requires only.** No `require` or `include` of a computed path, no `eval`. gate:15 and
  gate:16 hold it.

## Entry points and markup

- **Every entry point is one of the five kinds.** See [kinds.md](kinds.md). gate:9 holds it.
- **A controller holds no markup.** No string with `<` in `src/Controller/`, and each controller
  method returns its kind's response; helpers go to `src/Service/`. gate:8 holds it.
- **Templates are plain PHP, read only.** `<?= ?>`, the alternative control syntax, the component
  variable and loop variables; no function, method or static call, no `new`, no assignment. Values
  are escaped by their type when the build compiles the template. gate:25 holds it.
- **Raw HTML is granted, one use at a time.** `AIC\Component\Domain\RawHtml` skips escaping; each
  use needs a grant with its reason in `config/raw-html.php`. gate:27 holds it.

## Modern PHP

Write PHP 8.5 forms: `match` over `switch`, enums over class constants for a
closed set, first-class callables, named arguments where they read better. gate:7 (Rector) points
out the older forms.

## Seams

Every seam between systems is a PSR interface: PSR-7 and PSR-17 for HTTP messages, PSR-15 for
middleware and handlers, PSR-11 for the container. No bespoke equivalent of a PSR interface, and
no framework code from outside AIC. gate:2 keeps runtime `require` to `php`, `ext-*`, `psr/*` and
`aic/framework`.
