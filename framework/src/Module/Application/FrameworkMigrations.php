<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Access\Domain\CreateAccessTables;
use AIC\Access\Domain\CreateTokensTable;
use AIC\Auth\Domain\EnablePgcrypto;
use AIC\Broadcast\Domain\CreateBroadcastTable;
use AIC\Cache\Domain\CreateCacheTables;
use AIC\Entity\Domain\CreateEntityAuditTable;
use AIC\Mcp\Domain\CreateMcpCallsTable;
use AIC\Notification\Domain\AddNotificationInbox;
use AIC\Notification\Domain\CreateNotificationsTable;
use AIC\OAuth\Domain\CreateOAuthTables;
use AIC\Profiler\Domain\CreateProfilesTable;
use AIC\Queue\Domain\QueueMigrations;
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
            ...(new QueueMigrations())->all(),
            new CreateScheduleRuns(),
            new CreateNotificationsTable(),
            new CreateTesseraWritesTable(),
            new AddNotificationInbox(),
            new CreateProfilesTable(),
            new CreateAccessTables(),
            new CreateEntityAuditTable(),
            new CreateCacheTables(),
            new CreateTokensTable(),
            new CreateBroadcastTable(),
            new CreateOAuthTables(),
            new CreateMcpCallsTable(),
        ];
    }
}
