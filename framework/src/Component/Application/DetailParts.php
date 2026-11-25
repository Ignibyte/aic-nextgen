<?php

declare(strict_types=1);

namespace AIC\Component\Application;

use AIC\Component\Domain\DescriptionItem;
use AIC\Component\Domain\DescriptionList;
use AIC\Component\Domain\Tab;
use AIC\Component\Domain\Tabs;
use AIC\Database\Contracts\QueryException;
use AIC\Database\Contracts\Row;
use AIC\Entity\Domain\FieldType;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function is_string;
use function rawurlencode;

/**
 * The parts of a row's detail page (#341): the tab its query string opens,
 * the tabs, which refresh one anchored part in place, the open tab's panel
 * of terms and values, and one field's value as text.
 */
final readonly class DetailParts
{
    /**
     * @param ServerRequestInterface                $serverRequest The request, with `tab=<name>`.
     * @param non-empty-list<array{string, string}> $tabs          Each tab's name and label, in order.
     *
     * @return string The tab asked for; the first when it names none of them.
     */
    public function tab(ServerRequestInterface $serverRequest, array $tabs): string
    {
        $query = $serverRequest->getQueryParams();
        $asked = array_key_exists('tab', $query) && is_string($query['tab'])
            ? $query['tab']
            : '';

        foreach ($tabs as [$name]) {
            if ($name === $asked) {
                return $name;
            }
        }

        return $tabs[0][0];
    }

    /**
     * @param string                      $path   The row's page.
     * @param string                      $tab    The open tab.
     * @param list<array{string, string}> $tabs   Each tab's name and label, in order.
     * @param string                      $anchor The anchor of the part holding the tabs and the panel.
     *
     * @return Tabs A link per tab to `?tab=<name>`, the open one current, each refreshing `@<anchor>`.
     */
    public function tabs(string $path, string $tab, array $tabs, string $anchor): Tabs
    {
        $links = [];

        foreach ($tabs as [$name, $label]) {
            $links[] = new Tab($label, $path . '?tab=' . rawurlencode($name), $name === $tab);
        }

        return new Tabs($links, '@' . $anchor, 'Sections');
    }

    /**
     * @param Row                      $row    The row.
     * @param array<string, FieldType> $types  Each field's type, by name.
     * @param array<string, string>    $terms  Each field's term, by name.
     * @param list<string>             $fields The open tab's fields, in order.
     *
     * @throws QueryException When a field is missing from the row or of another type.
     *
     * @return DescriptionList Each field's term and value.
     */
    public function panel(Row $row, array $types, array $terms, array $fields): DescriptionList
    {
        $items = [];

        foreach ($fields as $field) {
            $items[] = new DescriptionItem(
                $terms[$field] ?? $field,
                $this->value($row, $field, $types[$field] ?? FieldType::Text),
            );
        }

        return new DescriptionList($items);
    }

    /**
     * @param Row       $row       The row.
     * @param string    $field     A field.
     * @param FieldType $fieldType Its type.
     *
     * @throws QueryException When the field is missing from the row or of another type.
     *
     * @return string Its value as text: a boolean is `yes` or `no`, a null is empty.
     */
    public function value(Row $row, string $field, FieldType $fieldType): string
    {
        if ($fieldType === FieldType::Boolean) {
            return $row->bool($field)
                ? 'yes'
                : 'no';
        }

        return $fieldType === FieldType::Integer
            ? self::number($row, $field)
            : $row->stringOrNull($field) ?? '';
    }

    /**
     * @param Row    $row   The row.
     * @param string $field An integer field.
     *
     * @throws QueryException When the field is missing from the row or not an integer.
     *
     * @return string Its digits; empty for a null.
     */
    private static function number(Row $row, string $field): string
    {
        $number = $row->intOrNull($field);

        return $number === null
            ? ''
            : (string) $number;
    }
}
