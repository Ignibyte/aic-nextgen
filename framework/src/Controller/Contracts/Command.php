<?php

declare(strict_types=1);

namespace AIC\Controller\Contracts;

/**
 * The Command kind: an entry point run from the command line, whose answer is
 * lines of output and an exit code.
 *
 * Every entry point is exactly one kind, and each kind returns only its own
 * sealed response type; the phpat guards in gates/architecture hold both.
 */
interface Command
{
    /**
     * @param CommandInput $commandInput The command's arguments.
     *
     * @return CommandResponse The answer.
     */
    public function command(CommandInput $commandInput): CommandResponse;
}
