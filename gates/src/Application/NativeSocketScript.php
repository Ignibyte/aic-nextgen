<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\SocketConversation;
use Gates\Domain\SocketSend;
use InvalidArgumentException;
use stdClass;

use function array_key_exists;
use function get_object_vars;
use function in_array;
use function is_bool;
use function is_int;
use function is_string;

/**
 * Reads the native site script's `sockets` (gate:23, #32): each an object with a `name`, a `path`, `origin` (true
 * for the server's own, false for another site's), the upgrade's `status` and `send`, a list of `{"frame": "text",
 * "data": "join lobby", "read": 1}` (`frame` is `text`, `binary` or `ping`).
 */
final readonly class NativeSocketScript
{
    /**
     * The frames a conversation may send.
     */
    private const array FRAMES = ['text', 'binary', 'ping'];

    /**
     * @param NativeSiteDocument $nativeSiteDocument Reads the script.
     */
    public function __construct(
        private NativeSiteDocument $nativeSiteDocument = new NativeSiteDocument(),
    ) {
    }

    /**
     * @param string $root The project's root.
     *
     * @throws InvalidArgumentException When the script, a conversation or a frame in it is malformed, naming which.
     *
     * @return list<SocketConversation> The conversations, in order; none when the script names none.
     */
    public function conversations(string $root): array
    {
        $conversations = [];

        foreach ($this->nativeSiteDocument->objects($root, 'sockets') as $index => $entry) {
            $where = NativeSiteScript::PATH . ': socket ' . $index;
            $conversations[] = new SocketConversation(
                self::text($entry, 'name', $where),
                self::text($entry, 'path', $where),
                self::flag($entry, $where),
                self::number($entry, 'status', $where),
                $this->sends($entry, $where),
            );
        }

        return $conversations;
    }

    /**
     * @param stdClass $entry A conversation's fields.
     * @param string   $where The conversation, for the message.
     *
     * @throws InvalidArgumentException When `send` is not a list of frames.
     *
     * @return list<SocketSend> Its frames.
     */
    private function sends(stdClass $entry, string $where): array
    {
        $sends = [];

        foreach ($this->nativeSiteDocument->entries($entry, 'send', $where) as $index => $frame) {
            $sends[] = self::send($frame, $where . ', frame ' . $index);
        }

        return $sends;
    }

    /**
     * @param stdClass $frame A frame's fields.
     * @param string   $where The frame, for the message.
     *
     * @throws InvalidArgumentException When a field is missing or wrong.
     *
     * @return SocketSend The frame.
     */
    private static function send(stdClass $frame, string $where): SocketSend
    {
        $kind = self::text($frame, 'frame', $where);

        return in_array($kind, self::FRAMES, true)
            ? new SocketSend($kind, self::text($frame, 'data', $where), self::number($frame, 'read', $where))
            : throw new InvalidArgumentException($where . '\'s "frame" is not text, binary or ping.');
    }

    /**
     * @param stdClass $fields An object.
     * @param string   $field  A string field.
     * @param string   $where  The object, for the message.
     *
     * @throws InvalidArgumentException When it is missing or not a string.
     *
     * @return string Its value.
     */
    private static function text(stdClass $fields, string $field, string $where): string
    {
        $values = get_object_vars($fields);

        return array_key_exists($field, $values) && is_string($values[$field])
            ? $values[$field]
            : throw new InvalidArgumentException($where . ' has no string at "' . $field . '".');
    }

    /**
     * @param stdClass $fields An object.
     * @param string   $field  An integer field.
     * @param string   $where  The object, for the message.
     *
     * @throws InvalidArgumentException When it is missing or not a non-negative integer.
     *
     * @return int Its value.
     */
    private static function number(stdClass $fields, string $field, string $where): int
    {
        $values = get_object_vars($fields);

        return array_key_exists($field, $values) && is_int($values[$field]) && $values[$field] >= 0
            ? $values[$field]
            : throw new InvalidArgumentException($where . ' has no count at "' . $field . '".');
    }

    /**
     * @param stdClass $fields A conversation.
     * @param string   $where  The conversation, for the message.
     *
     * @throws InvalidArgumentException When `origin` is missing or not a boolean.
     *
     * @return bool Its value.
     */
    private static function flag(stdClass $fields, string $where): bool
    {
        $values = get_object_vars($fields);

        return array_key_exists('origin', $values) && is_bool($values['origin'])
            ? $values['origin']
            : throw new InvalidArgumentException($where . ' has no boolean at "origin".');
    }
}
