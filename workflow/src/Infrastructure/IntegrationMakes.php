<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\Make\MakeClientCommand;
use AIC\Workflow\Application\Make\MakeMcpToolCommand;
use AIC\Workflow\Application\Make\MakeRun;

/**
 * The makes for what an app exchanges with other systems: `make:client`, a
 * client of an upstream API (#305), and `make:mcp-tool`, a tool an agent calls
 * over MCP (#316).
 */
final readonly class IntegrationMakes
{
    /**
     * @param MakeRun $makeRun The run every make shares.
     *
     * @return array<string, Command> Each make, by its command name.
     */
    public function commands(MakeRun $makeRun): array
    {
        return [
            'make:client' => new MakeClientCommand($makeRun),
            'make:mcp-tool' => new MakeMcpToolCommand($makeRun),
        ];
    }
}
