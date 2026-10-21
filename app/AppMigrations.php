<?php

declare(strict_types=1);

namespace App;

use AIC\Schema\Contracts\Migration;
use App\Generated\ModuleMigrations;
use App\Migration\AddStockCost;
use App\Migration\ConvertStockCost;
use App\Migration\CreateContentPagesTable;
use App\Migration\CreateNotesTable;
use App\Migration\CreateNoteTagsTable;
use App\Migration\CreateStockItemsTable;
use App\Migration\CreateSubscribersTable;
use App\Migration\CreateUsersTable;
use App\Migration\IndexNotesByCreation;
use App\Migration\MakeSubscriberEmailUnique;

use function array_merge;

/**
 * The app's migrations, after the framework's, in the order they apply; the
 * modules' come last, from the generated ModuleMigrations (#92).
 */
final readonly class AppMigrations
{
    /**
     * @return list<Migration> The migrations, oldest first.
     */
    public function all(): array
    {
        return array_merge((new FrameworkMigrations())->all(), [
            new CreateNotesTable(),
            new CreateUsersTable(),
            new CreateContentPagesTable(),
            new CreateNoteTagsTable(),
            new IndexNotesByCreation(),
            new CreateSubscribersTable(),
            new MakeSubscriberEmailUnique(),
            new CreateStockItemsTable(),
            new AddStockCost(),
            new ConvertStockCost(),
        ], (new ModuleMigrations())->all());
    }
}
