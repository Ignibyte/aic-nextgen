<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use JsonException;
use stdClass;

use function array_any;
use function array_key_exists;
use function basename;
use function get_object_vars;
use function json_decode;
use function str_starts_with;
use function strtolower;

use const JSON_THROW_ON_ERROR;

/** Check declared rendering prerequisites before a make writes any authored file. */
final readonly class MakePresentation
{
    /**
     * @param MakeRecipe            $makeRecipe   The planned make.
     * @param ProjectFiles          $projectFiles The actual project or its scaffold overlay.
     * @param array<string, string> $declared     Actual manifest claims, independent of build freshness.
     *
     * @throws WorkflowRefusal When compiled autoload setup or a reused declaration is absent.
     */
    public function check(MakeRecipe $makeRecipe, ProjectFiles $projectFiles, array $declared): void
    {
        $presentation = array_any($makeRecipe->claims, static fn (string $claim): bool => str_starts_with($claim, 'template '));

        if ($presentation || $makeRecipe->component !== '') {
            $this->autoload($projectFiles);
        }

        if ($makeRecipe->component === '') {
            return;
        }

        $class = (new MakeNames())->module($makeRecipe->module) . '\\Component\\' . basename($makeRecipe->component, '.php');

        if (!array_key_exists(strtolower('template ' . $class), $declared)) {
            throw new WorkflowRefusal($class . ' has no explicit ModuleTemplate declaration; declare it before reusing it.');
        }
    }

    /**
     * @param ProjectFiles $projectFiles The project files.
     *
     * @throws WorkflowRefusal When Composer cannot resolve the exact lowercase compiled-template directory.
     */
    private function autoload(ProjectFiles $projectFiles): void
    {
        try {
            $composer = [json_decode($projectFiles->read('composer.json') ?? '', false, 512, JSON_THROW_ON_ERROR)];
        } catch (JsonException $jsonException) {
            throw new WorkflowRefusal('composer.json must be readable JSON before generating declared templates.', 0, $jsonException);
        }

        $autoload = [$composer[0] instanceof stdClass
            ? get_object_vars($composer[0])['autoload'] ?? null
            : null];
        $psr4 = [$autoload[0] instanceof stdClass
            ? get_object_vars($autoload[0])['psr-4'] ?? null
            : null];

        if (
            !$psr4[0] instanceof stdClass
            || (get_object_vars($psr4[0])['App\\Generated\\Templates\\'] ?? null) !== 'generated/templates/'
        ) {
            throw new WorkflowRefusal('Add "App\\\\Generated\\\\Templates\\\\": "generated/templates/" to autoload.psr-4, '
                . 'then run composer dump-autoload before generating declared templates.');
        }
    }
}
