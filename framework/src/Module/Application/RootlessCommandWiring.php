<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Controller\Contracts\Command;

/**
 * The modules' console commands when none of them reads a root (#93), for
 * the console kernel; the generated wiring implements it instead of
 * `CommandWiring`, so no context goes unread.
 */
interface RootlessCommandWiring
{
    /**
     * @return array<string, Command> The modules' commands by name.
     */
    public function commands(): array;
}
