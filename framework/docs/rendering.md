# Rendering

A Page builds a tree of data. The framework renders that tree as a document or as fragments;
controllers do not assemble markup. Start with [kinds.md](kinds.md#page) for the response shapes
and [modules.md](modules.md#the-theme) for declaring the templates.

## Components and layouts

`AIC\Component\Contracts\Component` has no methods. An app component is final readonly data
with typed public constructor properties. Its caller computes the values; its declared template
reads them. Children are Components, lists of Components, or typed values, rather than strings
containing HTML. Built-in atoms such as `AIC\Component\Domain\Link` and
`AIC\Component\Domain\Form` have framework templates supplied by the atom lookup.

`AIC\Controller\Domain\PageDocument` holds an `AIC\Component\Contracts\Layout` and a status.
A Layout supplies its title, regions, region names, assets, CSRF token and stable Tessera layout
identity. A filled `AIC\Component\Contracts\Regions` contains named
`AIC\Component\Contracts\Region` values, each with a list of Components. Region names use
lowercase words joined by hyphens. Duplicate names and filling a region the layout does not place
are refused.

The site's declared theme separates the document wrapper from its typed page-region data.
`AIC\Component\Domain\ThemeLayout` holds the title, CSRF data, tracked assets and page;
`AIC\Component\Domain\ThemePage` carries the filled regions into the generated adapter.
The authored page class has promoted nonnullable Region properties. Its template renders every
declared region exactly once on every path. The HTML template renders the page and tracked assets
exactly once. These wrappers stay transparent to component target paths.

## One Renderer

`AIC\Component\Application\Renderer` is the only code that constructs
`AIC\Component\Contracts\Html`. Its public operations, excluding construction, are:

| Operation | Result |
|---|---|
| `Renderer::document()` | `document(Layout): Html`, the whole document through its registered template |
| `Renderer::fragments()` | `fragments(Layout, list<string>): ?Html`, targets from the same tree; null when a target is absent |

The generated `App\Generated\ThemeTemplates` lookup maps the theme's component classes to their
compiled classes, then delegates to the framework atoms. A component folder without its template
is a build error. No runtime directory scan, template include or companion-class naming rule
chooses a template.

`AIC\Component\Application\RenderPass` writes a child with component markers, a region with
its `data-region` wrapper, or an inline transparent wrapper. Full documents and fragments use the
same pass and lookup. A fragment contains title, current CSRF metadata and tracked resources,
followed by one template element per target. It is not a second view implementation.
`AIC\Controller\Application\Psr7ResponseWriter` alone reads Html's markup for the response.

## Blocks and keys

`AIC\Block\Contracts\Block` supplies `placement()` and `component(ServerRequestInterface)`.
The block is a service; the component it produces is data for this request. A placement names a
region, a weight and a route prefix. Matching blocks follow the page's own components, ordered by
weight and then registration order; a block for a region the layout does not place is skipped.
Declare blocks with the module's `->block(` method. Never retain a request on a shared block.

Wrap a child in `AIC\Component\Domain\KeyedComponent` when the app needs a stable key or signed
props token. Its key becomes part of the target path, for example `content/stock-list`. Keep keys
stable when data changes; a loop's keyed children must have distinct keys. See
[tessera.md](tessera.md#markers-and-fragments) for the wire format.

## Escaping and deliberate raw HTML

[templates.md](templates.md#types-and-output) describes escaping by static type. Prefer Components
for structure and plain strings for text. `AIC\Component\Domain\RawHtml` is the counted escape
hatch. Every construction needs an exact class grant and a reason in `config/raw-html.php`;
trust comes from the caller's validation, not from the wrapper.

Gate:27 lists and counts those constructions against the grants. gate:8 rejects Html construction
outside the Renderer, markup reads outside the writer, controller markup and invalid tree inputs;
gate:9 holds the rendering dependency boundaries. Fix a refusal by moving presentation into data
and a declared template. A grant does not make untrusted text safe to render.
