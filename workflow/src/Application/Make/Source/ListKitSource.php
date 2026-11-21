<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\ListPagePlan;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Exception\WorkflowRefusal;

use function str_replace;

/**
 * The six files `make:list-page` writes (#324), from the templates in
 * `workflow/scaffolds/list-page/`: the page, its query and query reader, its
 * repository, its row and its view.
 */
final readonly class ListKitSource
{
    /**
     * Each file's folder under `src/`, its template and its class's suffix.
     */
    private const array FILES = [
        ['Controller/', 'page', 'ListPage'],
        ['Form/', 'query', 'ListQuery'],
        ['Form/', 'queries', 'ListQueries'],
        ['Repository/', 'repository', 'ListRepository'],
        ['Entity/', 'row', 'ListRow'],
        ['Service/', 'view', 'ListView'],
    ];

    /**
     * The constructors' and methods' `@param` rows, by placeholder; `{Stem}` and `{variable}` are the page's.
     */
    private const array PARAMS = [
        'pageDocParams' => [
            ['PageDocuments', '$pageDocuments', 'Makes the page.'],
            ['{Stem}ListQueries', '${variable}ListQueries', 'Reads the query string.'],
            ['{Stem}ListView', '${variable}ListView', 'Makes its components.'],
        ],
        'pageParams' => [
            ['{Stem}ListQuery', '${variable}ListQuery', 'What the list is asked for.'],
            ['int', '$page', 'The page, from 1.'],
            ['int', '$perPage', 'How many rows a page shows.'],
        ],
        'tableParams' => [
            ['{Stem}ListQuery', '${variable}ListQuery', 'What the list is asked for.'],
            ['list<{Stem}ListRow>', '$rows', "The page's rows, in order."],
        ],
        'viewParams' => [
            ['{Stem}ListRepository', '${variable}ListRepository', 'The rows.'],
            ['ListParts', '$listParts', 'Makes the links and the pager.'],
        ],
    ];

    /**
     * @param MakeTemplates    $makeTemplates    Renders the `*.tpl` files.
     * @param ListKitLines     $listKitLines     The row's, the query's and the reader's lines.
     * @param ListKitViewLines $listKitViewLines The repository's and the view's lines.
     * @param DocParams        $docParams        Pads the constructors' `@param` lines.
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
        private ListKitLines $listKitLines = new ListKitLines(),
        private ListKitViewLines $listKitViewLines = new ListKitViewLines(),
        private DocParams $docParams = new DocParams(),
    ) {
    }

    /**
     * @param string       $folder       The module's `src/`, relative to the project.
     * @param ListPagePlan $listPagePlan The page.
     *
     * @throws WorkflowRefusal When a template is missing or a placeholder unfilled.
     *
     * @return list<MadeFile> The six files.
     */
    public function files(string $folder, ListPagePlan $listPagePlan): array
    {
        $values = $this->values($listPagePlan);
        $made = [];

        foreach (self::FILES as [$under, $kind, $suffix]) {
            $made[] = new MadeFile(
                $folder . $under . $listPagePlan->stem . $suffix . '.php',
                $this->makeTemplates->render('list-page', $kind . '.php.tpl', $values),
            );
        }

        return $made;
    }

    /**
     * @param ListPagePlan $listPagePlan The page.
     *
     * @return array<string, string> Every template's placeholders.
     */
    private function values(ListPagePlan $listPagePlan): array
    {
        return [
            ...$this->listKitLines->values($listPagePlan),
            ...$this->listKitViewLines->values($listPagePlan),
            ...$this->params($listPagePlan),
            ...$listPagePlan->values(),
        ];
    }

    /**
     * @param ListPagePlan $listPagePlan The page.
     *
     * @return array<string, string> The page's, the repository's and the view's aligned `@param` lines.
     */
    private function params(ListPagePlan $listPagePlan): array
    {
        $names = ['{Stem}', '{variable}'];
        $values = [$listPagePlan->stem, $listPagePlan->variable()];
        $params = [];

        foreach (self::PARAMS as $placeholder => $rows) {
            $named = [];

            foreach ($rows as [$type, $variable, $what]) {
                $named[] = [str_replace($names, $values, $type), str_replace($names, $values, $variable), $what];
            }

            $params[$placeholder] = $this->docParams->lines($named);
        }

        return $params;
    }
}
