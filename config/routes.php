<?php

declare(strict_types=1);

/*
 * The app's routes. Each names a handler id; the worker gives the router the
 * handlers by these ids. The table compiles once per worker boot.
 */

use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Domain\RoutePatternCompiler;
use App\Auth\AccountPage;
use App\Auth\LoginPage;
use App\Auth\LoginPost;
use App\Auth\LogoutPost;
use App\Content\ContentEditPage;
use App\Content\ContentListPage;
use App\Content\ContentNewPost;
use App\Content\ContentSavePost;
use App\Content\ContentShowPage;
use App\Greeting\GreetingPage;
use App\Greeting\GreetingPost;
use App\Job\JobsPage;
use App\Job\JobsPost;
use App\Job\NotificationsPage;
use App\Job\NotificationsPost;
use App\Note\NoteCreatePost;
use App\Note\NoteDeletePost;
use App\Note\NoteListPage;
use App\Note\NoteShowPage;
use App\Note\NoteUpdatePost;
use App\Session\UploadsPage;
use App\Session\UploadsPost;
use App\Session\VisitsForget;
use App\Session\VisitsPage;
use App\Site\AssetFile;
use App\Site\HealthApi;
use App\Site\HomePage;
use App\Site\ResourceDownload;
use App\Site\StatusApi;
use App\Site\TickEvents;

return new RouteCollector(new RoutePatternCompiler())
    ->onGet('/', HomePage::class)
    ->onGet('/hello/{name}', GreetingPage::class)
    ->onPost('/hello', GreetingPost::class)
    ->onGet('/api/status', StatusApi::class)
    ->onGet('/health', HealthApi::class)
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
    ->onGet('/pages', ContentListPage::class)
    ->onGet('/pages/{slug}', ContentShowPage::class)
    ->onPost('/account/pages', ContentNewPost::class)
    ->onGet('/account/pages/{slug}', ContentEditPage::class)
    ->onPost('/account/pages/{slug}', ContentSavePost::class)
    ->onGet('/notes', NoteListPage::class)
    ->onPost('/notes', NoteCreatePost::class)
    ->onGet('/notes/{id:int}', NoteShowPage::class)
    ->onPost('/notes/{id:int}', NoteUpdatePost::class)
    ->onPost('/notes/{id:int}/delete', NoteDeletePost::class)
    ->table();
