<?php

declare(strict_types=1);

/*
 * The site module's manifest (#93; the fixture app's since #126): the layout
 * every page renders in, the home page, the status and health APIs, file
 * downloads and assets, the tick stream and its page, the greeting pages and
 * their greeters, the jobs and notifications pages with the demo job and its
 * schedule, the site's blocks, db:ping and greet. The build reads it;
 * nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleAsset;
use AIC\Module\Contracts\ModuleAssetKind;
use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\ModuleTemplate;
use AIC\Module\Contracts\ModuleTheme;
use AIC\Module\Contracts\RouteMethod;
use App\Site\Block\PoweredByBlock;
use App\Site\Block\SiteNavBlock;
use App\Site\Command\DatabasePing;
use App\Site\Command\Greet;
use App\Site\Component\Greetings;
use App\Site\Component\Home;
use App\Site\Component\LineList;
use App\Site\Component\Page;
use App\Site\Component\SiteNav;
use App\Site\Component\Ticks;
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
    ->with(new ModuleTemplate(Home::class, 'templates/components/home.tpl.php'))
    ->with(new ModuleTemplate(LineList::class, 'templates/components/line-list.tpl.php'))
    ->with(new ModuleTemplate(SiteNav::class, 'templates/components/site-nav.tpl.php'))
    ->with(new ModuleTemplate(Ticks::class, 'templates/components/ticks.tpl.php'))
    ->with(new ModuleTheme(
        'app',
        'layouts/html.tpl.php',
        'layouts/page.tpl.php',
        Page::class,
        ['header' => 'header', 'content' => 'content', 'nav' => 'nav', 'footer' => 'footer'],
        [
            new ModuleAsset('base', 'assets/theme.css', ModuleAssetKind::Style),
            new ModuleAsset('tokens', 'assets/10-tokens.css', ModuleAssetKind::Style),
            new ModuleAsset('structure', 'assets/20-base.css', ModuleAssetKind::Style),
            new ModuleAsset('atoms', 'assets/30-atoms.css', ModuleAssetKind::Style),
        ],
    ))
    ->with(new ModuleTemplate(Greetings::class, 'templates/components/greetings.tpl.php'))
    ->with(new ModuleAsset('styles', 'assets/site.css', ModuleAssetKind::Style))
    ->with(new ModuleAsset('favicon', 'assets/favicon.svg', ModuleAssetKind::File))
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
