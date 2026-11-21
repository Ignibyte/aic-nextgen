<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Domain\Make\ListPagePlan;

use function implode;
use function in_array;
use function ucfirst;

/**
 * The lines of a list page's repository and view that its columns and
 * filters decide (#324): each filter's condition, the entity type's fields
 * and the row's reads, the table's head, alignments and cells, and the
 * filter bar's fields.
 */
final readonly class ListKitViewLines
{
    /**
     * @param ListKitLines $listKitLines Writes a list of names.
     */
    public function __construct(
        private ListKitLines $listKitLines = new ListKitLines(),
    ) {
    }

    /**
     * @param ListPagePlan $listPagePlan The page.
     *
     * @return array<string, string> The placeholders of the repository and the view.
     */
    public function values(ListPagePlan $listPagePlan): array
    {
        $names = [];
        $heads = [];

        foreach ($listPagePlan->columns as $entityField) {
            $names[] = $entityField->name;
            $heads[] = ucfirst($entityField->words());
        }

        return [
            ...$this->columns($listPagePlan),
            'columns' => $this->listKitLines->listed($names, '        '),
            'fieldLines' => $this->filters($listPagePlan),
            'head' => $this->listKitLines->listed($heads, '        '),
            'matching' => $this->matching($listPagePlan),
        ];
    }

    /**
     * @param ListPagePlan $listPagePlan The page.
     *
     * @return array<string, string> The entity type's fields, the row's reads, the alignments and the cells.
     */
    private function columns(ListPagePlan $listPagePlan): array
    {
        $fields = '';
        $reads = '';
        $alignments = '';
        $cells = '';

        foreach ($listPagePlan->columns as $entityField) {
            $fields .= $entityField->name === $listPagePlan->key
                ? ''
                : '            ' . $entityField->field() . ",\n";
            $reads .= '            ' . $entityField->read() . ",\n";
            $alignments .= in_array($entityField->type, ['integer', 'numeric'], true)
                ? "        TableAlign::End,\n"
                : "        TableAlign::Start,\n";
            $cells .= '            new TableCell(' . self::cell($listPagePlan, $entityField) . "),\n";
        }

        return ['alignments' => $alignments, 'cells' => $cells, 'fields' => $fields, 'rowReads' => $reads];
    }

    /**
     * @param ListPagePlan $listPagePlan The page.
     *
     * @return string The filter bar's field for each filter.
     */
    private function filters(ListPagePlan $listPagePlan): string
    {
        $query = '$' . $listPagePlan->variable() . 'ListQuery->';
        $lines = '';

        foreach ($listPagePlan->filters as $entityField) {
            $label = "'" . $entityField->name . "', '" . ucfirst($entityField->words()) . "', " . $query . $entityField->property();
            $lines .= $this->listKitLines->searched($entityField)
                ? '            new FilterField(' . $label . "),\n"
                : '            $this->listParts->choice(' . $label . "),\n";
        }

        return $lines;
    }

    /**
     * @param ListPagePlan $listPagePlan The page.
     *
     * @return string Each filter's condition, chained on the query.
     */
    private function matching(ListPagePlan $listPagePlan): string
    {
        $query = '$' . $listPagePlan->variable() . 'ListQuery->';
        $lines = [];

        foreach ($listPagePlan->filters as $entityField) {
            $named = "'" . $entityField->name . "'";
            $value = $query . $entityField->property();
            $lines[] = match (true) {
                $this->listKitLines->searched($entityField) => '            ->search([' . $named . '], ' . $value . ')',
                $entityField->isBoolean() => '            ->whereAny($this->listRows->flags(' . $named . ', ' . $value . '))',
                default => '            ->whereIn(' . $named . ', ' . $value . '->matches())',
            };
        }

        return implode("\n", $lines);
    }

    /**
     * @param ListPagePlan $listPagePlan The page.
     * @param EntityField  $entityField  A column.
     *
     * @return string Its cell's text.
     */
    private static function cell(ListPagePlan $listPagePlan, EntityField $entityField): string
    {
        $value = '$' . $listPagePlan->variable() . 'ListRow->' . $entityField->property();

        return match ($entityField->phpType()) {
            'int' => '(string) ' . $value,
            'bool' => $value . " ? 'yes' : 'no'",
            default => $value,
        };
    }
}
