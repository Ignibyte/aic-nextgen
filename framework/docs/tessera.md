# Tessera

Tessera enhances the server-rendered page with navigation, targeted refreshes, form submissions and
stream updates. Pages remain [Component trees](rendering.md), so a full page and a targeted answer
render the same data. The server speaks protocol version 2 (Tessera 0.3.1 and later; this app pins 0.4.3; the profiler uses the devtools events and highlight 0.4.0 added); the browser release is pinned separately
in app config and published by the build with its exact bytes and checksum.

## Markers and fragments

Regions render with `data-region="content"`. A component is its one root element, which carries
`data-t-c="KEY"`: `composer run build` writes `$renderPass->marks()` into each component and atom
template's root start tag, and the render pass prints the attribute there when it renders the
template as a region's or a template's child. Inline rendering prints none, and a marked component
whose template printed no marks fails the render. Keys form paths such as `content/stock-list`. A
`KeyedComponent` may add a signed props token, written as `data-t-props`. Protocol 1 has no block
markers. HTML/page wrappers are transparent, so changing a wrapper does not add target segments.

A `KeyedComponent` may also carry an anchor, a name in the key grammar written as `data-t-anchor`.
A link, form, poll or stream patch (since Tessera 0.3.1) targets it as `@NAME` (#204);
Tessera finds the anchored element and sends its path,
so module code never spells a path that a layout change could strand. One page holds each anchor
once: a second on one render fails it (`DuplicateAnchor`). A gate:23 step may send
`Tessera-Targets: @NAME`, which each site resolves from its own page of the step's path. Gate:15
refuses a path with a positional segment (`cN`, `Name.N`) in modules, themes and browser tests: it
moves when a wrapper or an order changes. A region and a component's own key
(`content/stock-list`) stays allowed; `TesseraProps::forTarget()` reads props by it. The root
`.semgrepignore`, an aic/gates stub, lets the rule reach `tests/browser/`.

Protocol 2 adds the anchor to a target entry: Tessera sends `target [props] [@NAME]`, the anchor last
whenever the component's element carries one, however it was targeted. So a module reads a
component's props by its anchor wherever the page places it: `TesseraProps::forTarget(request,
'@stock-list')` and `unchanged(request, '@stock-list', version)` match the entry that carries that
anchor. A keyed component prints its props token and anchor wherever it renders, a template's child
included, at a positional path such as `content/stock-panel/Panel.1`. Give a component's region
wrapper its own key too, so a filter that adds or drops a sibling does not move its path.

`AIC\Tessera\Domain\TesseraRequest` validates request kind, protocol, layout identity and target
syntax. `AIC\Tessera\Application\TesseraRequests::read()` reads it from a request's four Tessera
headers, in one place for the framework and an app (#268): a class takes it as a defaulted
constructor argument and asks, say, `refreshesOnly('@jobs-live')`, instead of reading the headers itself. A valid visit naming a region also refreshes the layout's other regions so navigation state
follows the page. A visit naming only components gets exactly those components. Refresh, submit
and action requests receive their requested targets. A refresh, submission or action whose
component path the page no longer renders, below a region the layout places, gets that target with
`data-mode="remove"` and no markup, so Tessera drops the element, as protocol 5.1 allows (#247): a deleted row
goes away. Several removals and patches may share one answer; Tessera 0.4.2 and later land every one (#251),
where 0.4.1 and older dropped each fragment after the first removal. A page adds signal patches to its
answer with `PageDocument::withSignalPatch(new SignalPatch($scope, $json, $onlyIfMissing))` (#253):
a fragment answer writes each after the templates as `<script type="application/json"
data-scope="…">`, `data-only-if-missing` when set, and Tessera merges them once every fragment has
landed. `SignalPatch` refuses a scope that is not a target and JSON that is not an object, and writes
`<` as `\u003c`. The whole document carries none; its markup sets the signals a page starts with.
Every dynamic answer is `Cache-Control: private, no-store`, and Tessera does not use a hover
prefetch (`data-prefetch="hover"`) whose answer says `no-store`. A page that may be shown from an
answer a few seconds old opts in with `PageDocument::prefetchable()` (#254): its document and
fragment answers say `private, no-cache`, so the click takes the hover's answer. A page with
anything a shared machine should not keep, or that changes from one second to the next, stays as it
is; a press prefetch is the click's own request and works either way. A visit's missing target, a missing region, overlapping or malformed paths, a different
layout or a different protocol fall back to a whole document. Each theme layout
has its own identity: the theme's name for the default (`app`), `<theme>-<name>` for a
named one (`app-focus`). A visit from a page in one layout to a page in another therefore gets a
whole document, and a fragment never crosses layouts.

A fragment response carries title, CSRF metadata and tracked resources, then
`<template data-target="content">...</template>` for each target. It renders the same bytes for
that target as the whole document, its descendants included however deep the target sits (#179);
`tests/browser/tessera.spec.ts` holds that for a nested target. A Page controller runs whole for a fragment
too; a part built from a source ([rendering.md](rendering.md#blocks-and-keys)) reads its data only
when the answer renders it, so a target's refresh leaves the other parts' reads undone.

A poll that finds nothing changed answers `204`, and Tessera leaves the page as it is. Seal a
`version` in the component's props (what its data is now: a count and the latest timestamps are
enough), and before building anything ask `AIC\Tessera\Application\TesseraProps::unchanged(request,
target, version)`. It is true only for a refresh, in protocol 2, of that one target whose verified
props carry the same version; then return `AIC\Controller\Domain\PageUnchanged`. A visit, a
submission or a refresh of several targets always renders. The props come back on every refresh
and are re-sealed on every render, so the version needs no header of its own. Assets use their compiled canonical URLs. A release change is
visible through tracked resources and may require a full visit.

## Protocol constants

These values are checked against the public constants:

| Constant | Meaning |
|---|---|
| `TesseraRequest::VERSION = 2` | server protocol, separate from the script's release version |
| `TesseraRequest::REQUEST = Tessera-Request` | request kind: visit, submit, action, refresh or stream |
| `TesseraRequest::PROTOCOL = Tessera-Protocol` | protocol version |
| `TesseraRequest::TARGETS = Tessera-Targets` | region/component targets and optional props tokens |
| `TesseraRequest::LAYOUT = Tessera-Layout` | layout identity of the current page |
| `TesseraRequest::WRITE_ID = Tessera-Write-Id` | repeat identifier for an unsafe write |
| `TesseraRequest::FULL_VISIT = Tessera-Full-Visit` | response location to load as a full document |
| `TesseraRequest::STREAM_PAUSE = Tessera-Stream-Pause` | a stream Tessera closes while the page is hidden (#259) |
| `TesseraRequest::VARY = Tessera-Request, Tessera-Protocol, Tessera-Targets, Tessera-Layout` | page response Vary value |
| `TesseraRelease::SCRIPT = tessera.csp.iife.js` | script filename inside the pinned release |

`AIC\Tessera\Domain\TesseraRelease` holds the app's configured release and checksum. Do not
infer its release version from the protocol constant or use an unpinned remote script. Rebuild
assets after changing the pin. [modules.md](modules.md#assets) describes publication and deployment.
Dynamic page responses default to `private, no-store`; their Vary header still distinguishes the
request modes.

## Forms, redirects and write identifiers

A Page returns data for a form on both success and failure. A 422 validation response or a 409
conflict can render the affected regions in place. A successful enhanced redirect is translated
by `AIC\Tessera\Application\TesseraRedirectMiddleware` to `Tessera-Location`; ordinary browsers
receive the HTTP redirect. `AIC\Controller\Domain\PageFullVisit` requests full navigation.
Missing or stale CSRF data gets 419 for an enhanced request and 403 for an ordinary browser;
a filled honeypot gets 400. See [security.md](security.md#form-guard).

`AIC\Tessera\Application\TesseraWriteMiddleware` recognizes unsafe requests carrying a write
identifier, scoped by the session CSRF token. The Postgres ledger records their completed replies, so repeating that write can return
the recorded result without applying it again. An unfinished write returns 409; a malformed identifier returns 400. Use a fresh identifier
for each distinct write: reusing a settled identifier replays its original response. A write identifier does not replace CSRF, authentication
or domain validation.

## Streams and props

An EventSource yields `AIC\Controller\Contracts\ServerEvent` values through an EventStream.
`AIC\Tessera\Domain\TesseraEvents` supplies the event helpers below. An element patch carries a
component, never markup: the response writer, as `AIC\Controller\Contracts\EventWriter`, renders it
through `Renderer::elements()` when the stream pulls the event, with the same templates and escaping
as a page and no marks on the patched component. Signals and keep-alive events carry no
component.

| Operation | Contract |
|---|---|
| `TesseraEvents::patchElements()` | target, component, mode, id and last; emits tessera-patch-elements |
| `TesseraEvents::patchSignals()` | scope, JSON object, only-if-missing, id and last; emits tessera-patch-signals |
| `SignalPatch` | scope, JSON object and only-if-missing; a Page's fragment answer writes it (#253); a `@NAME` scope is written as the path the request named with that anchor, and left out when it named none (#260) |
| `TesseraEvents::keepAlive()` | keep-alive comment, with the last flag |
| `TesseraEvents::reconnect()` | a last comment that ends the connection but not the stream (#202) |
| `EventStream::pausedWhileHidden()` | the stream answered `Tessera-Stream-Pause: hidden`: Tessera closes it while the page is hidden and reconnects with `Last-Event-ID` when visible (#259) |
| `TesseraProps::forTarget()` | verifies the requested target's props envelope and returns string values; empty when absent |
| `TesseraProps::unchanged()` | true only for a protocol 2 refresh of that one target whose verified props carry the given `version` |

Element modes are morph, inner, append, prepend and remove. Event data uses one `KEY VALUE` line
per value line. IDs support resuming through `Last-Event-ID`; the source decides what follows that
ID. The final event closes the stream with `tessera-close`; an idle source can send keep-alive
comments while waiting. The server flushes event chunks and disables proxy buffering.

A stream holds a server worker for as long as its connection is open, so a part that stays live for
a page's whole life should bound each connection: after some seconds its source returns
`TesseraEvents::reconnect()` (a `ServerEvent` that is last and `resumes`), the connection ends with
no `tessera-close`, and Tessera reconnects with the last id it saw (#202). A visitor who signed out
meanwhile is turned away on that reconnect by the protected prefix, which ends the stream.

Seal props with `AIC\Form\Application\EnvelopeCodec` and place the token on a KeyedComponent.
`AIC\Tessera\Application\TesseraProps` verifies the target's returned token before exposing its
values. Tokens are signed, readable and subject to the component token size/character limits.
They carry no authorization by themselves. Recheck access to the object on every request.

Gate:23 compares configured whole-site and fragment behavior against PHP, including status, media
type, redirects, normalized bodies and the `Tessera-*`, `Vary` and `Cache-Control` headers (#173).
It also runs the kept browser tests on the compiled site (#174); AIC's `tests/browser/tessera.spec.ts`
holds region swaps with back and forward, a refused form answered in place with its error summary
focused, a submit's redirect, the streamed ticks and the plain form with JavaScript off, each in
the same document where Tessera promises it and with no console error.

Every HTML response carries `ResponseBuilder::POLICY` as its `Content-Security-Policy`: scripts,
styles, fonts and connections from the site itself only, images also as `data:`. The pinned csp
build runs under it, and the HTML lint (#170) keeps inline script and style out of pages. Gate:24 checks the pinned assets; gate:8 and gate:27 keep
the render tree and deliberate raw output within their contracts. A missing target leads to a
full response, or to its removal as above, never to an independent fragment template.

## Vocabulary

What Tessera 0.4.3 reads from a page and what it raises, by name (Tessera's `docs/protocol.md`
sections 3 and 7.2, `docs/design.md` and its README's events). `bin/aic code:coverage` counts each
row in an app's PHP and template files, and shows the ones AIC's own code and templates write
(#232). Keep the table in step when AIC pins another Tessera.

| Name | Kind | What it is |
|---|---|---|
| `data-region` | attribute | a region (written by AIC's layouts) |
| `data-t-c` | attribute | a component's key |
| `data-t-props` | attribute | a component's signed props token |
| `data-t-anchor` | attribute | names a component `@NAME` |
| `data-target` | attribute | the targets a link, form or submitter updates |
| `data-push` | attribute | `off` leaves history as it was |
| `data-enhance` | attribute | `off` leaves links and forms to the browser |
| `data-track` | attribute | a tracked asset in `<head>` |
| `autofocus` | attribute | the server's choice of focus after an update |
| `data-focus-key` | attribute | a stable name to restore focus to |
| `data-error-summary` | attribute | the error list focused after a `422` or `409` |
| `aria-invalid` | attribute | the focus fallback for a refused field |
| `data-permanent` | attribute | an element kept across visits |
| `data-lazy` | attribute | loads its target once rendered |
| `data-poll` | attribute | refreshes its target on an interval |
| `data-prefetch` | attribute | `hover` or `off` for link prefetching |
| `data-stream` | attribute | opens a stream for the element's life |
| `data-signals` | attribute | declares signals |
| `data-computed` | attribute | a signal computed from others |
| `data-bind` | attribute | binds a field to a signal |
| `data-text` | attribute | text from an expression |
| `data-show` | attribute | shows the element while an expression holds |
| `data-class` | attribute | classes from expressions |
| `data-attr` | attribute | attributes from expressions |
| `data-style` | attribute | style properties from expressions |
| `data-on` | attribute | runs an expression on an event |
| `data-init` | attribute | runs an expression once the element is live |
| `data-effect` | attribute | runs an expression when its signals change |
| `data-loading` | attribute | a signal true while a request runs |
| `data-ignore` | attribute | Tessera leaves the element's directives alone |
| `data-ignore-morph` | attribute | a morph leaves the element as it is |
| `tessera-layout` | meta | the layout a page was rendered with |
| `tessera-csrf-token` | meta | the CSRF token sent on unsafe requests |
| `tessera-csrf-header` | meta | the header that carries it |
| `tessera-patch-elements` | stream event | one fragment |
| `tessera-patch-signals` | stream event | one signal patch |
| `data-scope` | fragment answer | a signal patch's scope, written by AIC's fragment template (#253) |
| `data-only-if-missing` | fragment answer | the patch sets only missing signals (#253) |
| `tessera-close` | stream event | the stream is over; no reconnect |
| `tessera:error` | browser event | a directive, request, stream, lazy load or poll failed |
| `tessera:expired` | browser event | an answer was `419` |
| `tessera:indeterminate` | browser event | a write may have been saved |
| `tessera:exchange` | browser event | an exchange settled (the profiler's toolbar reads it) |
| `tessera:patched` | browser event | an element a patch changed |
