<?php

declare(strict_types=1);

namespace App;

use AIC\Auth\Domain\EnablePgcrypto;
use AIC\Notification\Domain\CreateNotificationsTable;
use AIC\Queue\Domain\CreateJobTables;
use AIC\Schedule\Domain\CreateScheduleRuns;
use AIC\Schema\Contracts\Migration;
use AIC\Session\Domain\CreateSessionsTable;
use AIC\Tessera\Domain\CreateTesseraWritesTable;
use App\Migration\CreateContentPagesTable;
use App\Migration\CreateNotesTable;
use App\Migration\CreateNoteTagsTable;
use App\Migration\CreateStockItemsTable;
use App\Migration\CreateSubscribersTable;
use App\Migration\CreateUsersTable;
use App\Migration\IndexNotesByCreation;
use App\Migration\MakeSubscriberEmailUnique;

/**
 * The app's migrations, the framework's among them, in the order they apply.
 */
final readonly class AppMigrations
{
    /**
     * @return list<Migration> The migrations, oldest first.
     */
    public function all(): array
    {
        return [
            new CreateNotesTable(), new CreateSessionsTable(), new EnablePgcrypto(), new CreateUsersTable(), new CreateJobTables(),
            new CreateScheduleRuns(),
            new CreateNotificationsTable(),
            new CreateContentPagesTable(),
            new CreateNoteTagsTable(),
            new IndexNotesByCreation(),
            new CreateSubscribersTable(),
            new MakeSubscriberEmailUnique(),
            new CreateStockItemsTable(),
            new CreateTesseraWritesTable(),
        ];
    }
}
