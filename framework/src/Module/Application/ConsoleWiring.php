<?php

declare(strict_types=1);

namespace AIC\Module\Application;

use AIC\Schedule\Domain\Schedule;
use AIC\Schema\Contracts\Migration;
use InvalidArgumentException;

/**
 * What the console kernel always takes from the generated wiring (#93): the
 * modules' migrations and schedules, and the theme's presentation for the
 * pages a command or job makes (#265). `aic-build` writes the one
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

    /**
     * @throws InvalidArgumentException When the compiled asset metadata is invalid.
     *
     * @return WebPresentation The compiled template, asset and layout catalogs.
     */
    public function presentation(): WebPresentation;
}
