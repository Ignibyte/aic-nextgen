# Templates

Templates are restricted plain PHP compiled at build time. The build reads the theme
`config/app.php` names and the fixed framework atom catalog. An app's templates compile to
ordinary final readonly PHP under `generated/templates/` with the `App\Generated\Templates`
Composer prefix. The theme's layout is in [modules.md](modules.md#the-theme); gate:24 checks the
compiled output and gate:25 checks the grammar.

## Identity and placement

A component's template sits beside its class in the theme, `themes/<theme>/components/<Name>/`,
named for the class in lower kebab case (`note-card.tpl.php` for `NoteCard`). The HTML and page
wrappers sit in `themes/<theme>/layouts/`. Each file begins exactly with its fully qualified class
and lower-camel variable, and a component template types its own folder's class:

```php
<?php /** @var \App\Theme\Components\NoteCard\NoteCard $noteCard */ ?>
<article><h2><?= $noteCard->title ?></h2></article>
```

The class, source, wrapper role and generated output come from one validated binding. Missing
files, a mismatched class, symlinks and nonportable paths are refused before publication. Old
`.html.php` sources, a `.tpl.php` anywhere outside the theme, and unsupported PHP inside a
template folder are refused. An API-only app names no theme and has no templates.

## Grammar

A template reads the component and declared loop variables. Use short echo tags and alternative
control syntax: `if` / `elseif` / `else` / `endif`, `foreach` / `endforeach`, `switch` / `endswitch`, and counter
`for` / `endfor`. Match expressions, isset, null coalescing and typed arithmetic/comparisons are
also supported. `break` and `continue` act on loops. A counter's initialization and increment
are the allowed assignment; ordinary assignments are refused. `empty()` is a dedicated typed
construct. It does not open a general function-call API.

Property chains, literals, allowed comparisons and typed operators are resolved statically.
Objects expose public declared typed properties. Array properties carry `list<T>` element types.
Class-typed loop variables use their class's lower-camel name, so the compiler can give each part
its actual parameter types. A null test must be possible for that type. Class constants and enum
cases must exist. A child component is rendered through the shared pass, with a stable key when
repeated in a loop.

Conditions can omit a literal attribute inside a tag or select part of a quoted value. Body
conditions and loops keep their Tessera block markers; tag and quoted-value blocks emit none.
The compiler also tracks comments and script, style, textarea and title content. Branches must
join at the same context; loops and their exits preserve the entry context. Tag names and opening
delimiters remain literal: empty branches and zero iterations cannot complete a divided name.
Quote dynamic attribute values. Render child Components and Regions only in element body context.

The fifteen framework atoms are declared under `framework/templates/components/`. A source-package
build uses the same reader and compiler to write their classes and lookup under
`framework/src/Component/Application/`. Their inline role preserves the existing form-field paths
and marker-free atom output. Apps consume these shipped classes; an app build never writes into
an installed framework package. The fixed catalog does not relax module ownership declarations.

The grammar denies function, method and static calls, `new`, closures, includes, require, eval,
exit, echo/print statements, arbitrary PHP blocks, declarations, global/static variables,
superglobals, variable variables, backticks and error suppression. Syntax or nodes outside the
allowed table fail closed. Short open tags are refused regardless of PHP's setting. Prepare the
values in the controller's helpers or services, rather than adding logic to the template.

## Types and output

The build's type categories are checked against its actual enum:

| Category | Output rule |
|---|---|
| `TemplateTypeKind::String = String` | HTML-escape text with quotes and invalid-byte substitution |
| `TemplateTypeKind::Int = Int` | decimal text |
| `TemplateTypeKind::Float = Float` | numeric text |
| `TemplateTypeKind::Bool = Bool` | usable in conditions; direct echo is refused |
| `TemplateTypeKind::Null = Null` | handle through a valid condition or coalescing; direct nullable echo is refused |
| `TemplateTypeKind::List = List` | iterate a typed list; do not echo the list |
| `TemplateTypeKind::Object = Object` | render a Component or read a supported property; do not echo an arbitrary object |
| `TemplateTypeKind::Unknown = Unknown` | refusal; supply a concrete declared type |

Strings are escaped in attribute values as well as text. Escaping is not URL policy: use validated
paths and appropriate data types for links. Components become child output, Regions become region
output, and transparent wrappers stay inline. See [rendering.md](rendering.md#escaping-and-deliberate-raw-html)
for RawHtml's explicit grants.

## Compiled output and failures

`composer run build` emits typed part methods and loops, static imports, escaping and Tessera
markers. A template too large for the method or class caps must be split into Components. Never
edit generated PHP to fix a refusal. Change the authored source or data declaration and rebuild.
All compiled classes take the normal style, analysis and native checks; only authored templates
are excluded from class tools.

Gate:25 reports `path:line: category: detail`. Fix placement with an explicit declaration, a typing
refusal by naming the declared class, and an expression refusal by giving the input its actual
type or preparing the value before rendering. Gate:24 also refuses missing, altered and orphaned
compiled outputs, including nested files. Its check mode writes nothing. Historical compiler-owned
module outputs are audited for exact provenance before the actual builder retires them; such an
audit never selects an old input.
