<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Auth\Domain\EnablePgcrypto;
use AIC\Notification\Domain\AddNotificationInbox;
use AIC\Notification\Domain\CreateNotificationsTable;
use AIC\Profiler\Domain\CreateProfilesTable;
use AIC\Queue\Domain\CreateJobTables;
use AIC\Schedule\Domain\CreateScheduleRuns;
use AIC\Schema\Contracts\Migration;
use AIC\Session\Domain\CreateSessionsTable;
use AIC\Tessera\Domain\CreateTesseraWritesTable;

/**
 * The framework systems' migrations the app uses, in the order they apply.
 * None refers to an app table, so they apply before the app's (#86).
 */
final readonly class FrameworkMigrations
{
    /**
     * @return list<Migration> The migrations, oldest first.
     */
    public function all(): array
    {
        return [
            new CreateSessionsTable(),
            new EnablePgcrypto(),
            new CreateJobTables(),
            new CreateScheduleRuns(),
            new CreateNotificationsTable(),
            new CreateTesseraWritesTable(),
            new AddNotificationInbox(),
            new CreateProfilesTable(),
        ];
    }
}
