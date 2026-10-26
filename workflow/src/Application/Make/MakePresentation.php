<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use JsonException;
use stdClass;

use function get_object_vars;
use function json_decode;
use function preg_match;
use function str_replace;
use function strpos;
use function substr;

use const JSON_THROW_ON_ERROR;

/**
 * Checks, before a make writes a theme component (#153), that Composer finds the component's class:
 * `App\Theme\Components\` maps to the theme's `components/`. Its template is a `.phtml` the build escapes into
 * `generated/templates/` and a lookup requires (#155), so no Composer line names it.
 */
final readonly class MakePresentation
{
    /**
     * @param MakeRecipe   $makeRecipe   The planned make.
     * @param ProjectFiles $projectFiles The project or its scaffold overlay.
     *
     * @throws WorkflowRefusal When a line is missing, naming the line to add.
     */
    public function check(MakeRecipe $makeRecipe, ProjectFiles $projectFiles): void
    {
        foreach ($makeRecipe->files as $madeFile) {
            if (preg_match('#^themes/[^/]+/components/#', $madeFile->path) !== 1) {
                continue;
            }

            $psr4 = $this->psr4($projectFiles);
            $components = substr($madeFile->path, 0, (int) strpos($madeFile->path, '/components/') + 12);
            $this->line($psr4, 'App\\Theme\\Components\\', $components);

            return;
        }
    }

    /**
     * @param stdClass $psr4      composer.json's autoload.psr-4.
     * @param string   $namespace A namespace with its last `\`.
     * @param string   $folder    The folder it must map to.
     *
     * @throws WorkflowRefusal When it maps elsewhere or not at all.
     */
    private function line(stdClass $psr4, string $namespace, string $folder): void
    {
        if ((get_object_vars($psr4)[$namespace] ?? null) !== $folder) {
            throw new WorkflowRefusal(
                'Add "' . str_replace('\\', '\\\\', $namespace) . '": "' . $folder . "\" to composer.json's autoload.psr-4, "
                    . 'then run composer dump-autoload.',
            );
        }
    }

    /**
     * @param ProjectFiles $projectFiles The project files.
     *
     * @throws WorkflowRefusal When composer.json is not readable JSON with an autoload.psr-4 object.
     *
     * @return stdClass Its autoload.psr-4.
     */
    private function psr4(ProjectFiles $projectFiles): stdClass
    {
        try {
            $composer = [json_decode($projectFiles->read('composer.json') ?? '', false, 512, JSON_THROW_ON_ERROR)];
        } catch (JsonException $jsonException) {
            throw new WorkflowRefusal('composer.json must be readable JSON before making a component.', 0, $jsonException);
        }

        $autoload = [$composer[0] instanceof stdClass
            ? get_object_vars($composer[0])['autoload'] ?? null
            : null];
        $psr4 = [$autoload[0] instanceof stdClass
            ? get_object_vars($autoload[0])['psr-4'] ?? null
            : null];

        if (!$psr4[0] instanceof stdClass) {
            throw new WorkflowRefusal("composer.json has no autoload.psr-4 to map the theme's components.");
        }

        return $psr4[0];
    }
}
