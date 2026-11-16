<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Scaffold;

/**
 * One file a scaffold writes (#298): where, relative to the module's folder,
 * with placeholders (`src/Job/{name}Poll.php`), and the template beside the
 * manifest that fills it.
 */
final readonly class ScaffoldFile
{
    /**
     * @param string $path     The path pattern, relative to `modules/<module>/`.
     * @param string $template The template's file name in the scaffold's folder, such as `job.php.tpl`.
     */
    public function __construct(
        public string $path,
        public string $template,
    ) {
    }
}
