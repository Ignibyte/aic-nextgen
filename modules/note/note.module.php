<?php

declare(strict_types=1);

/*
 * The note module's manifest (#93; stock's pages and table joined it in #126):
 * CRUD on notes, their tips block, the stock page, its report, its CSV
 * export (#209) and XML feed (#233), the notes and stock tables, and the
 * permission to delete a pinned note (#304). The build reads it; nothing
 * reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Note\Block\NoteTipsBlock;
use App\Note\Command\StockSample;
use App\Note\Controller\NoteCreatePost;
use App\Note\Controller\NoteDeletePost;
use App\Note\Controller\NoteListPage;
use App\Note\Controller\NoteMarkListPage;
use App\Note\Controller\NoteMarkRoundTripApi;
use App\Note\Controller\NoteRelationsApi;
use App\Note\Controller\NoteShowPage;
use App\Note\Controller\NoteTagPost;
use App\Note\Controller\NoteTagsPage;
use App\Note\Controller\NoteUpdatePost;
use App\Note\Controller\StockCsv;
use App\Note\Controller\StockDaysPage;
use App\Note\Controller\StockPage;
use App\Note\Controller\StockPost;
use App\Note\Controller\StockXml;
use App\Note\Migration\AddStockCost;
use App\Note\Migration\AddStockQuantity;
use App\Note\Migration\ConvertStockCost;
use App\Note\Migration\CreateNoteMarksTable;
use App\Note\Migration\CreateNoteMentionsTable;
use App\Note\Migration\CreateNotesStockItemsTable;
use App\Note\Migration\CreateNotesTable;
use App\Note\Migration\CreateNoteTagsTable;
use App\Note\Migration\CreateStockDaysView;
use App\Note\Migration\CreateStockItemsTable;
use App\Note\Migration\IndexNotesByCreation;

return (new ModuleManifest('note'))
    ->requires('site')
    ->route(RouteMethod::Get, '/notes', NoteListPage::class)
    ->route(RouteMethod::Post, '/notes', NoteCreatePost::class)
    ->route(RouteMethod::Get, '/notes/{id:int}', NoteShowPage::class)
    ->route(RouteMethod::Post, '/notes/{id:int}', NoteUpdatePost::class)
    ->route(RouteMethod::Put, '/notes/{id:int}', NoteUpdatePost::class)
    ->route(RouteMethod::Post, '/notes/{id:int}/delete', NoteDeletePost::class)
    ->route(RouteMethod::Get, '/stock', StockPage::class)
    ->route(RouteMethod::Post, '/stock', StockPost::class)
    ->route(RouteMethod::Get, '/notes/tags', NoteTagsPage::class)
    ->route(RouteMethod::Post, '/notes/tags', NoteTagPost::class)
    ->route(RouteMethod::Get, '/stock/days', StockDaysPage::class)
    ->route(RouteMethod::Get, '/stock.csv', StockCsv::class)
    ->route(RouteMethod::Get, '/stock.xml', StockXml::class)
    ->block(NoteTipsBlock::class)
    ->command('stock:sample', StockSample::class)
    ->migration(CreateNotesTable::class)
    ->migration(CreateNoteTagsTable::class)
    ->migration(IndexNotesByCreation::class)
    ->migration(CreateStockItemsTable::class)
    ->migration(AddStockCost::class)
    ->migration(ConvertStockCost::class)
    ->migration(AddStockQuantity::class)
    ->migration(CreateStockDaysView::class)
    ->permission('notes.delete-pinned', 'Delete a pinned note, one whose title starts with "Pinned:".')
    ->route(RouteMethod::Get, '/note-marks', NoteMarkListPage::class)
    ->migration(CreateNoteMarksTable::class)
    ->route(RouteMethod::Get, '/note-marks/round-trip', NoteMarkRoundTripApi::class)
    ->migration(CreateNotesStockItemsTable::class)
    ->migration(CreateNoteMentionsTable::class)
    ->route(RouteMethod::Get, '/notes/relations', NoteRelationsApi::class);
