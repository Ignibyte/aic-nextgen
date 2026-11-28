<?php

declare(strict_types=1);

namespace App\Chat\Controller;

use AIC\Broadcast\Application\Broadcaster;
use AIC\Broadcast\Domain\Channel;
use AIC\Controller\Contracts\Socket;
use AIC\Controller\Contracts\SocketFrame;
use AIC\Controller\Contracts\SocketResponse;
use AIC\Controller\Domain\SocketClose;
use AIC\Controller\Domain\SocketReply;
use AIC\Database\Contracts\QueryException;
use App\Chat\Service\ChatNames;
use InvalidArgumentException;
use Override;

use function explode;
use function mb_strlen;
use function trim;

/**
 * `/ws/chat` (#32): rooms over one socket. `join <room>` moves the connection
 * to the room's channel, `chat.<room>`, and answers `joined <room>`; `say
 * <room> <text>` publishes `<who>: <text>` to the room, which every connection
 * in it hears, the sender's too; `who` answers the socket's path, the
 * connection's id and who holds it; `bye` closes the connection. Anything
 * else is answered `unknown`.
 */
final readonly class ChatSocket implements Socket
{
    /**
     * @param Broadcaster $broadcaster Publishes to a room.
     * @param ChatNames   $chatNames   The rooms and speakers' names.
     */
    public function __construct(
        private Broadcaster $broadcaster,
        private ChatNames $chatNames = new ChatNames(),
    ) {
    }

    /**
     * @param SocketFrame $socketFrame The message.
     *
     * @throws QueryException           When the broadcast cannot be written.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return SocketReply|SocketClose The answer.
     */
    #[Override]
    public function socket(SocketFrame $socketFrame): SocketResponse
    {
        $words = explode(' ', trim($socketFrame->text), 3);

        return match ($words[0]) {
            'bye' => new SocketClose(1_000, 'bye'),
            'who' => new SocketReply([
                $socketFrame->path . ' ' . $socketFrame->connection . ' ' . $this->chatNames->speaker($socketFrame->user),
            ]),
            'join' => $this->join($words[1] ?? ''),
            'say' => $this->say($words[1] ?? '', $words[2] ?? '', $socketFrame->user),
            default => new SocketReply(['unknown']),
        };
    }

    /**
     * @param string $room The room asked for.
     *
     * @throws InvalidArgumentException When the room is no channel.
     *
     * @return SocketReply `joined <room>`, hearing the room from now on; `unknown` for a room that is not 1 to 40
     *                     lower-case letters and digits.
     */
    private function join(string $room): SocketReply
    {
        return $this->chatNames->room($room)
            ? new SocketReply(['joined ' . $room], [new Channel('chat.' . $room)])
            : new SocketReply(['unknown']);
    }

    /**
     * @param string $room The room.
     * @param string $text What to say, 1 to 200 characters.
     * @param string $user Who says it; empty for a visitor.
     *
     * @throws QueryException           When the broadcast cannot be written.
     * @throws InvalidArgumentException When the room is no channel.
     *
     * @return SocketReply Nothing, once it is published; `unknown` for a bad room or text.
     */
    private function say(string $room, string $text, string $user): SocketReply
    {
        if (!$this->chatNames->room($room) || $text === '' || mb_strlen($text) > 200) {
            return new SocketReply(['unknown']);
        }

        $this->broadcaster->publish(new Channel('chat.' . $room), $this->chatNames->speaker($user) . ': ' . $text);

        return new SocketReply();
    }
}
