<?php

declare(strict_types=1);

/*
 * The scaffold module's manifest, made by bin/aic make:module (#122). The build
 * reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Scaffold\Block\ScaffoldBlock;
use App\Scaffold\Command\ScaffoldHello;
use App\Scaffold\Command\ScaffoldShelvesSeed;
use App\Scaffold\Controller\ScaffoldAboutPage;
use App\Scaffold\Controller\ScaffoldApi;
use App\Scaffold\Controller\ScaffoldBookListPage;
use App\Scaffold\Controller\ScaffoldDeliveryWebhook;
use App\Scaffold\Controller\ScaffoldEventListPage;
use App\Scaffold\Controller\ScaffoldGaugePage;
use App\Scaffold\Controller\ScaffoldLoanListPage;
use App\Scaffold\Controller\ScaffoldNoteDetailPage;
use App\Scaffold\Controller\ScaffoldPage;
use App\Scaffold\Controller\ScaffoldPinAction;
use App\Scaffold\Controller\ScaffoldPost;
use App\Scaffold\Controller\ScaffoldShelfExportDownload;
use App\Scaffold\Controller\ScaffoldShelfExportPost;
use App\Scaffold\Controller\ScaffoldTaskListPage;
use App\Scaffold\Job\ScaffoldDeliveryWebhookJob;
use App\Scaffold\Job\ScaffoldHelloJob;
use App\Scaffold\Job\ScaffoldShelfExportJob;
use App\Scaffold\Job\ScaffoldShelfExportPruneJob;
use App\Scaffold\Migration\AddScaffoldTasksStage;
use App\Scaffold\Migration\CreateScaffoldBooksTable;
use App\Scaffold\Migration\CreateScaffoldEventsTable;
use App\Scaffold\Migration\CreateScaffoldItemsTable;
use App\Scaffold\Migration\CreateScaffoldLoansTable;
use App\Scaffold\Migration\CreateScaffoldPermitsTable;
use App\Scaffold\Migration\CreateScaffoldPermitStampsTable;
use App\Scaffold\Migration\CreateScaffoldShelfExportsTable;
use App\Scaffold\Migration\CreateScaffoldShelvesTable;
use App\Scaffold\Migration\CreateScaffoldTasksScaffoldEventsTable;
use App\Scaffold\Migration\CreateScaffoldTasksTable;
use App\Scaffold\Migration\CreateScaffoldVisitsTable;

return (new ModuleManifest('scaffold'))
    ->requires('site')
    ->route(RouteMethod::Get, '/scaffold', ScaffoldPage::class)
    ->route(RouteMethod::Get, '/scaffold/gauge', ScaffoldGaugePage::class)
    ->route(RouteMethod::Get, '/scaffold/about', ScaffoldAboutPage::class)
    ->route(RouteMethod::Post, '/scaffold', ScaffoldPost::class)
    ->route(RouteMethod::Get, '/api/scaffold', ScaffoldApi::class)
    ->block(ScaffoldBlock::class)
    ->command('scaffold:hello', ScaffoldHello::class)
    ->job('scaffold-hello', ScaffoldHelloJob::class)
    ->migration(CreateScaffoldItemsTable::class)
    ->route(RouteMethod::Get, '/scaffold-tasks', ScaffoldTaskListPage::class)
    ->migration(CreateScaffoldTasksTable::class)
    ->permission('scaffold.scaffold-task.create', 'Create a scaffold task.')
    ->route(RouteMethod::Get, '/scaffold-events', ScaffoldEventListPage::class)
    ->migration(CreateScaffoldEventsTable::class)
    ->migration(CreateScaffoldTasksScaffoldEventsTable::class)
    ->migration(AddScaffoldTasksStage::class)
    ->permission('scaffold.scaffold-lookup', 'Call the scaffold.scaffold-lookup tool over MCP.')
    ->migration(CreateScaffoldShelvesTable::class)
    ->migration(CreateScaffoldBooksTable::class)
    ->migration(CreateScaffoldLoansTable::class)
    ->migration(CreateScaffoldVisitsTable::class)
    ->migration(CreateScaffoldPermitsTable::class)
    ->migration(CreateScaffoldPermitStampsTable::class)
    ->command('scaffold-shelves:seed', ScaffoldShelvesSeed::class)
    ->route(RouteMethod::Post, '/scaffold/delivery', ScaffoldDeliveryWebhook::class)
    ->job('scaffold-delivery-webhook', ScaffoldDeliveryWebhookJob::class)
    ->route(RouteMethod::Post, '/scaffold/scaffold-pin', ScaffoldPinAction::class)
    ->route(RouteMethod::Post, '/scaffold/exports', ScaffoldShelfExportPost::class)
    ->route(RouteMethod::Get, '/scaffold/exports/{id:uuid}', ScaffoldShelfExportDownload::class)
    ->job('scaffold-shelf-export', ScaffoldShelfExportJob::class)
    ->job('scaffold-shelf-export-prune', ScaffoldShelfExportPruneJob::class)
    ->schedule('scaffold-shelf-export-prune', 'scaffold-shelf-export-prune', [], '15 4 * * *')
    ->migration(CreateScaffoldShelfExportsTable::class)
    ->route(RouteMethod::Get, '/scaffold/books', ScaffoldBookListPage::class)
    ->route(RouteMethod::Get, '/scaffold/notes/{id:int}', ScaffoldNoteDetailPage::class)
    ->route(RouteMethod::Get, '/scaffold-loans', ScaffoldLoanListPage::class);
