<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Domain\Make\ManifestCall;
use AIC\Workflow\Domain\Make\ManifestFacts;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The module manifests a make reads and edits (#122), through the same files
 * the make writes, so gate:31's overlay sees its own edits.
 */
interface ManifestEdits
{
    /**
     * @param string $path A manifest, relative to the project's root.
     *
     * @throws WorkflowRefusal When it cannot be read or is not an editable manifest.
     *
     * @return ManifestFacts What it declares.
     */
    public function facts(string $path): ManifestFacts;

    /**
     * @param string       $path         A manifest, relative to the project's root.
     * @param ManifestCall $manifestCall The call to add.
     *
     * @throws WorkflowRefusal When it cannot be read or is not an editable manifest.
     *
     * @return string Its new text, not written.
     */
    public function edit(string $path, ManifestCall $manifestCall): string;
}
