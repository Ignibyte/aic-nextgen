<?php

declare(strict_types=1);

namespace AIC\Mcp\Application;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Database\Contracts\QueryException;
use InvalidArgumentException;
use Override;

use function ctype_digit;
use function implode;

/**
 * `bin/aic mcp:calls [<limit>]` (#332): the capability registry's latest
 * calls, newest first, one a line: when (UTC), the client, the person, the
 * tool, the call id, `dry` for a dry run, and how it ended. 20 unless a limit
 * of 1 to 1000 is given. Never the arguments or the output.
 */
final readonly class McpCallsCommand implements Command
{
    /**
     * @param McpCallLog $mcpCallLog The audit.
     */
    public function __construct(
        private McpCallLog $mcpCallLog,
    ) {
    }

    /**
     * @param CommandInput $commandInput The limit, or nothing.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     * @throws QueryException           When the calls cannot be read.
     *
     * @return CommandResponse The calls, or a line saying there are none.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $limit = self::limit($commandInput);

        if ($limit === 0) {
            return new CommandResult(['Usage: mcp:calls [<limit>], a limit of 1 to 1000.'], 2);
        }

        $lines = [];

        foreach ($this->mcpCallLog->latest($limit) as $row) {
            $lines[] = implode("\t", [
                $row->string('at'),
                $row->string('client'),
                $row->string('user_id'),
                $row->string('tool'),
                $row->string('call_id'),
                $row->bool('dry_run') ? 'dry' : 'run',
                $row->string('outcome'),
            ]);
        }

        return new CommandResult($lines === [] ? ['No calls.'] : $lines, 0);
    }

    /**
     * @param CommandInput $commandInput The limit, or nothing.
     *
     * @return int The limit, 20 when none is given; 0 when it is not 1 to 1000 or more is given.
     */
    private static function limit(CommandInput $commandInput): int
    {
        $limit = $commandInput->argument(0, '20');
        $only = $commandInput->argument(1, '') === '';

        return $only && ctype_digit($limit) && (int) $limit >= 1 && (int) $limit <= 1_000
            ? (int) $limit
            : 0;
    }
}
