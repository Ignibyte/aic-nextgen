<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteStep;

/**
 * The requests gate:23 sends to both sites, in order: the pages, the forms,
 * the API, the download and its ranges, a theme asset, an event stream, the
 * error answers and a note's whole life. Each site runs the script on its
 * own and deletes the note it made, so both see the same database.
 */
final readonly class NativeSiteScript
{
    /**
     * @return list<NativeSiteStep> The steps, in order.
     */
    public function steps(): array
    {
        return [...$this->pages(), ...$this->notes()];
    }

    /**
     * @return list<NativeSiteStep> The steps that need no database: pages, forms, API, download, asset, events, errors.
     */
    private function pages(): array
    {
        return [
            new NativeSiteStep('home', 'GET', '/', '', '', 200, false, false),
            new NativeSiteStep('greeting', 'GET', '/hello/Ada', '', '', 200, false, false),
            new NativeSiteStep('greet blank', 'POST', '/hello', 'name=', '', 422, false, false),
            new NativeSiteStep('greet', 'POST', '/hello', 'name=Ada', '', 303, false, false),
            new NativeSiteStep('status', 'GET', '/api/status', '', '', 200, false, false),
            new NativeSiteStep('download', 'GET', '/download/hello.txt', '', '', 200, false, false),
            new NativeSiteStep('missing file', 'GET', '/download/nope.txt', '', '', 404, false, false),
            new NativeSiteStep('asset', 'GET', '/assets/aic.css', '', '', 200, false, false),
            new NativeSiteStep('range part', 'GET', '/download/hello.txt', '', 'Range: bytes=0-4', 206, false, false),
            new NativeSiteStep('range suffix', 'GET', '/download/hello.txt', '', 'Range: bytes=-6', 206, false, false),
            new NativeSiteStep('range unsatisfiable', 'GET', '/download/hello.txt', '', 'Range: bytes=9999-', 416, false, false),
            new NativeSiteStep('events', 'GET', '/events?count=3', '', '', 200, false, false),
            new NativeSiteStep('no route', 'GET', '/nope', '', '', 404, false, false),
            new NativeSiteStep('wrong method', 'DELETE', '/', '', '', 405, false, false),
        ];
    }

    /**
     * @return list<NativeSiteStep> A note's whole life, which needs Postgres.
     */
    private function notes(): array
    {
        return [
            new NativeSiteStep('visits', 'GET', '/visits', '', '', 200, true, false),
            new NativeSiteStep('visits, unknown session', 'GET', '/visits', '', 'Cookie: __Host-aic_session=unknown', 200, true, false),
            new NativeSiteStep('visits forget', 'POST', '/visits/forget', '', 'Cookie: __Host-aic_session=unknown', 303, true, false),
            new NativeSiteStep('notes', 'GET', '/notes', '', '', 200, true, false),
            new NativeSiteStep('note blank', 'POST', '/notes', 'title=', '', 422, true, false),
            new NativeSiteStep('note create', 'POST', '/notes', 'title=Gate&body=x', '', 303, true, true),
            new NativeSiteStep('note show', 'GET', '{note}', '', '', 200, true, false),
            new NativeSiteStep('note update', 'POST', '{note}', 'title=Gate+edited&body=y', '', 303, true, false),
            new NativeSiteStep('note delete', 'POST', '{note}/delete', '', '', 303, true, false),
            new NativeSiteStep('note gone', 'GET', '{note}', '', '', 404, true, false),
        ];
    }
}
