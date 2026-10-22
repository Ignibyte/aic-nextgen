<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteStep;

/**
 * The requests gate:23 sends to both sites, in order: the pages without a
 * form, the API, the download and its ranges, a theme asset, an event
 * stream and the error answers; then the steps with a session: the greeting
 * form and its guard (#42), the visits and a note's whole life. Each site
 * keeps its own session and token, and deletes the note it made, so both
 * see the same database.
 */
final readonly class NativeSiteScript
{
    /**
     * @return list<NativeSiteStep> The steps, in order.
     */
    public function steps(): array
    {
        return [
            ...$this->pages(),
            ...$this->forms(),
            ...$this->visits(),
            ...$this->notes(),
            ...$this->stock(),
            ...$this->signIn(),
            ...$this->tessera(),
        ];
    }

    /**
     * @return list<NativeSiteStep> The pages without a form, the API, the faq module's page and API (#92), the download,
     *                              an asset, events and errors.
     */
    private function pages(): array
    {
        return [
            new NativeSiteStep('greeting', 'GET', '/hello/Ada', '', '', 200, false),
            new NativeSiteStep('status', 'GET', '/api/status', '', '', 200, false),
            new NativeSiteStep('health', 'GET', '/health', '', '', 200, false),
            new NativeSiteStep('faq', 'GET', '/faq', '', '', 200, false),
            new NativeSiteStep('faq api', 'GET', '/api/faq', '', '', 200, false),
            new NativeSiteStep('download', 'GET', '/download/hello.txt', '', '', 200, false),
            new NativeSiteStep('missing file', 'GET', '/download/nope.txt', '', '', 404, false),
            new NativeSiteStep('asset', 'GET', '/assets/aic.css', '', '', 200, false),
            new NativeSiteStep('module asset', 'GET', '/assets/site/favicon.svg', '', '', 200, false),
            new NativeSiteStep('range part', 'GET', '/download/hello.txt', '', 'Range: bytes=0-4', 206, false),
            new NativeSiteStep('range suffix', 'GET', '/download/hello.txt', '', 'Range: bytes=-6', 206, false),
            new NativeSiteStep('range unsatisfiable', 'GET', '/download/hello.txt', '', 'Range: bytes=9999-', 416, false),
            new NativeSiteStep('events', 'GET', '/events?count=3', '', '', 200, false),
            new NativeSiteStep('events, resumed', 'GET', '/events?count=3', '', 'Last-Event-ID: 2', 200, false),
            new NativeSiteStep('events, all seen', 'GET', '/events?count=3', '', 'Last-Event-ID: 3', 200, false),
            new NativeSiteStep('no route', 'GET', '/nope', '', '', 404, false),
        ];
    }

    /**
     * @return list<NativeSiteStep> The greeting form and its guard (#42).
     */
    private function forms(): array
    {
        return [
            new NativeSiteStep('home', 'GET', '/', '', '', 200, false),
            new NativeSiteStep('greet blank', 'POST', '/hello', 'name=&aic_csrf={csrf}', '', 422, false),
            new NativeSiteStep('greet', 'POST', '/hello', 'name=Ada&aic_csrf={csrf}', '', 303, false),
            new NativeSiteStep('greet, no token', 'POST', '/hello', 'name=Ada', '', 403, false),
            new NativeSiteStep('greet, wrong token', 'POST', '/hello', 'name=Ada&aic_csrf=forged', '', 403, false),
            new NativeSiteStep('greet, honeypot', 'POST', '/hello', 'name=Ada&aic_csrf={csrf}&aic_website=spam', '', 400, false),
            new NativeSiteStep('unsafe method, forged header', 'DELETE', '/', '', 'X-AIC-CSRF: forged', 403, false),
        ];
    }

    /**
     * @return list<NativeSiteStep> A session's visits, where forgetting goes, and uploads (#41, #43, #44).
     */
    private function visits(): array
    {
        $upload = 'multipart:aic_csrf={csrf}&file=@modules/site/resources/';

        return [
            new NativeSiteStep('visits', 'GET', '/visits', '', '', 200, false),
            new NativeSiteStep('visits, unknown', 'GET', '/visits', '', 'Cookie: __Host-aic_session=unknown', 200, false),
            new NativeSiteStep('forget, off-site', 'POST', '/visits/forget', 'next=%2F%2Fevil.test&aic_csrf={csrf}', '', 303, false),
            new NativeSiteStep('visits', 'GET', '/visits', '', '', 200, false),
            new NativeSiteStep('forget, next', 'POST', '/visits/forget', 'next=%2Fnotes&aic_csrf={csrf}', '', 303, false),
            new NativeSiteStep('jobs', 'GET', '/jobs', '', '', 200, false),
            new NativeSiteStep('notifications', 'GET', '/notifications', '', '', 200, false),
            new NativeSiteStep('uploads', 'GET', '/uploads', '', '', 200, false),
            new NativeSiteStep('upload a PNG', 'POST', '/uploads', $upload . 'upload.png', '', 303, false),
            new NativeSiteStep('uploads, accepted', 'GET', '/uploads', '', '', 200, false),
            new NativeSiteStep('upload, disguised', 'POST', '/uploads', $upload . 'disguised.png', '', 422, false),
            new NativeSiteStep('upload, none', 'POST', '/uploads', 'multipart:aic_csrf={csrf}', '', 422, false),
        ];
    }

    /**
     * @return list<NativeSiteStep> Tessera requests (#70): another protocol refused, a component, two regions and a
     *                              template's child (#98) as fragments, another layout and a missing target as the
     *                              whole document.
     */
    private function tessera(): array
    {
        $other = "Tessera-Request: visit\r\nTessera-Protocol: 9";
        $app = "Tessera-Request: visit\r\nTessera-Protocol: 0\r\nTessera-Layout: app\r\nTessera-Targets: ";
        $layout = "Tessera-Request: visit\r\nTessera-Protocol: 0\r\nTessera-Layout: other\r\nTessera-Targets: content";

        return [
            new NativeSiteStep('tessera, another protocol', 'GET', '/stock', '', $other, 400, false),
            new NativeSiteStep('tessera, a component', 'GET', '/stock', '', $app . 'content/stock-list', 200, false),
            new NativeSiteStep('tessera, two regions', 'GET', '/notes', '', $app . 'content, nav', 200, false),
            new NativeSiteStep('tessera, a region visit', 'GET', '/notes', '', $app . 'content', 200, false),
            new NativeSiteStep('tessera, a template child', 'GET', '/notes', '', $app . 'content/c0/NoteList.1', 200, false),
            new NativeSiteStep('tessera, another layout', 'GET', '/stock', '', $layout, 200, false),
            new NativeSiteStep('tessera, a missing target', 'GET', '/stock', '', $app . 'content/nope', 200, false),
            new NativeSiteStep('tessera, altered props', 'GET', '/stock', '', $app . 'content/stock-list e30.forged', 400, false),
            ...$this->tesseraWrites(),
        ];
    }

    /**
     * @return list<NativeSiteStep> Tessera writes (#71): a redirect as `Tessera-Location`, a refusal as a 422
     *                              fragment, a stale token as 419; a write id applied once and repeated, and a
     *                              malformed one refused (#72).
     */
    private function tesseraWrites(): array
    {
        $submit = "Tessera-Request: submit\r\nTessera-Protocol: 0\r\nTessera-Layout: app\r\nTessera-Targets: content";
        $ada = 'name=Ada&aic_csrf={csrf}';
        $withId = $submit . "\r\nTessera-Write-Id: ";

        return [
            new NativeSiteStep('tessera, a submit that redirects', 'POST', '/hello', $ada, $submit, 200, false),
            new NativeSiteStep('tessera, a refused submit', 'POST', '/hello', 'name=&aic_csrf={csrf}', $submit, 422, false),
            new NativeSiteStep('tessera, a stale token', 'POST', '/hello', 'name=Ada&aic_csrf=stale', $submit, 419, false),
            new NativeSiteStep('tessera, a write with an id', 'POST', '/hello', $ada, $withId . 'gate-1', 200, false),
            new NativeSiteStep('tessera, its repeat', 'POST', '/hello', $ada, $withId . 'gate-1', 200, false),
            new NativeSiteStep('tessera, a malformed id', 'POST', '/hello', $ada, $withId . 'no good', 400, false),
        ];
    }

    /**
     * @return list<NativeSiteStep> Stock items with a date, a price and a certified flag, written and read back (#65).
     */
    private function stock(): array
    {
        $car = 'name=Gate+car&price=18999.99&added_on=2026-10-21&certified=1&aic_csrf={csrf}';
        $van = 'name=Gate+van&price=7000&added_on=2024-02-29&aic_csrf={csrf}';
        $badPrice = 'name=Gate+car&price=abc&added_on=2026-10-21&aic_csrf={csrf}';
        $badDate = 'name=Gate+car&price=1&added_on=2026-02-30&aic_csrf={csrf}';

        return [
            new NativeSiteStep('stock, bad price', 'POST', '/stock', $badPrice, '', 422, false),
            new NativeSiteStep('stock, bad date', 'POST', '/stock', $badDate, '', 422, false),
            new NativeSiteStep('stock add, certified', 'POST', '/stock', $car, '', 303, false),
            new NativeSiteStep('stock, certified', 'GET', '/stock', '', '', 200, false),
            new NativeSiteStep('stock add, not certified', 'POST', '/stock', $van, '', 303, false),
            new NativeSiteStep('stock, not certified', 'GET', '/stock', '', '', 200, false),
        ];
    }

    /**
     * @return list<NativeSiteStep> A note's whole life, its envelope altered once (#43).
     */
    private function notes(): array
    {
        $update = 'title=Gate+edited&body=y&aic_csrf={csrf}&aic_envelope={envelope}';

        return [
            new NativeSiteStep('content pages', 'GET', '/pages', '', '', 200, false),
            new NativeSiteStep('content page, none', 'GET', '/pages/no-such-page', '', '', 404, false),
            new NativeSiteStep('notes', 'GET', '/notes', '', '', 200, false),
            new NativeSiteStep('note blank', 'POST', '/notes', 'title=&aic_csrf={csrf}', '', 422, false),
            new NativeSiteStep('note create', 'POST', '/notes', 'title=Gate&body=x&aic_csrf={csrf}', '', 303, true),
            new NativeSiteStep('note show', 'GET', '{note}', '', '', 200, false),
            new NativeSiteStep('note update, altered', 'POST', '{note}', $update . 'x', '', 400, false),
            new NativeSiteStep('note update', 'POST', '{note}', $update, '', 303, false),
            new NativeSiteStep('note show again', 'GET', '{note}', '', '', 200, false),
            new NativeSiteStep('note delete', 'POST', '{note}/delete', 'aic_csrf={csrf}&aic_envelope={envelope}', '', 303, false),
            new NativeSiteStep('note gone', 'GET', '{note}', '', '', 404, false),
        ];
    }

    /**
     * @return list<NativeSiteStep> Signing in and out as the gate user, and the protected page (#9).
     */
    private function signIn(): array
    {
        $login = 'login=gate&next=%2Faccount&aic_csrf={csrf}&password=';

        return [
            new NativeSiteStep('account, anonymous', 'GET', '/account', '', '', 303, false),
            new NativeSiteStep('login page', 'GET', '/login?next=%2Faccount', '', '', 200, false),
            new NativeSiteStep('login, wrong', 'POST', '/login', $login . 'wrong', '', 422, false),
            new NativeSiteStep('login', 'POST', '/login', $login . '{password}', '', 303, false),
            new NativeSiteStep('account', 'GET', '/account', '', '', 200, false),
            new NativeSiteStep('logout', 'POST', '/logout', 'aic_csrf={csrf}', '', 303, false),
            new NativeSiteStep('account, signed out', 'GET', '/account', '', '', 303, false),
        ];
    }
}
