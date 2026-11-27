<?php

declare(strict_types=1);

/*
 * The statement module's manifest, made by bin/aic make:module (#122). The build
 * reads it; nothing reads it at run time. The module queues statement PDFs on
 * the report worker's queue and sends what the worker wrote (#336).
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Statement\Controller\StatementPage;
use App\Statement\Controller\StatementPdf;
use App\Statement\Controller\StatementPost;
use App\Statement\Controller\StatementShowPage;

return (new ModuleManifest('statement'))
    ->requires('site')
    ->route(RouteMethod::Get, '/statements', StatementPage::class)
    ->route(RouteMethod::Post, '/statements', StatementPost::class)
    ->route(RouteMethod::Get, '/statements/{id:int}', StatementShowPage::class)
    ->route(RouteMethod::Get, '/statements/{id:int}/pdf', StatementPdf::class);
