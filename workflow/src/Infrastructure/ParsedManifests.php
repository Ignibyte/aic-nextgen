<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Make\ManifestEdits;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\Make\ManifestCall;
use AIC\Workflow\Domain\Make\ManifestFacts;
use AIC\Workflow\Exception\WorkflowRefusal;
use AIC\Workflow\Parsing\ManifestEditor;
use Override;

/**
 * The module manifests through php-parser (#122), read from the files the
 * make writes, so gate:31's in-memory overlay sees its own edits.
 */
final readonly class ParsedManifests implements ManifestEdits
{
    /**
     * @param ProjectFiles   $projectFiles   The files.
     * @param ManifestEditor $manifestEditor Reads and edits a manifest.
     */
    public function __construct(
        private ProjectFiles $projectFiles,
        private ManifestEditor $manifestEditor = new ManifestEditor(),
    ) {
    }

    /**
     * @param string $path A manifest.
     *
     * @throws WorkflowRefusal When it cannot be read or is not an editable manifest.
     *
     * @return ManifestFacts What it declares.
     */
    #[Override]
    public function facts(string $path): ManifestFacts
    {
        return $this->manifestEditor->facts($this->text($path), $path);
    }

    /**
     * @param string       $path         A manifest.
     * @param ManifestCall $manifestCall The call to add.
     *
     * @throws WorkflowRefusal When it cannot be read or is not an editable manifest.
     *
     * @return string Its new text.
     */
    #[Override]
    public function edit(string $path, ManifestCall $manifestCall): string
    {
        return $this->manifestEditor->edit($this->text($path), $path, $manifestCall);
    }

    /**
     * @param string $path A manifest.
     *
     * @throws WorkflowRefusal When there is none.
     *
     * @return string Its text.
     */
    private function text(string $path): string
    {
        return $this->projectFiles->read($path)
            ?? throw new WorkflowRefusal($path . ' is not there.');
    }
}
