<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The app's scaffolds (#298): the kinds under `scaffolds/`, each one's
 * manifest and the text of its templates.
 */
interface ScaffoldSource
{
    /**
     * @return list<string> Every kind whose folder holds `<kind>.scaffold.php`, sorted.
     */
    public function kinds(): array;

    /**
     * @param string $kind A kind.
     *
     * @throws WorkflowRefusal When there is no such scaffold, or its file returns anything but a ScaffoldManifest.
     *
     * @return ScaffoldManifest What its file returns.
     */
    public function manifest(string $kind): ScaffoldManifest;

    /**
     * @param string $kind     A kind.
     * @param string $template A template's file name in its folder.
     *
     * @return string|null The template's text; null when there is no such file.
     */
    public function template(string $kind, string $template): ?string;
}
