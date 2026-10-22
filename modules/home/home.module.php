<?php

declare(strict_types=1);

/*
 * The home module's manifest (#93): the home page, which links every demo and
 * shows the greeting form.
 * bin/aic-build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Home\Controller\HomePage;

return (new ModuleManifest('home'))
    ->requires('site')
    ->requires('greeting')
    ->route(RouteMethod::Get, '/', HomePage::class);
