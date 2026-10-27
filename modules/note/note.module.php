<?php

declare(strict_types=1);

/*
 * The note module's manifest (#93; stock's pages and table joined it in #126):
 * CRUD on notes, their tips block, the stock page, and the notes and stock
 * tables. The build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Note\Block\NoteTipsBlock;
use App\Note\Command\StockSample;
use App\Note\Controller\NoteCreatePost;
use App\Note\Controller\NoteDeletePost;
use App\Note\Controller\NoteListPage;
use App\Note\Controller\NoteShowPage;
use App\Note\Controller\NoteUpdatePost;
use App\Note\Controller\StockPage;
use App\Note\Controller\StockPost;
use App\Note\Migration\AddStockCost;
use App\Note\Migration\AddStockQuantity;
use App\Note\Migration\ConvertStockCost;
use App\Note\Migration\CreateNotesTable;
use App\Note\Migration\CreateNoteTagsTable;
use App\Note\Migration\CreateStockItemsTable;
use App\Note\Migration\IndexNotesByCreation;

return (new ModuleManifest('note'))
    ->requires('site')
    ->route(RouteMethod::Get, '/notes', NoteListPage::class)
    ->route(RouteMethod::Post, '/notes', NoteCreatePost::class)
    ->route(RouteMethod::Get, '/notes/{id:int}', NoteShowPage::class)
    ->route(RouteMethod::Post, '/notes/{id:int}', NoteUpdatePost::class)
    ->route(RouteMethod::Post, '/notes/{id:int}/delete', NoteDeletePost::class)
    ->route(RouteMethod::Get, '/stock', StockPage::class)
    ->route(RouteMethod::Post, '/stock', StockPost::class)
    ->block(NoteTipsBlock::class)
    ->command('stock:sample', StockSample::class)
    ->migration(CreateNotesTable::class)
    ->migration(CreateNoteTagsTable::class)
    ->migration(IndexNotesByCreation::class)
    ->migration(CreateStockItemsTable::class)
    ->migration(AddStockCost::class)
    ->migration(ConvertStockCost::class)
    ->migration(AddStockQuantity::class);
