<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeNames;
use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Exception\WorkflowRefusal;

use function lcfirst;

/**
 * Renders what `make:page-action` writes (#322): the POST page in
 * `src/Controller/`, and its step and its region's view in `src/Service/`.
 */
final readonly class PageActionSource
{
    /**
     * Each file's folder under `src/`, its template and its class's suffix.
     */
    private const array FILES = [['Controller/', 'action', 'Action'], ['Service/', 'step', 'Step'], ['Service/', 'view', 'View']];

    /**
     * @param MakeTemplates $makeTemplates Renders the action's `*.tpl` files.
     * @param MakeNames     $makeNames     Words and kebab case for the form.
     * @param DocParams     $docParams     Pads the action's `@param` lines.
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
        private MakeNames $makeNames = new MakeNames(),
        private DocParams $docParams = new DocParams(),
    ) {
    }

    /**
     * @param string $folder    The module's `src/`, relative to the project.
     * @param string $namespace The module's namespace, without the last `\`.
     * @param string $stem      The action's name without `Action` (`ScaffoldPin`).
     * @param string $page      The page's path, where a plain browser goes back to.
     * @param string $path      The action's path.
     * @param string $region    The region its answer fills.
     *
     * @throws WorkflowRefusal When a template is missing or a placeholder unfilled.
     *
     * @return list<MadeFile> The action, its step and its view.
     */
    public function files(
        string $folder,
        string $namespace,
        string $stem,
        string $page,
        string $path,
        string $region,
    ): array {
        $values = [...$this->values($stem), 'namespace' => $namespace, 'on' => $page, 'path' => $path, 'region' => $region];
        $made = [];

        foreach (self::FILES as [$under, $kind, $suffix]) {
            $made[] = new MadeFile(
                $folder . $under . $stem . $suffix . '.php',
                $this->makeTemplates->render('page-action', $kind . '.php.tpl', ['class' => $stem . $suffix, ...$values]),
            );
        }

        return $made;
    }

    /**
     * @param string $stem The action's name without `Action`.
     *
     * @return array<string, string> The placeholders its name decides.
     */
    private function values(string $stem): array
    {
        return [
            'id' => $this->makeNames->kebab($stem),
            'params' => $this->params($stem),
            'step' => $stem . 'Step',
            'stepVariable' => lcfirst($stem) . 'Step',
            'title' => $this->makeNames->words($stem),
            'view' => $stem . 'View',
            'viewVariable' => lcfirst($stem) . 'View',
        ];
    }

    /**
     * @param string $stem The action's name without `Action`.
     *
     * @return string The action's constructor `@param` lines, aligned as Pint aligns them.
     */
    private function params(string $stem): string
    {
        $rows = [
            [$stem . 'Step', '$' . lcfirst($stem) . 'Step', 'Runs the action.'],
            [$stem . 'View', '$' . lcfirst($stem) . 'View', "The region's form."],
            ['TesseraRequests', '$tesseraRequests', 'Reads whether Tessera sent the request.'],
        ];
        return $this->docParams->lines($rows);
    }
}
