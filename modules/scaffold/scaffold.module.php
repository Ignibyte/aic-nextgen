<?php

declare(strict_types=1);

/*
 * The scaffold module's manifest, made by bin/aic make:module (#122). The build
 * reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\ModuleTemplate;
use AIC\Module\Contracts\RouteMethod;
use App\Scaffold\Block\ScaffoldBlock;
use App\Scaffold\Command\ScaffoldHello;
use App\Scaffold\Component\ScaffoldAboutView;
use App\Scaffold\Component\ScaffoldCard;
use App\Scaffold\Controller\ScaffoldAboutPage;
use App\Scaffold\Controller\ScaffoldApi;
use App\Scaffold\Controller\ScaffoldPage;
use App\Scaffold\Controller\ScaffoldPost;
use App\Scaffold\Job\ScaffoldHelloJob;
use App\Scaffold\Migration\CreateScaffoldItemsTable;

return (new ModuleManifest('scaffold'))
    ->requires('site')
    ->with(new ModuleTemplate(ScaffoldCard::class, 'templates/components/scaffold-card.tpl.php'))
    ->export(ScaffoldCard::class)
    ->route(RouteMethod::Get, '/scaffold', ScaffoldPage::class)
    ->route(RouteMethod::Get, '/scaffold/about', ScaffoldAboutPage::class)
    ->with(new ModuleTemplate(ScaffoldAboutView::class, 'templates/components/scaffold-about-view.tpl.php'))
    ->route(RouteMethod::Post, '/scaffold', ScaffoldPost::class)
    ->route(RouteMethod::Get, '/api/scaffold', ScaffoldApi::class)
    ->block(ScaffoldBlock::class)
    ->command('scaffold:hello', ScaffoldHello::class)
    ->job('scaffold-hello', ScaffoldHelloJob::class)
    ->migration(CreateScaffoldItemsTable::class);
