# Templates

Templates are restricted plain PHP that run as templates. Every template is a `.phtml` file.
The build reads the theme `config/app.php` names and the fixed framework atom catalog, checks
each template against the grammar below, and writes an escaped copy of it: the same template
with every echo routed through the render pass. An app's copies sit under `build/generated/templates/`,
mirroring their source paths. Generated PHP holds only the lookups that require them; no PHP
class holds markup. The theme's layout is in [modules.md](modules.md#the-theme); gate:24 checks
the generated output and gate:25 checks the grammar.

## Identity and placement

A component's template sits alone in the theme's `themes/<theme>/components/<Name>/`, named for
the class in lower kebab case (`note-card.phtml` for `NoteCard`); the class sits in
`themes/<theme>/src/Components/<Name>/` (#167). The HTML and page
wrappers sit in `themes/<theme>/layouts/`. Each file begins exactly with its fully qualified class
and lower-camel variable, and a component template types its own folder's class:

```php
<?php /** @var \App\Theme\Components\NoteCard\NoteCard $noteCard */ ?>
<article><h2><?= $noteCard->title ?></h2></article>
```

The class, source, wrapper role and generated output come from one validated binding. Missing
files, a mismatched class, symlinks and nonportable paths are refused before publication. Old
`.html.php` sources, a `.phtml` anywhere outside the theme, and unsupported PHP inside a
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
Class-typed loop variables use their class's lower-camel name, so the build types each one
from its declared list. A null test must be possible for that type. Class constants and enum
cases must exist. A child component is rendered through the shared pass, with a stable key when
repeated in a loop.

Conditions can omit a literal attribute inside a tag or select part of a quoted value. A
component or atom template renders exactly one element at its top level, outside every condition
and loop, with no text beside it (gate:25's `root` refusal): wrap siblings in a `<div>`, rows
in a `<tbody>`. The build writes Tessera's marks into that root's start tag; the framework's
tracked assets, head links rendered only inline, are the one exception.
The compiler also tracks comments and script, style, textarea and title content. Branches must
join at the same context; loops and their exits preserve the entry context. Tag names and opening
delimiters remain literal: empty branches and zero iterations cannot complete a divided name.
Quote dynamic attribute values. Render child Components and Regions only in element body context.

The framework atoms are declared under `framework/templates/components/` in four groups: text,
controls, forms, and the data atoms (#230), which are `AIC\Component\Domain\Table`, `Tabs`,
`Timeline`, `CodeBlock`, `Stack` and `FilterForm`, plus the profiler's own document,
`AIC\Profiler\Domain\ProfilerLayout`. A data atom's value classes (`TableRow`, `TableCell`, `Tab`,
`TimelineSegment`) are plain values its template reads. `Table`'s optional `alignments` (#240)
(`list<TableAlign>`, by column position) puts `class="aic-align-end"` on an end column's
header and cells; the framework ships no atom stylesheet, so the theme styles that class (the
profiler styles its own in `ProfilerStyles`). A source-package
build uses the same reader to write their escaped copies and lookup under
`framework/src/Component/Application/Templates/`. Their inline role preserves the existing
form-field paths, and an atom rendered inside another prints no marks. Apps consume these shipped files; an app build never
writes into an installed framework package. The fixed catalog does not relax module ownership
declarations. Tessera's framing, the region element, a child's marks and the fragment answer,
is the framework's own runtime templates in `framework/templates/tessera/`.

The grammar denies function, method and static calls, `new`, closures, includes, require, eval,
exit, echo/print statements, arbitrary PHP blocks, declarations, global/static variables,
superglobals, variable variables, backticks and error suppression. Syntax or nodes outside the
allowed table fail closed. Short open tags are refused regardless of PHP's setting. Prepare the
values in the controller's helpers or services, rather than adding logic to the template.

## Markup and whitespace

Gate:25 also reads each template's markup (#170): the inline HTML as written, each `<?= ?>` as one
marker and every other PHP block as its newlines, so both branches of a condition count and each
line keeps its number. It refuses, as `html`:

- inline style: a `style` attribute or a `<style>` element. Style an element through a class in the
  theme's stylesheet; a script file may still set styles.
- inline script: an `on*` handler, a `javascript:` URL, or a `<script>` with no `src` or with a body.
- an echo that writes a tag or attribute name: write the name, and echo only its value.
- a value not in double quotes, anything but one space (or a new line and its indentation) between
  attributes, a space around `=`, and a space before `>` or `/>`. Write JSON in an attribute as
  `data-x="{&quot;a&quot;:1}"`.

As `whitespace`, it refuses a tab, a carriage return, trailing whitespace, indentation that is not a
multiple of 4, two blank lines together and a blank line at the end. A template may end with or
without its newline; an atom ends without one, so its output carries none.

Gate:23 holds what the templates make together: every HTML answer the PHP app sends for its steps
passes the inline rules, uses each `id` once, gives every `<img>` an `alt`, and, when it starts with
a doctype, parses without an error from PHP's HTML5 parser (`Dom\HTMLDocument`). The output's own
spacing is not linted.

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

## Escaped output and failures

`composer run build` writes each template back out with its text as authored: each echo through
`$renderPass->text()` (escaped by its static type), `child()` (a component under its key),
`region()` or `inline()`, alternative-syntax conditions and loops, fully qualified class names,
and `marks()` in a component's root start tag. Its output prints byte for byte what the authored
template means. Each lookup method opens the render pass's buffer, requires the template by a
literal path and closes it; the compiled site inlines every required template at compile time.
Never edit a generated template to fix a refusal. Change the authored source or data declaration
and rebuild.

The class tools read `*.php` only, so templates never reach them. Psalm follows each literal
`require` and checks a template in its lookup's scope; PHPStan does not type templates, and its
dead-code detector learns what they read from the templates themselves. The native gate compiles
each lookup with the templates it requires.

Gate:25 reports `path:line: category: detail`. Fix placement with an explicit declaration, a typing
refusal by naming the declared class, and an expression refusal by giving the input its actual
type or preparing the value before rendering. Gate:24 also refuses missing, altered and orphaned
compiled outputs, including nested files. Its check mode writes nothing. Historical compiler-owned
module outputs are audited for exact provenance before the actual builder retires them; such an
audit never selects an old input.
