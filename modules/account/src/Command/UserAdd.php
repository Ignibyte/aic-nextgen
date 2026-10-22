<?php

declare(strict_types=1);

namespace App\Account\Command;

use AIC\Auth\Contracts\PasswordHasher;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use App\Account\Repository\UserRepository;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic user:add <login> <password>`: creates a user, or resets an existing
 * one's password (#9). Exit 2 without both arguments.
 */
final readonly class UserAdd implements Command
{
    /**
     * @param UserRepository $userRepository The users.
     * @param PasswordHasher $passwordHasher Hashes the password.
     */
    public function __construct(
        private UserRepository $userRepository,
        private PasswordHasher $passwordHasher,
    ) {
    }

    /**
     * @param CommandInput $commandInput The login, then the password.
     *
     * @throws QueryException           When the user cannot be stored.
     * @throws InvalidArgumentException When a constructed value or argument is invalid.
     *
     * @return CommandResponse What was done, or the usage.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $login = $commandInput->argument(0, '');
        $password = $commandInput->argument(1, '');

        if ($login === '' || $password === '') {
            return new CommandResult(['usage: bin/aic user:add <login> <password>'], 2);
        }

        $this->userRepository->save($login, $this->passwordHasher->hash($password));

        return new CommandResult(['user ' . $login . ' saved.'], 0);
    }
}
