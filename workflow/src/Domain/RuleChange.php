<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One path a run changed, as a house rule reads it (#104): the path, its
 * content at the base commit and now, each as a file the rule may read.
 */
final readonly class RuleChange
{
    /**
     * @param string      $path The path, relative to the project.
     * @param string|null $base A file holding it at the base commit; null when the run added it.
     * @param string|null $head A file holding it now; null when the run deleted it.
     */
    public function __construct(
        public string $path,
        public ?string $base,
        public ?string $head,
    ) {
    }

    /**
     * @return array{base: string|null, head: string|null, path: string} Its form in a rule's input.
     */
    public function toArray(): array
    {
        return ['base' => $this->base, 'head' => $this->head, 'path' => $this->path];
    }
}
