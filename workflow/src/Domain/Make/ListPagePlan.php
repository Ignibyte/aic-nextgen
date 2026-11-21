<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

use function lcfirst;
use function str_replace;
use function ucfirst;
use function ucwords;

/**
 * What one `make:list-page` line asks for (#324): the module's namespace, the
 * page's name without `ListPage`, the table and its key, the path, how many
 * rows a page shows, the columns, the ones it filters by and the ones it
 * sorts by.
 */
final readonly class ListPagePlan
{
    /**
     * @param string                      $namespace The module's namespace, without the last `\`.
     * @param string                      $stem      The page's name without `ListPage`, such as `ScaffoldShelf`.
     * @param string                      $table     The table it lists.
     * @param string                      $key       The table's key, which ends every sort.
     * @param string                      $path      The page's path.
     * @param int                         $perPage   How many rows a page shows.
     * @param non-empty-list<EntityField> $columns   The columns, in the table's order.
     * @param non-empty-list<EntityField> $filters   The columns it filters by, each text, choice or boolean.
     * @param non-empty-list<string>      $sorts     The columns it sorts by; the first is the default.
     */
    public function __construct(
        public string $namespace,
        public string $stem,
        public string $table,
        public string $key,
        public string $path,
        public int $perPage,
        public array $columns,
        public array $filters,
        public array $sorts,
    ) {
    }

    /**
     * @return array<string, string> The placeholders its names decide.
     */
    public function values(): array
    {
        return [
            'key' => $this->key,
            'namespace' => $this->namespace,
            'path' => $this->path,
            'perPage' => (string) $this->perPage,
            'stem' => $this->stem,
            'table' => $this->table,
            'title' => $this->title(),
            'type' => $this->type(),
            'variable' => $this->variable(),
        ];
    }

    /**
     * @return string The stem as a variable's start, such as `scaffoldShelf`.
     */
    public function variable(): string
    {
        return lcfirst($this->stem);
    }

    /**
     * @return string The page's title, from the table, such as `Scaffold shelves`.
     */
    public function title(): string
    {
        return ucfirst(str_replace('_', ' ', $this->table));
    }

    /**
     * @return string The repository's entity type method, from the table, such as `scaffoldShelvesType`.
     */
    public function type(): string
    {
        return lcfirst(str_replace('_', '', ucwords($this->table, '_'))) . 'Type';
    }
}
