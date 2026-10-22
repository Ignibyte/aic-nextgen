<?php

declare(strict_types=1);

/*
 * The note module's manifest (#93): CRUD on notes, their tips block, and the
 * notes tables. bin/aic-build reads it; nothing reads it at run time.
 */

use AIC\Module\Contracts\ModuleManifest;
use AIC\Module\Contracts\RouteMethod;
use App\Note\Block\NoteTipsBlock;
use App\Note\Controller\NoteCreatePost;
use App\Note\Controller\NoteDeletePost;
use App\Note\Controller\NoteListPage;
use App\Note\Controller\NoteShowPage;
use App\Note\Controller\NoteUpdatePost;
use App\Note\Migration\CreateNotesTable;
use App\Note\Migration\CreateNoteTagsTable;
use App\Note\Migration\IndexNotesByCreation;

return (new ModuleManifest('note'))
    ->requires('site')
    ->route(RouteMethod::Get, '/notes', NoteListPage::class)
    ->route(RouteMethod::Post, '/notes', NoteCreatePost::class)
    ->route(RouteMethod::Get, '/notes/{id:int}', NoteShowPage::class)
    ->route(RouteMethod::Post, '/notes/{id:int}', NoteUpdatePost::class)
    ->route(RouteMethod::Post, '/notes/{id:int}/delete', NoteDeletePost::class)
    ->block(NoteTipsBlock::class)
    ->migration(CreateNotesTable::class)
    ->migration(CreateNoteTagsTable::class)
    ->migration(IndexNotesByCreation::class);
