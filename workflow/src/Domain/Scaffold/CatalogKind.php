<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Scaffold;

use function array_map;

/**
 * One row of `bin/aic make:catalog` (#298): a kind an agent may make, where
 * it comes from, what it makes, how to call it, the files it writes and when
 * not to use it.
 */
final readonly class CatalogKind
{
    /**
     * @param string            $kind        The kind: `make:job` for a built-in, the folder's name for an app scaffold.
     * @param string            $source      `built-in`, or the scaffold's folder.
     * @param string            $description What it makes.
     * @param string            $usage       Its usage line.
     * @param string            $from        The kind it was started from; empty when none.
     * @param list<string>      $files       The path patterns it writes; empty for a built-in.
     * @param list<string>      $notFor      When not to use it.
     * @param list<ScaffoldArg> $args        Its arguments; empty for a built-in.
     */
    public function __construct(
        public string $kind,
        public string $source,
        public string $description,
        public string $usage,
        public string $from = '',
        public array $files = [],
        public array $notFor = [],
        public array $args = [],
    ) {
    }

    /**
     * @return array{args: list<array{choices: list<string>, default: string|null, name: string, type: string}>,
     *               description: string, files: list<string>, from: string, kind: string, not_for: list<string>,
     *               source: string, usage: string} The row as `--json` prints it.
     */
    public function data(): array
    {
        return [
            'args' => array_map(static fn (ScaffoldArg $scaffoldArg): array => [
                'choices' => $scaffoldArg->choices,
                'default' => $scaffoldArg->default,
                'name' => $scaffoldArg->name,
                'type' => $scaffoldArg->argType->value,
            ], $this->args),
            'description' => $this->description,
            'files' => $this->files,
            'from' => $this->from,
            'kind' => $this->kind,
            'not_for' => $this->notFor,
            'source' => $this->source,
            'usage' => $this->usage,
        ];
    }
}
