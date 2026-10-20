<?php

declare(strict_types=1);

/*
 * The app's routes. Each names a handler id; the worker gives the router the
 * handlers by these ids. The table compiles once per worker boot.
 */

use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Domain\RoutePatternCompiler;
use App\Http\AccountPage;
use App\Http\AssetFile;
use App\Http\GreetingPage;
use App\Http\GreetingPost;
use App\Http\JobsPage;
use App\Http\JobsPost;
use App\Http\LoginPage;
use App\Http\LoginPost;
use App\Http\LogoutPost;
use App\Http\NoteCreatePost;
use App\Http\NoteDeletePost;
use App\Http\NoteListPage;
use App\Http\NoteShowPage;
use App\Http\NoteUpdatePost;
use App\Http\NotificationsPage;
use App\Http\NotificationsPost;
use App\Http\PlaceholderPage;
use App\Http\ResourceDownload;
use App\Http\StatusApi;
use App\Http\TickEvents;
use App\Http\UploadsPage;
use App\Http\UploadsPost;
use App\Http\VisitsForget;
use App\Http\VisitsPage;

return new RouteCollector(new RoutePatternCompiler())
    ->onGet('/', PlaceholderPage::class)
    ->onGet('/hello/{name}', GreetingPage::class)
    ->onPost('/hello', GreetingPost::class)
    ->onGet('/api/status', StatusApi::class)
    ->onGet('/download/{file}', ResourceDownload::class)
    ->onGet('/assets/{file}', AssetFile::class)
    ->onGet('/events', TickEvents::class)
    ->onGet('/visits', VisitsPage::class)
    ->onPost('/visits/forget', VisitsForget::class)
    ->onGet('/uploads', UploadsPage::class)
    ->onPost('/uploads', UploadsPost::class)
    ->onGet('/login', LoginPage::class)
    ->onPost('/login', LoginPost::class)
    ->onPost('/logout', LogoutPost::class)
    ->onGet('/account', AccountPage::class)
    ->onGet('/jobs', JobsPage::class)
    ->onPost('/jobs', JobsPost::class)
    ->onGet('/notifications', NotificationsPage::class)
    ->onPost('/notifications', NotificationsPost::class)
    ->onGet('/notes', NoteListPage::class)
    ->onPost('/notes', NoteCreatePost::class)
    ->onGet('/notes/{id:int}', NoteShowPage::class)
    ->onPost('/notes/{id:int}', NoteUpdatePost::class)
    ->onPost('/notes/{id:int}/delete', NoteDeletePost::class)
    ->table();
