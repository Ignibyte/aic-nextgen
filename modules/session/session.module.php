<?php

declare(strict_types=1);

/*
 * The session module's manifest (#93): the visit counter and the upload demo,
 * with the upload rules as a factory. bin/aic-build reads it; nothing reads it
 * at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Session\Controller\UploadsPage;
use App\Session\Controller\UploadsPost;
use App\Session\Controller\VisitsForget;
use App\Session\Controller\VisitsPage;
use App\Session\Service\UploadRules;

return (new ModuleManifest('session'))
    ->requires('site')
    ->route(RouteMethod::Get, '/visits', VisitsPage::class)
    ->route(RouteMethod::Post, '/visits/forget', VisitsForget::class)
    ->route(RouteMethod::Get, '/uploads', UploadsPage::class)
    ->route(RouteMethod::Post, '/uploads', UploadsPost::class)
    ->factory(UploadRules::class);
