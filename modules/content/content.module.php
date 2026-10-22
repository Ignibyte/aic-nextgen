<?php

declare(strict_types=1);

/*
 * The content module's manifest (#93): content pages by slug, their editing
 * under /account (which the auth module guards), and their table.
 * bin/aic-build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Content\Controller\ContentEditPage;
use App\Content\Controller\ContentListPage;
use App\Content\Controller\ContentNewPost;
use App\Content\Controller\ContentSavePost;
use App\Content\Controller\ContentShowPage;
use App\Content\Migration\CreateContentPagesTable;

return (new ModuleManifest('content'))
    ->requires('site')
    ->route(RouteMethod::Get, '/pages', ContentListPage::class)
    ->route(RouteMethod::Get, '/pages/{slug}', ContentShowPage::class)
    ->route(RouteMethod::Post, '/account/pages', ContentNewPost::class)
    ->route(RouteMethod::Get, '/account/pages/{slug}', ContentEditPage::class)
    ->route(RouteMethod::Post, '/account/pages/{slug}', ContentSavePost::class)
    ->migration(CreateContentPagesTable::class);
