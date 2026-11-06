<?php

declare(strict_types=1);

/*
 * The site module's manifest: the home page, the health API and db:ping. The
 * build reads it and writes the wiring into build/generated/; nothing reads it
 * at run time. Add a route, command, job or migration here, then run
 * `composer run build`.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Site\Command\DatabasePing;
use App\Site\Controller\HealthApi;
use App\Site\Controller\HomePage;

return (new ModuleManifest('site'))
    ->route(RouteMethod::Get, '/', HomePage::class)
    ->route(RouteMethod::Get, '/health', HealthApi::class)
    ->command('db:ping', DatabasePing::class);
