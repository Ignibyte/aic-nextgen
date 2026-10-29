<?php

declare(strict_types=1);

namespace App\Site\Command;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use AIC\Notification\Application\Inbox;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic notifications:forget <user>` (#210): deletes the user's kept
 * notifications. gate:23's first notifications step runs it, so each pass
 * starts the demo user's inbox afresh.
 */
final readonly class NotificationsForget implements Command
{
    /**
     * @param Inbox $inbox The kept notifications.
     */
    public function __construct(
        private Inbox $inbox,
    ) {
    }

    /**
     * @param CommandInput $commandInput The user, first.
     *
     * @throws QueryException           When the notifications cannot be deleted.
     * @throws InvalidArgumentException When a constructed value is invalid.
     *
     * @return CommandResponse How many were deleted; exit 2 without a user.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $user = $commandInput->argument(0, '');

        if ($user === '') {
            return new CommandResult(['usage: bin/aic notifications:forget <user>'], 2);
        }

        return new CommandResult(['notifications:forget deleted ' . $this->inbox->forget($user) . ' notification(s).'], 0);
    }
}
