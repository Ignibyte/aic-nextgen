<?php

declare(strict_types=1);

namespace AIC\Console\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandWriter;
use AIC\Controller\Domain\CommandResult;

use function array_keys;
use function array_slice;
use function is_string;

/**
 * Runs a command line: the first argument names the command, the rest are its
 * arguments. A missing or unknown name answers with the usage and exit code 2.
 */
final readonly class Console
{
    /**
     * @param array<string, Command> $commands The commands by name.
     */
    public function __construct(
        private array $commands,
    ) {
    }

    /**
     * @param list<string>  $argv          The command line, the script first.
     * @param CommandWriter $commandWriter Writes the output.
     *
     * @return int The exit code.
     */
    public function run(array $argv, CommandWriter $commandWriter): int
    {
        $command = $this->commands[self::name($argv)] ?? null;

        if (!$command instanceof Command) {
            return $this->usage()->writeTo($commandWriter);
        }

        return $command->command(new CommandInput(array_slice($argv, 2)))->writeTo($commandWriter);
    }

    /**
     * @return CommandResult How to call the console, and its commands.
     */
    private function usage(): CommandResult
    {
        $lines = ['Usage: aic <command> [arguments]', '', 'Commands:'];

        foreach (array_keys($this->commands) as $name) {
            $lines[] = '  ' . $name;
        }

        return new CommandResult($lines, 2);
    }

    /**
     * @param list<string> $argv The command line, the script first.
     *
     * @return string The command's name; empty when there is none.
     */
    private static function name(array $argv): string
    {
        $name = $argv[1] ?? null;

        return is_string($name)
            ? $name
            : '';
    }
}
