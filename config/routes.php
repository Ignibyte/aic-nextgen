<?php

declare(strict_types=1);

/*
 * The app's own routes. Each names a handler id; the worker gives the router the
 * handlers by these ids. It returns the collector, not the table: the modules'
 * routes (app/Generated/ModuleRoutes, #92) are added after these, by
 * bootstrap/serve.php at run time and by bin/aic-build for surface.json.
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
use App\Site\TicksPage;
use App\Stock\StockPage;
use App\Stock\StockPost;

return new RouteCollector(new RoutePatternCompiler())
    ->get('/', HomePage::class)
    ->get('/hello/{name}', GreetingPage::class)
    ->post('/hello', GreetingPost::class)
    ->get('/api/status', StatusApi::class)
    ->get('/health', HealthApi::class)
    ->get('/download/{file}', ResourceDownload::class)
    ->get('/assets/{file}', AssetFile::class)
    ->get('/assets/{dir}/{file}', AssetFile::class)
    ->get('/events', TickEvents::class)
    ->get('/ticks', TicksPage::class)
    ->get('/visits', VisitsPage::class)
    ->post('/visits/forget', VisitsForget::class)
    ->get('/uploads', UploadsPage::class)
    ->post('/uploads', UploadsPost::class)
    ->get('/stock', StockPage::class)
    ->post('/stock', StockPost::class)
    ->get('/login', LoginPage::class)
    ->post('/login', LoginPost::class)
    ->post('/logout', LogoutPost::class)
    ->get('/account', AccountPage::class)
    ->get('/jobs', JobsPage::class)
    ->post('/jobs', JobsPost::class)
    ->get('/notifications', NotificationsPage::class)
    ->post('/notifications', NotificationsPost::class)
    ->get('/pages', ContentListPage::class)
    ->get('/pages/{slug}', ContentShowPage::class)
    ->post('/account/pages', ContentNewPost::class)
    ->get('/account/pages/{slug}', ContentEditPage::class)
    ->post('/account/pages/{slug}', ContentSavePost::class)
    ->get('/notes', NoteListPage::class)
    ->post('/notes', NoteCreatePost::class)
    ->get('/notes/{id:int}', NoteShowPage::class)
    ->post('/notes/{id:int}', NoteUpdatePost::class)
    ->post('/notes/{id:int}/delete', NoteDeletePost::class);
