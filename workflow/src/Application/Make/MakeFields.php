<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_key_exists;
use function array_values;
use function count;
use function explode;
use function in_array;
use function preg_match;

/**
 * `make:entity`'s `--fields=<name>:<type>,…` (#123): each a lower snake-case column, not `id` or `created_at`, given
 * once, with one of the four types the entity store reads.
 */
final readonly class MakeFields
{
    /** The types a field may take. */
    private const array TYPES = ['text', 'numeric', 'date', 'boolean'];

    /**
     * @param string $fields What `--fields` gave.
     *
     * @throws WorkflowRefusal When it names no field, or a field breaks a rule.
     *
     * @return non-empty-list<EntityField> The fields, in order.
     */
    public function parse(string $fields): array
    {
        if ($fields === '') {
            throw $this->usage('--fields names no field: give --fields=<name>:<type>,… with types ' . $this->types());
        }

        $parsed = [];

        foreach (explode(',', $fields) as $field) {
            $entityField = $this->field($field);

            if (array_key_exists($entityField->name, $parsed)) {
                throw $this->usage('the field "' . $entityField->name . '" is given twice');
            }

            $parsed[$entityField->name] = $entityField;
        }

        return array_values($parsed);
    }

    /**
     * @param string $field One `<name>:<type>`.
     *
     * @throws WorkflowRefusal When its name or type breaks a rule.
     *
     * @return EntityField It.
     */
    private function field(string $field): EntityField
    {
        $parts = explode(':', $field);
        $name = $parts[0];
        $type = $parts[1] ?? '';

        if (preg_match('/^[a-z][a-z0-9_]*\\z/', $name) !== 1 || in_array($name, ['id', 'created_at'], true)) {
            throw $this->usage('the field "' . $name . '" must be lower snake case and not id or created_at, which every table has');
        }

        if (!in_array($type, self::TYPES, true) || count($parts) !== 2) {
            throw $this->usage('the field "' . $field . '" needs one type of ' . $this->types());
        }

        return new EntityField($name, $type);
    }

    /**
     * @return string The types, for a refusal.
     */
    private function types(): string
    {
        return 'text, numeric, date or boolean';
    }

    /**
     * @param string $why What is wrong.
     *
     * @return WorkflowRefusal A usage refusal, exit 2.
     */
    private function usage(string $why): WorkflowRefusal
    {
        return new WorkflowRefusal($why, ConsoleArguments::USAGE);
    }
}
