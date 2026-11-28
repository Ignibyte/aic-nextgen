<?php

declare(strict_types=1);

namespace App\Chat\Service;

use function preg_match;

/**
 * The chat socket's names (#32): which rooms there may be, and how a room
 * names who spoke.
 */
final readonly class ChatNames
{
    /**
     * @param string $room A room's name.
     *
     * @return bool Whether it is 1 to 40 lower-case letters and digits.
     */
    public function room(string $room): bool
    {
        return preg_match('/^[a-z0-9]{1,40}$/', $room) === 1;
    }

    /**
     * @param string $user A user's id; empty for a visitor.
     *
     * @return string How the room names them.
     */
    public function speaker(string $user): string
    {
        return $user === ''
            ? 'guest'
            : $user;
    }
}
