<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Wiki;

use AIC\Workflow\Application\CodeLookup;
use AIC\Workflow\Domain\Console\SurfaceModule;
use AIC\Workflow\Domain\Console\SurfaceView;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The code wiki's pages (#108): the index and one page per module, each a
 * pure function of the surface and the code index, keyed by their path under
 * the project (`docs/wiki/README.md`, `docs/wiki/modules/<m>.md`).
 */
final readonly class WikiPages
{
    /**
     * Where the wiki lives in the project.
     */
    public const string ROOT = 'docs/wiki';

    /**
     * @param CodeLookup     $codeLookup     The index: files, symbols and crossing references.
     * @param WikiModulePage $wikiModulePage A module's page.
     * @param WikiIndexPage  $wikiIndexPage  The index.
     * @param WikiClasses    $wikiClasses    Counts a module's classes for the index.
     */
    public function __construct(
        private CodeLookup $codeLookup,
        private WikiModulePage $wikiModulePage,
        private WikiIndexPage $wikiIndexPage,
        private WikiClasses $wikiClasses,
    ) {
    }

    /**
     * @param SurfaceView $surfaceView The surface the build wrote.
     *
     * @throws WorkflowRefusal When the index cannot be read, or a framework system has no section in `systems.md`.
     *
     * @return array<string, string> Every page by its path in the project, the index first.
     */
    public function pages(SurfaceView $surfaceView): array
    {
        $pages = [self::ROOT . '/README.md' => $this->index($surfaceView)];

        foreach ($surfaceView->modules as $surfaceModule) {
            $pages[self::ROOT . '/modules/' . $surfaceModule->name . '.md'] = $this->module($surfaceModule, $surfaceView);
        }

        return $pages;
    }

    /**
     * @param SurfaceModule $surfaceModule A module.
     * @param SurfaceView   $surfaceView   The surface.
     *
     * @throws WorkflowRefusal When the index cannot be read, or a framework system has no section in `systems.md`.
     *
     * @return string Its page.
     */
    public function module(SurfaceModule $surfaceModule, SurfaceView $surfaceView): string
    {
        $prefix = 'modules/' . $surfaceModule->name . '/';

        return $this->wikiModulePage->render(
            $surfaceModule,
            $surfaceView,
            $this->codeLookup->symbolsUnder($prefix),
            $this->codeLookup->filesUnder($prefix),
            $this->codeLookup->crossing('module:' . $surfaceModule->name),
        );
    }

    /**
     * @param SurfaceView $surfaceView The surface.
     *
     * @throws WorkflowRefusal When the index cannot be read.
     *
     * @return string The index page.
     */
    public function index(SurfaceView $surfaceView): string
    {
        $classes = [];

        foreach ($surfaceView->modules as $surfaceModule) {
            $symbols = $this->codeLookup->symbolsUnder('modules/' . $surfaceModule->name . '/src/');
            $classes[$surfaceModule->name] = $this->wikiClasses->count($symbols);
        }

        return $this->wikiIndexPage->render($surfaceView, $classes);
    }
}
