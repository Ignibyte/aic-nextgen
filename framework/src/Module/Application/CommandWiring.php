<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Controller\Contracts\Command;

/**
 * The modules' console commands (#93), for the console kernel; the generated
 * wiring implements it when some module declares a command.
 */
interface CommandWiring
{
    /**
     * @param ConsoleContext $consoleContext The console's roots.
     *
     * @return array<string, Command> The modules' commands by name.
     */
    public function commands(ConsoleContext $consoleContext): array;
}
