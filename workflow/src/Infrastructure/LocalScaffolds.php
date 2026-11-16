<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Scaffold\ScaffoldSource;
use AIC\Workflow\Domain\Scaffold\ScaffoldManifest;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function basename;
use function file_get_contents;
use function glob;
use function is_file;
use function sort;

use const GLOB_ONLYDIR;

/**
 * The app's scaffolds on disk (#298): each folder under `scaffolds/` holding
 * its `<kind>.scaffold.php`, which returns a `ScaffoldManifest` the way
 * `config/workflow.php` returns the settings, and the templates beside it.
 */
final readonly class LocalScaffolds implements ScaffoldSource
{
    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @return list<string> Every kind whose folder holds `<kind>.scaffold.php`, sorted.
     */
    #[Override]
    public function kinds(): array
    {
        $folders = glob($this->root . '/scaffolds/*', GLOB_ONLYDIR);
        $kinds = [];

        foreach ($folders === false ? [] : $folders as $folder) {
            if (!is_file($folder . '/' . basename($folder) . '.scaffold.php')) {
                continue;
            }

            $kinds[] = basename($folder);
        }

        sort($kinds);

        return $kinds;
    }

    /**
     * @param string $kind A kind, already held to the kind rule by the loader.
     *
     * @throws WorkflowRefusal When there is no such scaffold, or its file returns anything but a ScaffoldManifest.
     *
     * @return ScaffoldManifest What its file returns.
     */
    #[Override]
    public function manifest(string $kind): ScaffoldManifest
    {
        $relative = 'scaffolds/' . $kind . '/' . $kind . '.scaffold.php';
        $path = $this->root . '/' . $relative;

        if (!is_file($path)) {
            throw new WorkflowRefusal('there is no scaffold ' . $kind . ' (no ' . $relative . '); bin/aic make:catalog lists the kinds.');
        }

        $scaffoldManifest = require $path;

        if (!$scaffoldManifest instanceof ScaffoldManifest) {
            throw new WorkflowRefusal($relative . ' returns ' . ScaffoldManifest::class . ', and here it does not.');
        }

        return $scaffoldManifest;
    }

    /**
     * @param string $kind     A kind.
     * @param string $template A template's file name, already held to the template rule by the loader.
     *
     * @return string|null The template's text; null when there is no such file.
     */
    #[Override]
    public function template(string $kind, string $template): ?string
    {
        $path = $this->root . '/scaffolds/' . $kind . '/' . $template;
        $text = is_file($path)
            ? file_get_contents($path)
            : false;

        return $text === false
            ? null
            : $text;
    }
}
