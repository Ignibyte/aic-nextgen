<?php

declare(strict_types=1);

namespace AIC\Access\Application;

use AIC\Access\Domain\RoleChange;
use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

use function preg_match;

/**
 * `bin/aic role:grant|role:revoke <role> <permission>` and
 * `bin/aic role:assign|role:unassign <user> <role>` (#304): the roles are
 * the operator's data. A role is lower-case words joined by `-`; exit 0 when
 * the row changed, 1 when it was already so, 2 for a line it does not take.
 */
final readonly class RoleCommand implements Command
{
    /**
     * A role's name.
     */
    private const string ROLE = '/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/';

    /**
     * @param RoleChange    $roleChange    Which of the four it is.
     * @param PostgresRoles $postgresRoles The roles.
     */
    public function __construct(
        private RoleChange $roleChange,
        private PostgresRoles $postgresRoles,
    ) {
    }

    /**
     * @param CommandInput $commandInput The two words.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     * @throws QueryException           When the change cannot be written.
     *
     * @return CommandResponse What changed.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $first = $commandInput->argument(0, '');
        $second = $commandInput->argument(1, '');

        if (!$this->takes($first, $second, $commandInput->argument(2, ''))) {
            return new CommandResult([$this->roleChange->usage() . '; a role is lower-case words joined by -.'], 2);
        }

        $changed = $this->postgresRoles->change($this->roleChange, $first, $second);

        return new CommandResult(
            ['role:' . $this->roleChange->value . ' ' . $first . ' ' . $second . ($changed ? ': done.' : ': already so.')],
            $changed ? 0 : 1,
        );
    }

    /**
     * @param string $first  The first word.
     * @param string $second The second word.
     * @param string $third  A third word, which none takes.
     *
     * @return bool Whether the line is two words and its role is a role's name.
     */
    private function takes(string $first, string $second, string $third): bool
    {
        $role = $this->roleChange === RoleChange::Grant || $this->roleChange === RoleChange::Revoke
            ? $first
            : $second;

        return $first !== '' && $second !== '' && $third === '' && preg_match(self::ROLE, $role) === 1;
    }
}
