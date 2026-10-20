<?php

declare(strict_types=1);

namespace App;

use AIC\Auth\Domain\EnablePgcrypto;
use AIC\Notification\Domain\CreateNotificationsTable;
use AIC\Queue\Domain\CreateJobTables;
use AIC\Schedule\Domain\CreateScheduleRuns;
use AIC\Schema\Contracts\Migration;
use AIC\Session\Domain\CreateSessionsTable;
use App\Migration\CreateContentPagesTable;
use App\Migration\CreateNotesTable;
use App\Migration\CreateUsersTable;

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
        ];
    }
}
