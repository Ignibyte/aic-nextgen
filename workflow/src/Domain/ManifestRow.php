<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain;

/**
 * One row of a spec's `### File Manifest` (#118): its path, what it does to
 * it, and the `Change` cell as written, for a refusal that quotes it.
 */
final readonly class ManifestRow
{
    /**
     * @param string          $path       The path, relative to the project, backticks trimmed.
     * @param FileChange|null $fileChange What the row does to it; null when the cell is no change AIC knows.
     * @param string          $cell       The `Change` cell as written.
     */
    public function __construct(
        public string $path,
        public ?FileChange $fileChange,
        public string $cell,
    ) {
    }
}
