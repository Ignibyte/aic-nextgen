<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

use function lcfirst;
use function str_replace;
use function strtolower;
use function ucfirst;

/**
 * What one `make:detail-page` line asks for (#341): the module's namespace,
 * the page's name without `DetailPage`, the table, its key and the key's
 * type, the rows' path, the fields, and the tabs that group them.
 */
final readonly class DetailPagePlan
{
    /**
     * @param string                                $namespace The module's namespace, without the last `\`.
     * @param string                                $stem      The page's name without `DetailPage`, such as `ScaffoldEvent`.
     * @param string                                $table     The table it shows a row of.
     * @param string                                $key       The table's key column.
     * @param bool                                  $uuid      Whether the key is a uuid; an integer otherwise.
     * @param string                                $path      The rows' path; the page is `<path>/{id}`.
     * @param non-empty-list<EntityField>           $fields    The fields, the first the page's heading.
     * @param non-empty-array<string, list<string>> $tabs      Each tab's fields, by the tab's name, in order.
     * @param string                                $anchor    The part the tabs refresh, `<stem in kebab case>-tab`.
     */
    public function __construct(
        public string $namespace,
        public string $stem,
        public string $table,
        public string $key,
        public bool $uuid,
        public string $path,
        public array $fields,
        public array $tabs,
        public string $anchor,
    ) {
    }

    /**
     * @return array<string, string> The placeholders its names decide.
     */
    public function values(): array
    {
        return [
            'anchor' => $this->anchor,
            'heading' => $this->fields[0]->name,
            'headingType' => $this->fieldType($this->fields[0]),
            'key' => $this->key,
            'keyType' => $this->uuid ? 'Uuid' : 'Integer',
            'namespace' => $this->namespace,
            'path' => $this->path,
            'route' => $this->route(),
            'stem' => $this->stem,
            'table' => $this->table,
            'title' => ucfirst(str_replace('_', ' ', $this->table)),
            'variable' => $this->variable(),
        ];
    }

    /**
     * @return string The page's route, `<path>/{id:int}` or `<path>/{id:uuid}`.
     */
    public function route(): string
    {
        return $this->path . '/{id:' . ($this->uuid ? 'uuid' : 'int') . '}';
    }

    /**
     * @return string The stem as a variable's start, such as `scaffoldEvent`.
     */
    public function variable(): string
    {
        return lcfirst($this->stem);
    }

    /**
     * @param EntityField $entityField A field.
     *
     * @return string Its `FieldType` case, such as `Timestamp`.
     */
    public function fieldType(EntityField $entityField): string
    {
        return ucfirst(strtolower($entityField->type));
    }
}
