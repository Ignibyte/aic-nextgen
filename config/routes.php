<?php

declare(strict_types=1);

/*
 * The app's routes. Each names a handler id; the worker gives the router the
 * handlers by these ids. The table compiles once per worker boot.
 */

use AIC\Routing\Application\RouteCollector;
use AIC\Routing\Domain\RoutePatternCompiler;
use App\Http\GreetingPage;
use App\Http\PlaceholderPage;

return new RouteCollector(new RoutePatternCompiler())
    ->onGet('/', PlaceholderPage::class)
    ->onGet('/hello/{name}', GreetingPage::class)
    ->table();
