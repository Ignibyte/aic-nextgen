<?php

declare(strict_types=1);

/*
 * The site module's manifest (#93): the layout every page renders in, the
 * status and health APIs, file downloads and assets, the tick stream and its
 * page, the site's blocks, and db:ping. bin/aic-build reads it; nothing reads
 * it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Site\Block\PoweredByBlock;
use App\Site\Block\SiteNavBlock;
use App\Site\Command\DatabasePing;
use App\Site\Component\AppLayout;
use App\Site\Controller\AssetFile;
use App\Site\Controller\HealthApi;
use App\Site\Controller\ResourceDownload;
use App\Site\Controller\StatusApi;
use App\Site\Controller\TickEvents;
use App\Site\Controller\TicksPage;
use App\Site\Service\HtmlLayout;

return (new ModuleManifest('site'))
    ->route(RouteMethod::Get, '/api/status', StatusApi::class)
    ->route(RouteMethod::Get, '/health', HealthApi::class)
    ->route(RouteMethod::Get, '/download/{file}', ResourceDownload::class)
    ->route(RouteMethod::Get, '/assets/{file}', AssetFile::class)
    ->route(RouteMethod::Get, '/assets/{dir}/{file}', AssetFile::class)
    ->route(RouteMethod::Get, '/events', TickEvents::class)
    ->route(RouteMethod::Get, '/ticks', TicksPage::class)
    ->block(PoweredByBlock::class)
    ->block(SiteNavBlock::class)
    ->command('db:ping', DatabasePing::class)
    ->export(HtmlLayout::class)
    ->export(AppLayout::class);
