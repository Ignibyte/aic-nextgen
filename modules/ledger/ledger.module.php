<?php

declare(strict_types=1);

/*
 * The ledger module's manifest, made by bin/aic make:module (#122). The build
 * reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Ledger\Controller\LedgerGuardsApi;
use App\Ledger\Migration\CreateLedgerEntriesTable;
use App\Ledger\Migration\CreateLedgerReviewsTable;

return (new ModuleManifest('ledger'))
    ->requires('site')
    ->route(RouteMethod::Get, '/ledger/guards', LedgerGuardsApi::class)
    ->migration(CreateLedgerEntriesTable::class)
    ->migration(CreateLedgerReviewsTable::class);
