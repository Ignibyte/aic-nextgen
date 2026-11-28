<?php

declare(strict_types=1);

namespace AIC\Socket\Application;

use function array_key_exists;
use function count;
use function fclose;
use function fread;
use function fwrite;
use function in_array;
use function strlen;
use function substr;

/**
 * The socket server's open connections (#32), by id: each one's stream, the
 * bytes it sent that no frame has taken, its socket's path once the upgrade
 * is accepted, its user, and the text of a message whose parts are still
 * arriving. It is the one mutable table the server's loop keeps; a write to a
 * dropped connection does nothing.
 */
final class Connections
{
    /**
     * @var array<int, resource>
     */
    private array $streams = [];

    /**
     * @var array<int, string>
     */
    private array $buffers = [];

    /**
     * Each opened connection's socket path; a connection is absent until its upgrade is accepted.
     *
     * @var array<int, string>
     */
    private array $paths = [];

    /**
     * @var array<int, string>
     */
    private array $users = [];

    /**
     * The broadcast channels each opened connection hears, as its socket's last answer named them.
     *
     * @var array<int, list<string>>
     */
    private array $channels = [];

    /**
     * The text so far of each message that arrives in parts.
     *
     * @var array<int, string>
     */
    private array $parts = [];

    /**
     * @param list<resource> $stream An accepted connection, in a one-slot list (elephc types a parameter with no
     *                               declared type as an int; a list slot keeps a resource's type).
     *
     * @return int Its id; 0 for an empty list.
     */
    public function add(array $stream): int
    {
        $id = 0;

        foreach ($stream as $accepted) {
            $id = (int) $accepted;
            $this->streams[$id] = $accepted;
            $this->buffers[$id] = '';
        }

        return $id;
    }

    /**
     * @return array<int, resource> Every connection's stream by id.
     */
    public function streams(): array
    {
        return $this->streams;
    }

    /**
     * @return int How many connections are open or opening.
     */
    public function count(): int
    {
        return count($this->streams);
    }

    /**
     * @param int $id A connection.
     *
     * @return bool Whether it is still there.
     */
    public function has(int $id): bool
    {
        return array_key_exists($id, $this->streams);
    }

    /**
     * @param int $id A connection the select loop found readable.
     *
     * @return bool Whether it sent bytes, now in its buffer; false when it has gone.
     */
    public function receive(int $id): bool
    {
        $stream = $this->streams[$id] ?? null;
        $chunk = $stream === null
            ? false
            : fread($stream, 65_536);

        if ($chunk === false || $chunk === '') {
            return false;
        }

        $this->buffers[$id] = ($this->buffers[$id] ?? '') . $chunk;

        return true;
    }

    /**
     * @param int $id A connection.
     *
     * @return string What it sent that no frame has taken.
     */
    public function buffer(int $id): string
    {
        return $this->buffers[$id] ?? '';
    }

    /**
     * @param int $id   A connection.
     * @param int $used How many bytes of its buffer were taken.
     */
    public function consume(int $id, int $used): void
    {
        $this->buffers[$id] = substr($this->buffer($id), $used);
    }

    /**
     * @param int    $id   A connection whose upgrade was accepted.
     * @param string $path Its socket's path.
     * @param string $user Its user's id; empty for a visitor.
     */
    public function open(int $id, string $path, string $user): void
    {
        $this->paths[$id] = $path;
        $this->users[$id] = $user;
    }

    /**
     * @return array<int, string> Each opened connection's socket path by id.
     */
    public function opened(): array
    {
        return $this->paths;
    }

    /**
     * @param int          $id       An opened connection.
     * @param list<string> $channels The channels it hears from now on.
     */
    public function listen(int $id, array $channels): void
    {
        if (!array_key_exists($id, $this->paths)) {
            return;
        }

        $this->channels[$id] = $channels;
    }

    /**
     * @param string $channel A broadcast's channel.
     *
     * @return list<int> The opened connections that hear it.
     */
    public function hearing(string $channel): array
    {
        $ids = [];

        foreach ($this->channels as $id => $channels) {
            if (!in_array($channel, $channels, true)) {
                continue;
            }

            $ids[] = $id;
        }

        return $ids;
    }

    /**
     * @param int $id A connection.
     *
     * @return string Its user's id; empty for a visitor or a connection not opened.
     */
    public function user(int $id): string
    {
        return $this->users[$id] ?? '';
    }

    /**
     * @param int $id A connection.
     *
     * @return string|null The text so far of a message arriving in parts; null when none is.
     */
    public function part(int $id): ?string
    {
        return $this->parts[$id] ?? null;
    }

    /**
     * @param int         $id   A connection.
     * @param string|null $part The text so far of its message in parts; null once it is whole.
     */
    public function keep(int $id, ?string $part): void
    {
        if ($part === null) {
            unset($this->parts[$id]);

            return;
        }

        $this->parts[$id] = $part;
    }

    /**
     * @param int    $id    A connection.
     * @param string $bytes What to send it.
     */
    public function write(int $id, string $bytes): void
    {
        $stream = $this->streams[$id] ?? null;

        for ($sent = 0, $length = strlen($bytes); $stream !== null && $sent < $length;) {
            $written = fwrite($stream, substr($bytes, $sent));
            $sent = $written === false || $written === 0
                ? $length
                : $sent + $written;
        }
    }

    /**
     * @param int $id A connection to close and forget.
     */
    public function drop(int $id): void
    {
        $stream = $this->streams[$id] ?? null;

        if ($stream !== null) {
            fclose($stream);
        }

        unset($this->streams[$id], $this->buffers[$id], $this->paths[$id], $this->users[$id], $this->channels[$id]);
        unset($this->parts[$id]);
    }
}
