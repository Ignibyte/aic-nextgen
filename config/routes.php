<?php

declare(strict_types=1);

/*
 * The app's routes. Each names a handler id; the worker gives the router the
 * handlers by these ids. The table compiles once per worker boot.
 */

use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Domain\RoutePatternCompiler;
use App\Http\AssetFile;
use App\Http\GreetingPage;
use App\Http\GreetingPost;
use App\Http\NoteCreatePost;
use App\Http\NoteDeletePost;
use App\Http\NoteListPage;
use App\Http\NoteShowPage;
use App\Http\NoteUpdatePost;
use App\Http\PlaceholderPage;
use App\Http\ResourceDownload;
use App\Http\StatusApi;
use App\Http\TickEvents;

return new RouteCollector(new RoutePatternCompiler())
    ->onGet('/', PlaceholderPage::class)
    ->onGet('/hello/{name}', GreetingPage::class)
    ->onPost('/hello', GreetingPost::class)
    ->onGet('/api/status', StatusApi::class)
    ->onGet('/download/{file}', ResourceDownload::class)
    ->onGet('/assets/{file}', AssetFile::class)
    ->onGet('/events', TickEvents::class)
    ->onGet('/notes', NoteListPage::class)
    ->onPost('/notes', NoteCreatePost::class)
    ->onGet('/notes/{id:int}', NoteShowPage::class)
    ->onPost('/notes/{id:int}', NoteUpdatePost::class)
    ->onPost('/notes/{id:int}/delete', NoteDeletePost::class)
    ->table();
