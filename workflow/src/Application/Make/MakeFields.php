<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_key_exists;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function preg_match;

/**
 * `make:entity`'s `--fields=<name>:<type>,…` (#123): each a lower snake-case column, not `id` or `created_at`, given
 * once, with one of the types the entity store reads: `text`, `numeric`, `date`, `boolean`, `uuid`, `json`,
 * `timestamp`, or `choice(a|b)` with its values (#307). `make:seed` also takes `integer` (#320), since it writes into
 * a table it does not create.
 */
final readonly class MakeFields
{
    /** The types a field may take. */
    private const array TYPES = ['text', 'numeric', 'date', 'boolean', 'uuid', 'json', 'timestamp'];

    /** A choice and its values (#307): `choice(open|shut)`, each value lower case letters, digits, `_` or `-`. */
    private const string CHOICE = '/^choice\\(([a-z0-9_-]+(?:\\|[a-z0-9_-]+)*)\\)\\z/';

    /**
     * @param string $fields What `--fields` gave.
     *
     * @throws WorkflowRefusal When it names no field, or a field breaks a rule.
     *
     * @return non-empty-list<EntityField> The fields, in order.
     */
    public function parse(string $fields): array
    {
        return $this->parsed($fields, self::TYPES);
    }

    /**
     * @param string $fields What `make:seed`'s `--fields` gave (#320).
     *
     * @throws WorkflowRefusal When it names no field, or a field breaks a rule.
     *
     * @return non-empty-list<EntityField> The fields, in order; `integer` is a type too.
     */
    public function seeded(string $fields): array
    {
        return $this->parsed($fields, [...self::TYPES, 'integer']);
    }

    /**
     * @param string       $fields What `--fields` gave.
     * @param list<string> $types  The types a field may take, but `choice`.
     *
     * @throws WorkflowRefusal When it names no field, or a field breaks a rule.
     *
     * @return non-empty-list<EntityField> The fields, in order.
     */
    private function parsed(string $fields, array $types): array
    {
        if ($fields === '') {
            throw $this->usage('--fields names no field: give --fields=<name>:<type>,… with types ' . $this->types($types));
        }

        $parsed = [];

        foreach (explode(',', $fields) as $field) {
            $entityField = $this->field($field, $types);

            if (array_key_exists($entityField->name, $parsed)) {
                throw $this->usage('the field "' . $entityField->name . '" is given twice');
            }

            $parsed[$entityField->name] = $entityField;
        }

        return array_values($parsed);
    }

    /**
     * @param string       $field One `<name>:<type>`.
     * @param list<string> $types The types it may take, but `choice`.
     *
     * @throws WorkflowRefusal When its name or type breaks a rule.
     *
     * @return EntityField It.
     */
    private function field(string $field, array $types): EntityField
    {
        $parts = explode(':', $field);
        $name = $parts[0];
        $type = $parts[1] ?? '';

        if (preg_match('/^[a-z][a-z0-9_]*\\z/', $name) !== 1 || in_array($name, ['id', 'created_at'], true)) {
            throw $this->usage('the field "' . $name . '" must be lower snake case and not id or created_at, which every table has');
        }

        if (count($parts) !== 2) {
            throw $this->usage('the field "' . $field . '" needs one type of ' . $this->types($types));
        }

        return $this->typed($name, $type, $types);
    }

    /**
     * @param string       $name  The field's name.
     * @param string       $type  Its type, as given.
     * @param list<string> $types The types it may take, but `choice`.
     *
     * @throws WorkflowRefusal When the type is not one the entity store reads.
     *
     * @return EntityField It.
     */
    private function typed(string $name, string $type, array $types): EntityField
    {
        $choices = $this->choices($type);

        if ($choices !== []) {
            return new EntityField($name, 'choice', $choices);
        }

        if (!in_array($type, $types, true)) {
            throw $this->usage('the field "' . $name . ':' . $type . '" needs one type of ' . $this->types($types));
        }

        return new EntityField($name, $type);
    }

    /**
     * @param string $type A field's type, as given.
     *
     * @return list<string> A `choice(a|b)`'s values, each once; empty for any other type.
     */
    private function choices(string $type): array
    {
        $matches = [];

        $found = preg_match(self::CHOICE, $type, $matches) === 1;
        $values = $matches[1] ?? '';

        return $found
            ? array_values(array_unique(explode('|', $values)))
            : [];
    }

    /**
     * @param list<string> $types The types a field may take, but `choice`.
     *
     * @return string The types, for a refusal.
     */
    private function types(array $types): string
    {
        return implode(', ', $types) . ' or choice(<a>|<b>)';
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
