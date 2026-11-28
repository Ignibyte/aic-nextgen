<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Exception\WorkflowRefusal;

use function implode;
use function lcfirst;
use function preg_replace;
use function sprintf;
use function str_replace;
use function ucwords;

/**
 * A pivot table's migration (`make:relation`, #306) and the declarations that
 * relate its two tables through it: the pivot's entity type and a ManyToMany
 * on each side, as methods and relations for the module's types class (#328),
 * or as lines the agent adds to a types class the make does not edit.
 */
final readonly class RelationSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string                $namespace The module's namespace.
     * @param string                $class     The migration.
     * @param string                $version   The version its ledger row records.
     * @param array<string, string> $tables    The owner's and the target's tables, then the pivot's: `owner`, `target`, `pivot`.
     * @param array<string, bool>   $uuid      Whether the owner's and the target's keys are uuids: `owner`, `target`.
     * @param list<EntityField>     $fields    The pivot's own fields.
     *
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return string The migration's bytes.
     */
    public function migration(
        string $namespace,
        string $class,
        string $version,
        array $tables,
        array $uuid,
        array $fields,
    ): string {
        $ownerKey = self::column($tables['owner'] ?? '');
        $targetKey = self::column($tables['target'] ?? '');
        $columns = '';

        foreach ($fields as $field) {
            $columns .= "\n            " . $field->column();
        }

        return $this->makeTemplates->render('relation', 'pivot.php.tpl', [
            'class' => $class,
            'columns' => $columns,
            'namespace' => $namespace,
            'owner' => $tables['owner'] ?? '',
            'ownerColumn' => self::key($ownerKey, $uuid['owner'] ?? false),
            'ownerKey' => $ownerKey,
            'table' => $tables['pivot'] ?? '',
            'target' => $tables['target'] ?? '',
            'targetColumn' => self::key($targetKey, $uuid['target'] ?? false),
            'targetKey' => $targetKey,
            'version' => $version,
        ]);
    }

    /**
     * @param array<string, string> $tables The owner's, the target's and the pivot's tables.
     * @param array<string, bool>   $uuid   Whether the owner's and the target's keys are uuids.
     * @param list<EntityField>     $fields The pivot's own fields.
     *
     * @return list<string> What to add by hand: the pivot's entity type, and the relation on each side's.
     */
    public function lines(array $tables, array $uuid, array $fields): array
    {
        $owner = $tables['owner'] ?? '';
        $target = $tables['target'] ?? '';
        $ownerKey = self::column($owner);
        $targetKey = self::column($target);
        $pivotFields = self::pivotFields($tables, $uuid, $fields);
        $pivot = sprintf("new EntityType('%s', 'id', [%s])", $tables['pivot'] ?? '', implode(', ', $pivotFields));

        return [
            "The pivot's entity type: " . $pivot . '.',
            self::relation($owner, $target, $ownerKey, $targetKey),
            self::relation($target, $owner, $targetKey, $ownerKey),
        ];
    }

    /**
     * @param array<string, string> $tables The owner's, the target's and the pivot's tables.
     *
     * @return string The pivot's accessor in the module's types: `scaffold_tasks_scaffold_events` gives
     *                `scaffoldTasksScaffoldEvents`.
     */
    public function pivotAccessor(array $tables): string
    {
        return lcfirst(str_replace('_', '', ucwords($tables['pivot'] ?? '', '_')));
    }

    /**
     * @param array<string, string> $tables The owner's, the target's and the pivot's tables.
     * @param array<string, bool>   $uuid   Whether the owner's and the target's keys are uuids.
     * @param list<EntityField>     $fields The pivot's own fields.
     *
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return string The pivot's accessor, each line ending in a newline.
     */
    public function pivotType(array $tables, array $uuid, array $fields): string
    {
        $lines = '';

        foreach (self::pivotFields($tables, $uuid, $fields) as $field) {
            $lines .= '            ' . $field . ",\n";
        }

        return $this->makeTemplates->render('relation', 'pivot-type.php.tpl', [
            'fields' => $lines,
            'table' => $tables['pivot'] ?? '',
            'variable' => $this->pivotAccessor($tables),
        ]);
    }

    /**
     * @param array<string, string> $tables    The owner's, the target's and the pivot's tables.
     * @param array<string, string> $accessors Each table's accessor in the module's types.
     *
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return array<string, string> Each side's accessor and the ManyToMany it gains, each line ending in a newline.
     */
    public function relations(array $tables, array $accessors): array
    {
        $owner = $tables['owner'] ?? '';
        $target = $tables['target'] ?? '';
        $ownerAccessor = $accessors[$owner] ?? '';
        $targetAccessor = $accessors[$target] ?? '';
        $pivot = $this->pivotAccessor($tables);

        return [
            $ownerAccessor => $this->manyToMany([$target, $targetAccessor], self::column($owner), $pivot, self::column($target)),
            $targetAccessor => $this->manyToMany([$owner, $ownerAccessor], self::column($target), $pivot, self::column($owner)),
        ];
    }

    /**
     * @param array{string, string} $other    The table it relates to, which names the relation, and its accessor.
     * @param string                $key      The pivot's column that holds the side's key.
     * @param string                $pivot    The pivot's accessor.
     * @param string                $otherKey The pivot's column that holds the other's key.
     *
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return string The relation's lines.
     */
    private function manyToMany(array $other, string $key, string $pivot, string $otherKey): string
    {
        return $this->makeTemplates->render('relation', 'relation.php.tpl', [
            'key' => $key,
            'name' => $other[0],
            'otherKey' => $otherKey,
            'pivot' => $pivot,
            'target' => $other[1],
        ]);
    }

    /**
     * @param array<string, string> $tables The owner's, the target's and the pivot's tables.
     * @param array<string, bool>   $uuid   Whether the owner's and the target's keys are uuids.
     * @param list<EntityField>     $fields The pivot's own fields.
     *
     * @return non-empty-list<string> The pivot's `Field`s: each side's key, then its own.
     */
    private static function pivotFields(array $tables, array $uuid, array $fields): array
    {
        $pivotFields = [
            self::field(self::column($tables['owner'] ?? ''), $uuid['owner'] ?? false),
            self::field(self::column($tables['target'] ?? ''), $uuid['target'] ?? false),
        ];

        foreach ($fields as $field) {
            $pivotFields[] = $field->field();
        }

        return $pivotFields;
    }

    /**
     * @param string $side     The table whose entity type gains the relation.
     * @param string $other    The table it relates to, which names the relation.
     * @param string $key      The pivot's column that holds the side's key.
     * @param string $otherKey The pivot's column that holds the other's key.
     *
     * @return string The line that adds the ManyToMany to the side's entity type.
     */
    private static function relation(string $side, string $other, string $key, string $otherKey): string
    {
        return 'Add to ' . $side . "'s entity type: relations: [new Relation('" . $other . "', RelationKind::ManyToMany,"
            . ' target: <' . $other . " type>, key: '" . $key . "', pivot: <the pivot type>, otherKey: '" . $otherKey . "')].";
    }

    /**
     * @param string $table A table, such as `stock_items`.
     *
     * @return string The pivot's column that holds its key: its singular and `_id`, such as `stock_item_id`.
     */
    private static function column(string $table): string
    {
        return (preg_replace(['/ies$/', '/s$/'], ['y', ''], $table) ?? $table) . '_id';
    }

    /**
     * @param string $column A key column.
     * @param bool   $uuid   Whether it holds a uuid.
     *
     * @return string Its migration builder call.
     */
    private static function key(string $column, bool $uuid): string
    {
        return $uuid
            ? "->column('" . $column . "', \$schema->types()->uuid())"
            : "->bigInteger('" . $column . "')";
    }

    /**
     * @param string $column A key column.
     * @param bool   $uuid   Whether it holds a uuid.
     *
     * @return string Its `Field` in the pivot's entity type.
     */
    private static function field(string $column, bool $uuid): string
    {
        return "new Field('" . $column . "', FieldType::" . ($uuid ? 'Uuid' : 'Integer') . ')';
    }
}
