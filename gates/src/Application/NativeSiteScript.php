<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\NativeSiteStep;

/**
 * The requests gate:23 sends to both sites, in order: the pages, the forms,
 * the API, the download, the error answers and a note's whole life. Each site
 * runs the script on its own and deletes the note it made, so both see the
 * same database.
 */
final readonly class NativeSiteScript
{
    /**
     * @return list<NativeSiteStep> The steps, in order.
     */
    public function steps(): array
    {
        return [
            new NativeSiteStep('home', 'GET', '/', '', 200, false, false),
            new NativeSiteStep('greeting', 'GET', '/hello/Ada', '', 200, false, false),
            new NativeSiteStep('greet blank', 'POST', '/hello', 'name=', 422, false, false),
            new NativeSiteStep('greet', 'POST', '/hello', 'name=Ada', 303, false, false),
            new NativeSiteStep('status', 'GET', '/api/status', '', 200, false, false),
            new NativeSiteStep('download', 'GET', '/download/hello.txt', '', 200, false, false),
            new NativeSiteStep('missing file', 'GET', '/download/nope.txt', '', 404, false, false),
            new NativeSiteStep('no route', 'GET', '/nope', '', 404, false, false),
            new NativeSiteStep('wrong method', 'DELETE', '/', '', 405, false, false),
            new NativeSiteStep('notes', 'GET', '/notes', '', 200, true, false),
            new NativeSiteStep('note blank', 'POST', '/notes', 'title=', 422, true, false),
            new NativeSiteStep('note create', 'POST', '/notes', 'title=Gate&body=x', 303, true, true),
            new NativeSiteStep('note show', 'GET', '{note}', '', 200, true, false),
            new NativeSiteStep('note update', 'POST', '{note}', 'title=Gate+edited&body=y', 303, true, false),
            new NativeSiteStep('note delete', 'POST', '{note}/delete', '', 303, true, false),
            new NativeSiteStep('note gone', 'GET', '{note}', '', 404, true, false),
        ];
    }
}
