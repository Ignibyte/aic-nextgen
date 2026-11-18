<?php

declare(strict_types=1);

/*
 * The ledger module's manifest, made by bin/aic make:module (#122). The build
 * reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Ledger\Controller\LedgerAccountsApi;
use App\Ledger\Controller\LedgerGuardsApi;
use App\Ledger\Controller\LedgerNoticesApi;
use App\Ledger\Job\LedgerAccountOpenedJob;
use App\Ledger\Migration\CreateLedgerAccountsTable;
use App\Ledger\Migration\CreateLedgerEntriesTable;
use App\Ledger\Migration\CreateLedgerNoticesTable;
use App\Ledger\Migration\CreateLedgerReviewsTable;

return (new ModuleManifest('ledger'))
    ->requires('site')
    ->route(RouteMethod::Get, '/ledger/guards', LedgerGuardsApi::class)
    ->migration(CreateLedgerEntriesTable::class)
    ->migration(CreateLedgerReviewsTable::class)
    ->migration(CreateLedgerAccountsTable::class)
    ->migration(CreateLedgerNoticesTable::class)
    ->job('ledger-account-opened', LedgerAccountOpenedJob::class)
    ->route(RouteMethod::Get, '/ledger/accounts/round-trip', LedgerAccountsApi::class)
    ->route(RouteMethod::Get, '/ledger/notices', LedgerNoticesApi::class);
