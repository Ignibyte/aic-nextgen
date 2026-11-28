<?php

declare(strict_types=1);

namespace AIC\Socket\Application;

use AIC\Database\Contracts\Database;
use AIC\Database\Contracts\QueryException;
use AIC\Socket\Domain\FrameCodec;
use AIC\Socket\Domain\WireFrame;

/**
 * #314's broadcasts sent to the sockets (#32): the server listens on
 * `aic_broadcasts` from the newest row there when it starts, and after a
 * notification sends each newer row's data, as a text frame, to every opened
 * connection hearing its channel. It keeps the last id it sent,
 * so it is the server's one other mutable object.
 */
final class SocketFanout
{
    /**
     * The newest row when the server starts.
     */
    private const string NEWEST = 'SELECT coalesce(max(id), 0) AS last FROM aic_broadcasts';

    /**
     * The rows after the last one sent, oldest first.
     */
    private const string DUE = 'SELECT id, channel, data FROM aic_broadcasts WHERE id > ? ORDER BY id LIMIT 500';

    /**
     * The last row sent.
     */
    private int $last = 0;

    /**
     * @param Database   $database   The database, with `aic_broadcasts`.
     * @param FrameCodec $frameCodec Writes the frames.
     */
    public function __construct(
        private readonly Database $database,
        private readonly FrameCodec $frameCodec = new FrameCodec(),
    ) {
    }

    /**
     * @throws QueryException When the table cannot be read or listened to.
     */
    public function start(): void
    {
        $this->database->listen('aic_broadcasts');
        $this->last = $this->database->fetchOne(self::NEWEST, [])->int('last');
    }

    /**
     * @param Connections $connections The connections.
     *
     * @throws QueryException When the broadcasts cannot be read.
     */
    public function deliver(Connections $connections): void
    {
        if (!$this->woken()) {
            return;
        }

        foreach ($this->database->fetchAll(self::DUE, [$this->last]) as $row) {
            $this->last = $row->int('id');
            $this->send($connections, $row->string('channel'), $row->string('data'));
        }
    }

    /**
     * @throws QueryException When the wait fails.
     *
     * @return bool Whether a broadcast was published since the last look; every waiting notification is taken.
     */
    private function woken(): bool
    {
        $woken = false;

        while ($this->database->notification(0) !== '') {
            $woken = true;
        }

        return $woken;
    }

    /**
     * @param Connections $connections The connections.
     * @param string      $channel     The broadcast's channel.
     * @param string      $data        Its data.
     */
    private function send(Connections $connections, string $channel, string $data): void
    {
        $frame = $this->frameCodec->encode(WireFrame::TEXT, $data);

        foreach ($connections->hearing($channel) as $id) {
            $connections->write($id, $frame);
        }
    }
}
