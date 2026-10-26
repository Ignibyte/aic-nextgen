<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Controller\Contracts\Command;
use AIC\Workflow\Application\Make\MakeComponentCommand;
use AIC\Workflow\Application\Make\MakeComponents;
use AIC\Workflow\Application\Make\MakeLayoutCommand;
use AIC\Workflow\Application\Make\MakeLayouts;
use AIC\Workflow\Application\Make\MakePageCommand;
use AIC\Workflow\Application\Make\MakeRun;
use AIC\Workflow\Application\Make\MakeTheme;
use AIC\Workflow\Application\Make\MakeThemeCommand;

/**
 * The makes that write into the theme (#153, #157), composed apart from `MakeParts` so each stays under PHPMD's
 * coupling cap: `make:component`, `make:page` (its own view), `make:layout` and `make:theme`, each over the same run.
 */
final readonly class ThemeMakes
{
    /**
     * @param string $root The project's root, whose build surface names the theme.
     */
    public function __construct(private string $root)
    {
    }

    /**
     * @param MakeRun $makeRun The run every make goes through.
     *
     * @return Command `make:component`.
     */
    public function component(MakeRun $makeRun): Command
    {
        return new MakeComponentCommand($makeRun, $this->makeComponents());
    }

    /**
     * @param MakeRun $makeRun The run every make goes through.
     *
     * @return Command `make:page`, which writes its own view into the theme and may name a layout.
     */
    public function page(MakeRun $makeRun): Command
    {
        return new MakePageCommand($makeRun, $this->makeComponents(), $this->makeLayouts());
    }

    /**
     * @param MakeRun $makeRun The run every make goes through.
     *
     * @return Command `make:layout`.
     */
    public function layout(MakeRun $makeRun): Command
    {
        return new MakeLayoutCommand($makeRun, $this->makeLayouts());
    }

    /**
     * @param MakeRun $makeRun The run every make goes through.
     *
     * @return Command `make:theme`.
     */
    public function theme(MakeRun $makeRun): Command
    {
        return new MakeThemeCommand($makeRun);
    }

    /**
     * @return MakeComponents Writes components into the theme.
     */
    private function makeComponents(): MakeComponents
    {
        return new MakeComponents($this->makeTheme());
    }

    /**
     * @return MakeLayouts Names and writes the theme's layouts.
     */
    private function makeLayouts(): MakeLayouts
    {
        return new MakeLayouts($this->makeTheme());
    }

    /**
     * @return MakeTheme The theme the build's surface names.
     */
    private function makeTheme(): MakeTheme
    {
        return new MakeTheme(new SurfaceEntries($this->root, new LocalHost($this->root)));
    }
}
