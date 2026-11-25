<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\DetailPagePlan;
use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_keys;
use function ksort;
use function str_replace;
use function strcmp;
use function ucfirst;
use function usort;

/**
 * The three files `make:detail-page` writes (#341), from the templates in
 * `workflow/scaffolds/detail-page/`: the page, its repository and its view.
 * The repository and the view hold the fields and the tabs as constants;
 * the framework's `DetailRows` and `DetailParts` read and draw them.
 */
final readonly class DetailKitSource
{
    /**
     * Each file's folder under `src/`, its template and its class's suffix.
     */
    private const array FILES = [
        ['Controller/', 'page', 'DetailPage'],
        ['Repository/', 'repository', 'DetailRepository'],
        ['Service/', 'view', 'DetailView'],
    ];

    /**
     * @param MakeTemplates $makeTemplates Renders the `*.tpl` files.
     * @param DocParams     $docParams     Pads the page's `@param` lines.
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
        private DocParams $docParams = new DocParams(),
    ) {
    }

    /**
     * @param string         $folder         The module's `src/`, relative to the project.
     * @param DetailPagePlan $detailPagePlan The page.
     *
     * @throws WorkflowRefusal When a template is missing or a placeholder unfilled.
     *
     * @return list<MadeFile> The three files.
     */
    public function files(string $folder, DetailPagePlan $detailPagePlan): array
    {
        $values = [...$this->values($detailPagePlan), ...$detailPagePlan->values()];
        $made = [];

        foreach (self::FILES as [$under, $kind, $suffix]) {
            $made[] = new MadeFile(
                $folder . $under . $detailPagePlan->stem . $suffix . '.php',
                $this->makeTemplates->render('detail-page', $kind . '.php.tpl', $values),
            );
        }

        return $made;
    }

    /**
     * @param DetailPagePlan $detailPagePlan The page.
     *
     * @return array<string, string> The page's `@param` lines, and the repository's and the view's constants.
     */
    private function values(DetailPagePlan $detailPagePlan): array
    {
        $stem = $detailPagePlan->stem;
        $variable = '$' . $detailPagePlan->variable();

        return [
            ...self::constants($detailPagePlan),
            'pageDocParams' => $this->docParams->lines([
                ['PageDocuments', '$pageDocuments', 'Makes the page.'],
                ['DetailParts', '$detailParts', 'Reads the open tab.'],
                [$stem . 'DetailView', $variable . 'DetailView', 'Makes its components.'],
            ]),
            'viewDocParams' => $this->docParams->lines([
                [$stem . 'DetailRepository', $variable . 'DetailRepository', 'Finds the row.'],
                ['DetailParts', '$detailParts', 'Makes the heading, the panel and the tabs.'],
            ]),
        ];
    }

    /**
     * @param DetailPagePlan $detailPagePlan The page.
     *
     * @return array<string, string> The repository's `FIELDS` and the view's `TERMS`, `TABS` and `PANELS` entries.
     */
    private static function constants(DetailPagePlan $detailPagePlan): array
    {
        $fields = '';
        $terms = '';
        $tabs = '';
        $panels = '';
        $sorted = $detailPagePlan->fields;
        usort($sorted, static fn (EntityField $first, EntityField $second): int => strcmp($first->name, $second->name));

        foreach ($sorted as $entityField) {
            $fields .= "        '" . $entityField->name . "' => FieldType::" . $detailPagePlan->fieldType($entityField) . ",\n";
            $terms .= "        '" . $entityField->name . "' => '" . ucfirst($entityField->words()) . "',\n";
        }

        foreach (array_keys($detailPagePlan->tabs) as $tab) {
            $tabs .= "        ['" . $tab . "', '" . ucfirst(str_replace('_', ' ', $tab)) . "'],\n";
        }

        $panelled = $detailPagePlan->tabs;
        ksort($panelled);

        foreach ($panelled as $tab => $named) {
            $panels .= self::panel($tab, $named);
        }

        return ['fields' => $fields, 'panels' => $panels, 'tabs' => $tabs, 'terms' => $terms];
    }

    /**
     * @param string       $tab    A tab's name.
     * @param list<string> $fields Its fields, in order.
     *
     * @return string Its entry in the view's `PANELS`, a field a line.
     */
    private static function panel(string $tab, array $fields): string
    {
        $lines = "        '" . $tab . "' => [\n";

        foreach ($fields as $field) {
            $lines .= "            '" . $field . "',\n";
        }

        return $lines . "        ],\n";
    }
}
