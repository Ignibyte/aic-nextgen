<?php

declare(strict_types=1);

namespace AIC\Socket\Application;

use AIC\Database\Contracts\QueryException;
use AIC\Socket\Domain\Handshake;
use AIC\Socket\Domain\SocketConfig;
use AIC\Socket\Domain\SocketEntry;

use function array_key_exists;
use function array_keys;
use function in_array;
use function stream_select;
use function stream_socket_accept;
use function time;

use const PHP_INT_MAX;

/**
 * The socket server's loop (#32): one process, one `stream_select` over the
 * listening socket and every connection, a tick at most `tickMs` long. A new
 * connection is accepted (or refused 503 past the cap), a readable one has
 * its upgrade or its frames handled, and after each tick the broadcasts are
 * sent.
 */
final readonly class SocketServer
{
    /**
     * @param SocketConfig  $socketConfig  The cap and the tick.
     * @param SocketUpgrade $socketUpgrade Opens a connection.
     * @param FrameDispatch $frameDispatch Handles an opened one's frames.
     * @param SocketFanout  $socketFanout  Sends the broadcasts.
     * @param Handshake     $handshake     Writes the 503.
     */
    public function __construct(
        private SocketConfig $socketConfig,
        private SocketUpgrade $socketUpgrade,
        private FrameDispatch $frameDispatch,
        private SocketFanout $socketFanout,
        private Handshake $handshake = new Handshake(),
    ) {
    }

    /**
     * @param list<resource>             $listener The listening socket in a one-slot list: elephc types a parameter
     *                                             with no declared type as an int, but a list slot keeps a resource's.
     * @param array<string, SocketEntry> $entries  Every declared socket by path.
     * @param int                        $seconds  How long to serve; 0 until the process is stopped.
     *
     * @throws QueryException When the database fails.
     */
    public function serve(array $listener, array $entries, int $seconds): void
    {
        $connections = new Connections();
        $until = $seconds === 0
            ? PHP_INT_MAX
            : time() + $seconds;
        $this->socketFanout->start();

        while (time() < $until) {
            $this->tick($listener, $connections, $entries);
        }

        foreach (array_keys($connections->streams()) as $id) {
            $connections->drop($id);
        }
    }

    /**
     * @param list<resource>             $listener    The listening socket, in its one-slot list.
     * @param Connections                $connections The connections.
     * @param array<string, SocketEntry> $entries     Every declared socket by path.
     *
     * @throws QueryException When the database fails.
     */
    private function tick(array $listener, Connections $connections, array $entries): void
    {
        foreach ($this->readable($listener, $connections) as $stream) {
            in_array($stream, $listener, true)
                ? $this->accept($listener, $connections)
                : $this->read((int) $stream, $connections, $entries);
        }

        $this->socketFanout->deliver($connections);
    }

    /**
     * @param list<resource> $listener    The listening socket, in its one-slot list.
     * @param Connections    $connections The connections.
     *
     * @return list<resource> The ones with something to read within a tick: a connection to accept, or bytes.
     */
    private function readable(array $listener, Connections $connections): array
    {
        $read = $listener;

        foreach ($connections->streams() as $stream) {
            $read[] = $stream;
        }

        $write = null;
        $except = null;

        $ready = [];

        if (stream_select($read, $write, $except, 0, $this->socketConfig->tickMs * 1_000) !== false) {
            foreach ($read as $stream) {
                $ready[] = $stream;
            }
        }

        return $ready;
    }

    /**
     * @param list<resource> $listener    The listening socket, readable, in its one-slot list.
     * @param Connections    $connections The connections.
     */
    private function accept(array $listener, Connections $connections): void
    {
        foreach ($listener as $socket) {
            $stream = stream_socket_accept($socket, 0);

            if ($stream === false) {
                continue;
            }

            $this->admitted($connections->add([$stream]), $connections);
        }
    }

    /**
     * @param int         $id          A connection just accepted.
     * @param Connections $connections The connections.
     */
    private function admitted(int $id, Connections $connections): void
    {
        if ($connections->count() <= $this->socketConfig->maxConnections) {
            return;
        }

        $connections->write($id, $this->handshake->refused(503, 'Service Unavailable'));
        $connections->drop($id);
    }

    /**
     * @param int                        $id          A readable connection.
     * @param Connections                $connections The connections.
     * @param array<string, SocketEntry> $entries     Every declared socket by path.
     *
     * @throws QueryException When the session or the roles cannot be read.
     */
    private function read(int $id, Connections $connections, array $entries): void
    {
        if (!$connections->receive($id)) {
            $connections->drop($id);

            return;
        }

        if (!array_key_exists($id, $connections->opened())) {
            $this->socketUpgrade->open($id, $connections, $entries);
        }

        if (!array_key_exists($id, $connections->opened())) {
            return;
        }

        $this->frameDispatch->drain($id, $connections, $entries);
    }
}
