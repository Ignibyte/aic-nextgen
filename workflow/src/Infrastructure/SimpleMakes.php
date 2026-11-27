<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\Make\MakeApiCommand;
use AIC\Workflow\Application\Make\MakeBlockCommand;
use AIC\Workflow\Application\Make\MakeCommandCommand;
use AIC\Workflow\Application\Make\MakeDetailPageCommand;
use AIC\Workflow\Application\Make\MakeListPageCommand;
use AIC\Workflow\Application\Make\MakePageActionCommand;
use AIC\Workflow\Application\Make\MakeRun;
use AIC\Workflow\Application\Make\MakeSeedCommand;
use AIC\Workflow\Application\Make\MakeShapeCommand;
use AIC\Workflow\Application\Make\MakeWebhookCommand;
use AIC\Workflow\Domain\Make\MadeShape;

/**
 * The makes that need nothing but the run (#122, #319), composed apart from
 * `MakeKernel` for PHPMD's coupling cap: one entry class each (`make:api`,
 * `make:block`, `make:command`, `make:page-action`, #322,
 * `make:seed`, #320, `make:webhook`, #321) and the
 * plain PHP shapes (`make:enum`, `make:exception`, `make:interface`).
 */
final readonly class SimpleMakes
{
    /**
     * @param MakeRun $makeRun The run every make goes through.
     *
     * @return array<string, Command> Each make, by its command name.
     */
    public function commands(MakeRun $makeRun): array
    {
        return [
            'make:api' => new MakeApiCommand($makeRun),
            'make:block' => new MakeBlockCommand($makeRun),
            'make:command' => new MakeCommandCommand($makeRun),
            'make:detail-page' => new MakeDetailPageCommand($makeRun),
            'make:enum' => new MakeShapeCommand($makeRun, MadeShape::Enum),
            'make:exception' => new MakeShapeCommand($makeRun, MadeShape::Exception),
            'make:interface' => new MakeShapeCommand($makeRun, MadeShape::Interface),
            'make:list-page' => new MakeListPageCommand($makeRun),
            'make:page-action' => new MakePageActionCommand($makeRun),
            'make:seed' => new MakeSeedCommand($makeRun),
            'make:webhook' => new MakeWebhookCommand($makeRun),
        ];
    }
}
