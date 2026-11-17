<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * One row of the made ledger, `.aic/made.jsonl` (#302): a file a make
 * created in a run, the kind that made it, the hash of that kind's
 * scaffold folder, the hash of the bytes it wrote, and the run.
 */
final readonly class MadeRow
{
    /**
     * @param string $path     The file, relative to the project's root.
     * @param string $kind     The kind that made it, such as `page` or an app's `poller`.
     * @param string $scaffold `sha256:<hex>` over the kind's scaffold folder; empty when the kind has none.
     * @param string $content  The hash of the bytes it wrote, `xxh128:<hex>`, as `code.made` carries it.
     * @param string $run      The run that made it.
     */
    public function __construct(
        public string $path,
        public string $kind,
        public string $scaffold,
        public string $content,
        public string $run,
    ) {
    }

    /**
     * @return string Its line in the ledger, its keys sorted, with no line break.
     */
    public function line(): string
    {
        return json_encode([
            'content' => $this->content,
            'kind' => $this->kind,
            'path' => $this->path,
            'run' => $this->run,
            'scaffold' => $this->scaffold,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
