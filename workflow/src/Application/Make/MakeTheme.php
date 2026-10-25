<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\SurfaceSource;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * The theme a component make writes into (#153): the one `config/app.php` names, as the build last recorded it in
 * `build/surface.json`.
 */
final readonly class MakeTheme
{
    /** @param SurfaceSource $surfaceSource The build's surface. */
    public function __construct(private SurfaceSource $surfaceSource)
    {
    }

    /**
     * @throws WorkflowRefusal When there is no surface, or the app names no theme.
     *
     * @return string The theme's folder name under `themes/`.
     */
    public function name(): string
    {
        $surfaceView = $this->surfaceSource->view()
            ?? throw new WorkflowRefusal('build/surface.json is missing: run composer run build.');

        return $surfaceView->theme->name ?? throw new WorkflowRefusal(
            'config/app.php names no theme: make one with bin/aic make:theme <name>, set theme in config/app.php,'
                . ' then run composer run build.',
        );
    }
}
