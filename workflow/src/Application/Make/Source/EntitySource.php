<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Domain\Make\EntityPlan;

use function implode;
use function max;
use function str_pad;
use function str_replace;
use function strlen;
use function strtr;

/**
 * The entity `make:entity` writes (#123): one readonly property per field, and its row as one line for its list.
 */
final readonly class EntitySource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace {namespace}\Entity;

        /**
         * One `{table}` row, made by bin/aic make:entity (#123).
         */
        final readonly class {class}
        {
            /**
        {params}     */
            public function __construct(
        {properties}    ) {
            }

            /**
             * @return string The row in one line, for its list.
             */
            public function line(): string
            {
                return {line};
            }
        }
        PHP;

    /**
     * @param EntityPlan $entityPlan The entity.
     *
     * @return string The file's bytes.
     */
    public function source(EntityPlan $entityPlan): string
    {
        [$params, $properties, $parts] = $this->members($entityPlan->fields);

        return strtr(self::TEMPLATE, [
            '{class}' => $entityPlan->class,
            '{line}' => implode("\n            . ", $parts),
            '{namespace}' => $entityPlan->namespace,
            '{params}' => $params,
            '{properties}' => $properties,
            '{table}' => $entityPlan->table,
        ]) . "\n";
    }

    /**
     * @param non-empty-list<EntityField> $fields The fields.
     *
     * @return array{string, string, list<string>} The constructor's `@param` lines, its promoted properties, and each
     *                                             field's part of the line.
     */
    private function members(array $fields): array
    {
        $params = '';
        $properties = '';
        $parts = [];
        $width = $this->width($fields);

        foreach ($fields as $field) {
            $type = $field->isBoolean()
                ? 'bool'
                : 'string';
            $params .= '     * @param ' . str_pad($type, $width['type']) . ' ' . str_pad('$' . $field->property(), $width['name'])
                . ' The ' . $field->name . " column.\n";
            $properties .= '        public ' . $type . ' $' . $field->property() . ",\n";
            $parts[] = $this->part($field, $parts === []
                ? ''
                : ', ');
        }

        return [$params, $properties, $parts];
    }

    /**
     * @param EntityField $entityField A field.
     * @param string      $separator   What comes before its words: nothing for the first field.
     *
     * @return string Its part of the line: its words, a colon and its value.
     */
    private function part(EntityField $entityField, string $separator): string
    {
        $value = $entityField->isBoolean()
            ? '($this->' . $entityField->property() . " ? 'yes' : 'no')"
            : '$this->' . $entityField->property();

        return "'" . $separator . str_replace('_', ' ', $entityField->name) . ": ' . " . $value;
    }

    /**
     * @param non-empty-list<EntityField> $fields The fields.
     *
     * @return array{type: int, name: int} The widest type and variable, to align the docblock.
     */
    private function width(array $fields): array
    {
        $type = 0;
        $name = 0;

        foreach ($fields as $field) {
            $type = max($type, $field->isBoolean() ? 4 : 6);
            $name = max($name, strlen($field->property()) + 1);
        }

        return ['type' => $type, 'name' => $name];
    }
}
