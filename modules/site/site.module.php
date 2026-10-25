<?php

declare(strict_types=1);

/*
 * The site module's manifest (#93; the fixture app's since #126): the layout
 * service every page renders through, the home page, the status and health
 * APIs, file downloads, the tick stream and its page, the greeting pages and
 * their greeters, the jobs and notifications pages with the demo job and its
 * schedule, the site's blocks, db:ping and greet. Its components, layouts and
 * assets live in the theme (#153). The build reads it; nothing reads it at
 * run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Site\Block\PoweredByBlock;
use App\Site\Block\SiteNavBlock;
use App\Site\Command\DatabasePing;
use App\Site\Command\Greet;
use App\Site\Controller\GreetingPage;
use App\Site\Controller\GreetingPost;
use App\Site\Controller\HealthApi;
use App\Site\Controller\HomePage;
use App\Site\Controller\JobsPage;
use App\Site\Controller\JobsPost;
use App\Site\Controller\NotificationsPage;
use App\Site\Controller\NotificationsPost;
use App\Site\Controller\ResourceDownload;
use App\Site\Controller\StatusApi;
use App\Site\Controller\TickEvents;
use App\Site\Controller\TicksPage;
use App\Site\Job\DemoJob;
use App\Site\Migration\CreateSubscribersTable;
use App\Site\Migration\MakeSubscriberEmailUnique;
use App\Site\Service\EnglishGreeter;
use App\Site\Service\Greeter;
use App\Site\Service\HtmlLayout;
use App\Site\Service\SpanishGreeter;

return (new ModuleManifest('site'))
    ->route(RouteMethod::Get, '/api/status', StatusApi::class)
    ->route(RouteMethod::Get, '/health', HealthApi::class)
    ->route(RouteMethod::Get, '/download/{file}', ResourceDownload::class)
    ->route(RouteMethod::Get, '/events', TickEvents::class)
    ->route(RouteMethod::Get, '/ticks', TicksPage::class)
    ->route(RouteMethod::Get, '/', HomePage::class)
    ->route(RouteMethod::Get, '/hello/{name}', GreetingPage::class)
    ->route(RouteMethod::Post, '/hello', GreetingPost::class)
    ->route(RouteMethod::Get, '/jobs', JobsPage::class)
    ->route(RouteMethod::Post, '/jobs', JobsPost::class)
    ->route(RouteMethod::Get, '/notifications', NotificationsPage::class)
    ->route(RouteMethod::Post, '/notifications', NotificationsPost::class)
    ->plugin(Greeter::class, EnglishGreeter::class)
    ->plugin(Greeter::class, SpanishGreeter::class)
    ->block(PoweredByBlock::class)
    ->block(SiteNavBlock::class)
    ->command('db:ping', DatabasePing::class)
    ->command('greet', Greet::class)
    ->job('demo', DemoJob::class)
    ->schedule('demo-every-minute', 'demo', ['mode' => 'succeed'], '* * * * *')
    ->migration(CreateSubscribersTable::class)
    ->migration(MakeSubscriberEmailUnique::class)
    ->export(HtmlLayout::class);
