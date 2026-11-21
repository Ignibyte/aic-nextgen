<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\Make\MakeEntityCommand;
use AIC\Workflow\Application\Make\MakeExportCommand;
use AIC\Workflow\Application\Make\MakeMigrationCommand;
use AIC\Workflow\Application\Make\MakeRelationCommand;
use AIC\Workflow\Application\Make\MakeRun;
use AIC\Workflow\Application\Make\MakeStateCommand;
use AIC\Workflow\Application\Make\MakeVersions;
use AIC\Workflow\Application\UtcTime;

/**
 * The makes that write a migration (#122, #123, #306, #308, #323), composed apart from
 * `MakeParts` for PHPMD's coupling cap: each versions its migration by now in
 * UTC unless `--version` names one.
 */
final readonly class VersionedMakes
{
    /**
     * @param MakeRun $makeRun The run every make goes through.
     *
     * @return array<string, Command> `make:entity`, `make:export` (#323), `make:migration`, `make:relation` and `make:state`.
     */
    public function commands(MakeRun $makeRun): array
    {
        $makeVersions = new MakeVersions(new UtcTime(new SystemClock()));

        return [
            'make:entity' => new MakeEntityCommand($makeRun, $makeVersions),
            'make:export' => new MakeExportCommand($makeRun, $makeVersions),
            'make:migration' => new MakeMigrationCommand($makeRun, $makeVersions),
            'make:relation' => new MakeRelationCommand($makeRun, $makeVersions),
            'make:state' => new MakeStateCommand($makeRun, $makeVersions),
        ];
    }
}
