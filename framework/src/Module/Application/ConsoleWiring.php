<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Schedule\Domain\Schedule;
use AIC\Schema\Contracts\Migration;

/**
 * What the console kernel always takes from the generated wiring (#93): the
 * modules' migrations and schedules. `bin/aic-build` writes the one
 * implementation, `App\Generated\ConsoleModules`, which also implements
 * `PluginWiring`, `CommandWiring` and `JobWiring` when some module has
 * plugins, commands or jobs.
 */
interface ConsoleWiring
{
    /**
     * @return list<Migration> The modules' migrations, in build order.
     */
    public function migrations(): array;

    /**
     * @return list<Schedule> The modules' schedules.
     */
    public function schedules(): array;
}
