<?php

declare(strict_types=1);

/*
 * The ledger module's manifest, made by bin/aic make:module (#122). The build
 * reads it; nothing reads it at run time.
 */

use AIC\Cache\Application\RateLimitMiddleware;
use AIC\Module\Contracts\MiddlewarePosition;
use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Ledger\Controller\LedgerAccountsApi;
use App\Ledger\Controller\LedgerCacheApi;
use App\Ledger\Controller\LedgerCategoriesApi;
use App\Ledger\Controller\LedgerFeedPage;
use App\Ledger\Controller\LedgerFeedPost;
use App\Ledger\Controller\LedgerFeedStream;
use App\Ledger\Controller\LedgerGuardsApi;
use App\Ledger\Controller\LedgerLimitedApi;
use App\Ledger\Controller\LedgerMeterPage;
use App\Ledger\Controller\LedgerNoticesApi;
use App\Ledger\Controller\LedgerStandingApi;
use App\Ledger\Job\LedgerAccountOpenedJob;
use App\Ledger\Migration\AddLedgerAccountsStanding;
use App\Ledger\Migration\CreateLedgerAccountsTable;
use App\Ledger\Migration\CreateLedgerCategoriesTable;
use App\Ledger\Migration\CreateLedgerEntriesTable;
use App\Ledger\Migration\CreateLedgerNoticesTable;
use App\Ledger\Migration\CreateLedgerReviewsTable;
use App\Ledger\Service\LedgerLimits;

return (new ModuleManifest('ledger'))
    ->requires('site')
    ->route(RouteMethod::Get, '/ledger/guards', LedgerGuardsApi::class)
    ->migration(CreateLedgerEntriesTable::class)
    ->migration(CreateLedgerReviewsTable::class)
    ->migration(CreateLedgerAccountsTable::class)
    ->migration(CreateLedgerNoticesTable::class)
    ->job('ledger-account-opened', LedgerAccountOpenedJob::class)
    ->route(RouteMethod::Get, '/ledger/accounts/round-trip', LedgerAccountsApi::class)
    ->route(RouteMethod::Get, '/ledger/notices', LedgerNoticesApi::class)
    ->migration(AddLedgerAccountsStanding::class)
    ->route(RouteMethod::Get, '/ledger/accounts/standing', LedgerStandingApi::class)
    ->permission('ledger.freeze', 'Freeze a ledger account, so nothing posts to it until it thaws.')
    ->route(RouteMethod::Get, '/ledger/cache', LedgerCacheApi::class)
    ->route(RouteMethod::Get, '/ledger/limited', LedgerLimitedApi::class)
    ->middleware(RateLimitMiddleware::class, MiddlewarePosition::AfterSession)
    ->factory(LedgerLimits::class)
    ->migration(CreateLedgerCategoriesTable::class)
    ->route(RouteMethod::Get, '/ledger/categories', LedgerCategoriesApi::class)
    ->permission('ledger.feed', 'Read and post to the ledger feed, which streams each post to every open page.')
    ->route(RouteMethod::Get, '/ledger/feed', LedgerFeedPage::class, needs: 'ledger.feed')
    ->route(RouteMethod::Post, '/ledger/feed', LedgerFeedPost::class, needs: 'ledger.feed')
    ->route(RouteMethod::Get, '/ledger/feed/events', LedgerFeedStream::class, needs: 'ledger.feed')
    ->route(RouteMethod::Get, '/ledger/meter', LedgerMeterPage::class);
