<?php

declare(strict_types=1);

namespace AIC\Controller\Domain;

use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Contracts\CommandWriter;
use InvalidArgumentException;
use Override;

/**
 * A command's answer: its lines of output and its exit code.
 */
final readonly class CommandResult implements CommandResponse
{
    /**
     * @param list<string> $lines    The output, one line each.
     * @param int          $exitCode The exit code, 0 for success.
     *
     * @throws InvalidArgumentException When the exit code is outside 0 to 255.
     */
    public function __construct(
        private array $lines,
        private int $exitCode,
    ) {
        if ($exitCode < 0 || $exitCode > 255) {
            throw new InvalidArgumentException('CommandResult: an exit code is 0 to 255.');
        }
    }

    /**
     * @param CommandWriter $commandWriter Writes the output.
     *
     * @return int The exit code.
     */
    #[Override]
    public function writeTo(CommandWriter $commandWriter): int
    {
        foreach ($this->lines as $line) {
            $commandWriter->line($line);
        }

        return $this->exitCode;
    }
}
