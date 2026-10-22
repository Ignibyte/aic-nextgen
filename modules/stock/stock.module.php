<?php

declare(strict_types=1);

/*
 * The stock module's manifest (#93): the stock page, whose list Tessera swaps
 * on its own, and the stock tables. bin/aic-build reads it; nothing reads it
 * at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Stock\Controller\StockPage;
use App\Stock\Controller\StockPost;
use App\Stock\Migration\AddStockCost;
use App\Stock\Migration\ConvertStockCost;
use App\Stock\Migration\CreateStockItemsTable;

return (new ModuleManifest('stock'))
    ->requires('site')
    ->route(RouteMethod::Get, '/stock', StockPage::class)
    ->route(RouteMethod::Post, '/stock', StockPost::class)
    ->migration(CreateStockItemsTable::class)
    ->migration(AddStockCost::class)
    ->migration(ConvertStockCost::class);
