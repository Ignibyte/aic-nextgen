<?php

declare(strict_types=1);

namespace AIC\Broadcast\Application;

use AIC\Broadcast\Contracts\Broadcast;
use AIC\Broadcast\Contracts\BroadcastEvents;
use AIC\Broadcast\Domain\BroadcastConfig;
use AIC\Broadcast\Domain\Channel;
use AIC\Controller\Contracts\EventSource;
use AIC\Controller\Contracts\ServerEvent;
use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Kernel\Domain\Monotonic;
use AIC\Tessera\Domain\TesseraEvents;
use Override;

use function intdiv;
use function min;

/**
 * A channel's events for one connection (#314). It listens before it first
 * reads, then sends each event after the last one the client saw, waits on
 * the database's notifications while there is none, sends a keep-alive when
 * one wait passes idle, and ends the connection as a reconnect once
 * `holdSeconds` have passed (#202), so the client comes back with the last id
 * and the worker is free between. A stream refused a slot is one reconnect
 * that tells the client when to try again.
 */
final class BroadcastSource implements EventSource
{
    /**
     * The one Postgres channel every publish notifies.
     */
    private const string LISTENS = 'aic_broadcasts';

    /**
     * The reconnect delay a hold's end sets, in milliseconds: short, since the client misses nothing, and set every
     * time, since a refusal's longer `retry:` would otherwise stay with the client.
     */
    private const int RESUME_MS = 100;

    /**
     * When the connection ends, by the monotonic clock in microseconds; 0 until the first event is asked for.
     */
    private int $deadline = 0;

    /**
     * @param Database        $database        The request's database connection.
     * @param Channel         $channel         The channel.
     * @param BroadcastEvents $broadcastEvents Makes each event the page reads.
     * @param BroadcastConfig $broadcastConfig The hold, the keep-alive and the refusal's retry.
     * @param bool            $admitted        Whether the stream took a slot.
     * @param int             $after           The last event id the client saw; -1 for none, so only new events come.
     * @param TesseraEvents   $tesseraEvents   Makes the keep-alive and the reconnect.
     * @param Monotonic       $monotonic       The clock the hold is timed by.
     */
    public function __construct(
        private readonly Database $database,
        private readonly Channel $channel,
        private readonly BroadcastEvents $broadcastEvents,
        private readonly BroadcastConfig $broadcastConfig,
        private readonly bool $admitted,
        private int $after,
        private readonly TesseraEvents $tesseraEvents = new TesseraEvents(),
        private readonly Monotonic $monotonic = new Monotonic(),
    ) {
    }

    /**
     * @throws QueryException When a read, LISTEN or a wait fails.
     *
     * @return ServerEvent A keep-alive first, once it listens, so the headers go out and a client that has them
     *                     misses nothing; then the next event, a keep-alive after an idle wait, and a reconnect when
     *                     the hold is over; one reconnect when the stream was refused.
     */
    #[Override]
    public function next(): ServerEvent
    {
        if (!$this->admitted) {
            return $this->tesseraEvents->reconnect($this->broadcastConfig->refusedRetryMs);
        }

        if ($this->deadline === 0) {
            $this->start();

            return $this->tesseraEvents->keepAlive(false);
        }

        return $this->awaited($this->due());
    }

    /**
     * @param Broadcast|null $broadcast The channel's next event, if there is one already.
     *
     * @throws QueryException When a read or a wait fails.
     *
     * @return ServerEvent That event, or the first published while the hold lasts; a keep-alive after an idle
     *                     wait; the reconnect when the hold is over.
     */
    private function awaited(?Broadcast $broadcast): ServerEvent
    {
        while (!$broadcast instanceof Broadcast && $this->left() > 0) {
            if (!$this->woken()) {
                return $this->idle();
            }

            $broadcast = $this->due();
        }

        return $broadcast instanceof Broadcast
            ? $this->broadcastEvents->event($broadcast)
            : $this->resume();
    }

    /**
     * @throws QueryException When the wait fails.
     *
     * @return bool Whether a notification came within the keep-alive or what is left of the hold.
     */
    private function woken(): bool
    {
        return $this->database->notification(min($this->left(), $this->broadcastConfig->keepAliveSeconds * 1_000)) !== '';
    }

    /**
     * On the first event asked for: LISTEN, the hold's deadline, and where a stream with no last id starts.
     *
     * @throws QueryException When LISTEN or the read fails.
     */
    private function start(): void
    {
        $this->database->listen(self::LISTENS);
        $this->deadline = $this->monotonic->microseconds() + $this->broadcastConfig->holdSeconds * 1_000_000;

        if ($this->after >= 0) {
            return;
        }

        $sql = 'SELECT COALESCE(MAX(id), 0) AS id FROM aic_broadcasts WHERE channel = ?';
        $this->after = $this->database->fetchOne($sql, [$this->channel->name])->int('id');
    }

    /**
     * @throws QueryException When the read fails.
     *
     * @return Broadcast|null The channel's next event after the last one sent; null when there is none yet.
     */
    private function due(): ?Broadcast
    {
        $sql = 'SELECT id, data FROM aic_broadcasts WHERE channel = ? AND id > ? ORDER BY id LIMIT 1';

        foreach ($this->database->fetchAll($sql, [$this->channel->name, $this->after]) as $row) {
            $this->after = $row->int('id');

            return new Broadcast($this->after, $row->string('data'));
        }

        return null;
    }

    /**
     * @return int The milliseconds left of the hold; 0 or less once it is over.
     */
    private function left(): int
    {
        return intdiv($this->deadline - $this->monotonic->microseconds(), 1_000);
    }

    /**
     * @return ServerEvent The hold's end: a reconnect soon, naming the last event this connection reached, so a
     *                     quiet channel's next connection resumes there and a post in between is not lost.
     */
    private function resume(): ServerEvent
    {
        return $this->tesseraEvents->reconnect(self::RESUME_MS, (string) $this->after);
    }

    /**
     * @return ServerEvent A keep-alive while the hold lasts; the reconnect once it is over.
     */
    private function idle(): ServerEvent
    {
        return $this->left() > 0
            ? $this->tesseraEvents->keepAlive(false)
            : $this->resume();
    }
}
