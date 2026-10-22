<?php

declare(strict_types=1);

/*
 * The faq module's manifest (#92): what it brings to the app. bin/aic-build
 * reads it and writes the wiring under generated/; nothing reads it at run
 * time. Its classes live under src/ as App\Faq\.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Faq\Block\FaqNoteBlock;
use App\Faq\Component\FaqList;
use App\Faq\Controller\FaqApi;
use App\Faq\Controller\FaqPage;
use App\Faq\Service\DemoFaqEntries;
use App\Faq\Service\FaqEntries;

return (new ModuleManifest('faq'))
    ->requires('site')
    ->route(RouteMethod::Get, '/faq', FaqPage::class)
    ->route(RouteMethod::Get, '/api/faq', FaqApi::class)
    ->binding(FaqEntries::class, DemoFaqEntries::class)
    ->block(FaqNoteBlock::class)
    ->export(FaqList::class);
