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
        return [...$this->pages(), ...$this->sessions()];
    }

    /**
     * @return list<NativeSiteStep> The steps that need no database: pages without a form, API, download, asset, events, errors.
     */
    private function pages(): array
    {
        return [
            new NativeSiteStep('greeting', 'GET', '/hello/Ada', '', '', 200, false, false),
            new NativeSiteStep('status', 'GET', '/api/status', '', '', 200, false, false),
            new NativeSiteStep('download', 'GET', '/download/hello.txt', '', '', 200, false, false),
            new NativeSiteStep('missing file', 'GET', '/download/nope.txt', '', '', 404, false, false),
            new NativeSiteStep('asset', 'GET', '/assets/aic.css', '', '', 200, false, false),
            new NativeSiteStep('range part', 'GET', '/download/hello.txt', '', 'Range: bytes=0-4', 206, false, false),
            new NativeSiteStep('range suffix', 'GET', '/download/hello.txt', '', 'Range: bytes=-6', 206, false, false),
            new NativeSiteStep('range unsatisfiable', 'GET', '/download/hello.txt', '', 'Range: bytes=9999-', 416, false, false),
            new NativeSiteStep('events', 'GET', '/events?count=3', '', '', 200, false, false),
            new NativeSiteStep('no route', 'GET', '/nope', '', '', 404, false, false),
        ];
    }

    /**
     * @return list<NativeSiteStep> The steps with a session, which needs Postgres: the greeting form and its
     *                              guard, the visits, a note's whole life.
     */
    private function sessions(): array
    {
        return [
            new NativeSiteStep('home', 'GET', '/', '', '', 200, true, false),
            new NativeSiteStep('greet blank', 'POST', '/hello', 'name=&aic_csrf={csrf}', '', 422, true, false),
            new NativeSiteStep('greet', 'POST', '/hello', 'name=Ada&aic_csrf={csrf}', '', 303, true, false),
            new NativeSiteStep('greet, no token', 'POST', '/hello', 'name=Ada', '', 403, true, false),
            new NativeSiteStep('greet, wrong token', 'POST', '/hello', 'name=Ada&aic_csrf=forged', '', 403, true, false),
            new NativeSiteStep('greet, honeypot', 'POST', '/hello', 'name=Ada&aic_csrf={csrf}&aic_website=spam', '', 400, true, false),
            new NativeSiteStep('unsafe method, forged header', 'DELETE', '/', '', 'X-AIC-CSRF: forged', 403, true, false),
            new NativeSiteStep('visits', 'GET', '/visits', '', '', 200, true, false),
            new NativeSiteStep('visits, unknown', 'GET', '/visits', '', 'Cookie: __Host-aic_session=unknown', 200, true, false),
            new NativeSiteStep('visits forget', 'POST', '/visits/forget', 'aic_csrf={csrf}', '', 303, true, false),
            new NativeSiteStep('notes', 'GET', '/notes', '', '', 200, true, false),
            new NativeSiteStep('note blank', 'POST', '/notes', 'title=&aic_csrf={csrf}', '', 422, true, false),
            new NativeSiteStep('note create', 'POST', '/notes', 'title=Gate&body=x&aic_csrf={csrf}', '', 303, true, true),
            new NativeSiteStep('note show', 'GET', '{note}', '', '', 200, true, false),
            new NativeSiteStep('note update', 'POST', '{note}', 'title=Gate+edited&body=y&aic_csrf={csrf}', '', 303, true, false),
            new NativeSiteStep('note delete', 'POST', '{note}/delete', 'aic_csrf={csrf}', '', 303, true, false),
            new NativeSiteStep('note gone', 'GET', '{note}', '', '', 404, true, false),
        ];
    }
}
