<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Domain\Make\ListPagePlan;

use function array_any;
use function implode;
use function ksort;

/**
 * The lines of a list page's row, query and query reader that its columns
 * and filters decide (#324): the row's properties, each filter's property
 * and read, and the sortable columns. A text filter searches; a choice or a
 * boolean one is a `ListChoice`, a boolean's values `yes` and `no`.
 */
final readonly class ListKitLines
{
    /**
     * The framework's component values, where `ListChoice` is; a name, never a class (#122).
     */
    private const string DOMAIN = 'AIC\\Component\\Domain';

    /**
     * @param DocParams $docParams Pads the `@param` lines.
     */
    public function __construct(
        private DocParams $docParams = new DocParams(),
    ) {
    }

    /**
     * @param ListPagePlan $listPagePlan The page.
     *
     * @return array<string, string> The placeholders of the row, the query and the reader.
     */
    public function values(ListPagePlan $listPagePlan): array
    {
        $rowParams = [];
        $rowProps = '';

        foreach ($listPagePlan->columns as $entityField) {
            $rowParams[] = [$entityField->phpType(), '$' . $entityField->property(), 'The ' . $entityField->words() . '.'];
            $rowProps .= '        public ' . $entityField->phpType() . ' $' . $entityField->property() . ",\n";
        }

        return [
            ...$this->query($listPagePlan),
            'reads' => $this->reads($listPagePlan),
            'rowParams' => $this->docParams->lines($rowParams),
            'rowProps' => $rowProps,
            'sorts' => $this->listed($listPagePlan->sorts, '        '),
        ];
    }

    /**
     * @param list<string> $names  Names.
     * @param string       $indent Each line's indent.
     *
     * @return string Each quoted on its own line, as an array's items.
     */
    public function listed(array $names, string $indent): string
    {
        $lines = '';

        foreach ($names as $name) {
            $lines .= $indent . "'" . $name . "',\n";
        }

        return $lines;
    }

    /**
     * @param EntityField $entityField A filter.
     *
     * @return bool Whether it searches text, rather than taking one of its values.
     */
    public function searched(EntityField $entityField): bool
    {
        return $entityField->type === 'text';
    }

    /**
     * @param EntityField $entityField A choice or boolean filter.
     *
     * @return list<string> The values it takes.
     */
    public function choices(EntityField $entityField): array
    {
        return $entityField->isBoolean()
            ? ['yes', 'no']
            : $entityField->choices;
    }

    /**
     * @param ListPagePlan $listPagePlan The page.
     *
     * @return array<string, string> The query's imports, `@param` lines, properties and filters.
     */
    private function query(ListPagePlan $listPagePlan): array
    {
        $params = [['ListPlace', '$listPlace', 'Its sort and page.']];
        $props = '';
        $filters = [];

        foreach ($listPagePlan->filters as $entityField) {
            $param = $this->param($entityField);
            $params[] = $param;
            $props .= '        public ' . $param[0] . ' ' . $param[1] . ",\n";
            $filters[$entityField->name] = "            '" . $entityField->name . "' => \$this->" . $entityField->property()
                . ($this->searched($entityField) ? '' : '->value') . ",\n";
        }

        ksort($filters);

        $choosing = array_any($listPagePlan->filters, fn (EntityField $entityField): bool => !$this->searched($entityField));

        return [
            'queryFilters' => implode('', $filters),
            'queryParams' => $this->docParams->lines($params),
            'queryProps' => $props,
            'queryUses' => $choosing ? 'use ' . self::DOMAIN . "\\ListChoice;\n" : '',
        ];
    }

    /**
     * @param ListPagePlan $listPagePlan The page.
     *
     * @return string The reader's line for each filter.
     */
    private function reads(ListPagePlan $listPagePlan): string
    {
        $reads = '';

        foreach ($listPagePlan->filters as $entityField) {
            $reads .= $this->searched($entityField)
                ? "            \$this->listQueries->text(\$serverRequest, '" . $entityField->name . "'),\n"
                : "            \$this->listQueries->choice(\$serverRequest, '" . $entityField->name . "', ['"
                    . implode("', '", $this->choices($entityField)) . "']),\n";
        }

        return $reads;
    }

    /**
     * @param EntityField $entityField A filter.
     *
     * @return array{string, string, string} Its property's type, variable and description.
     */
    private function param(EntityField $entityField): array
    {
        return $this->searched($entityField)
            ? ['string', '$' . $entityField->property(), 'The text the ' . $entityField->words() . ' holds; empty for any.']
            : ['ListChoice', '$' . $entityField->property(), 'The ' . $entityField->words() . '.'];
    }
}
