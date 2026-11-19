<?php

declare(strict_types=1);

namespace AIC\Broadcast\Application;

use AIC\Broadcast\Domain\Channel;
use AIC\Config\Domain\AppConfig;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;

/**
 * Publishes an event to a channel (#314), from a controller or a Job: one
 * statement inserts the row into `aic_broadcasts`, notifies the Postgres
 * channel `aic_broadcasts` with the channel's name, and removes the rows
 * older than `BroadcastConfig::$keepSeconds`. Inside an open transaction it
 * joins it, and the streams wake only when it commits.
 */
final readonly class Broadcaster
{
    private const string PUBLISH = 'WITH pruned AS (DELETE FROM aic_broadcasts'
        . " WHERE published_at < now() - ? * interval '1 second'),"
        . ' added AS (INSERT INTO aic_broadcasts (channel, data, published_at) VALUES (?, ?, now()) RETURNING id)'
        . " SELECT id, pg_notify('aic_broadcasts', ?) AS notified FROM added";

    /**
     * @param Database  $database  The database, with `aic_broadcasts`.
     * @param AppConfig $appConfig The app's settings: how long an event is kept.
     */
    public function __construct(
        private Database $database,
        private AppConfig $appConfig,
    ) {
    }

    /**
     * @param Channel $channel The channel.
     * @param string  $data    The event, any text; a module encodes its own, and its `BroadcastEvents` reads it.
     *
     * @throws QueryException When the write fails.
     */
    public function publish(Channel $channel, string $data): void
    {
        $params = [$this->appConfig->broadcast->keepSeconds, $channel->name, $data, $channel->name];
        $this->database->fetchOne(self::PUBLISH, $params);
    }
}
