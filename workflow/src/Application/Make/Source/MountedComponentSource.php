<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

use function lcfirst;

/**
 * A component with a script (`make:component --script`, #315): the class with its JSON `data`, the template whose
 * element names the script in `data-mount`, and the script, a module exporting `mount` and `unmount`.
 */
final readonly class MountedComponentSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $file  `mounted.php.tpl`, `mounted.phtml.tpl` or `mounted.js.tpl`.
     * @param string $class The component.
     * @param string $kebab Its kebab name, the script's and the template's.
     * @param string $theme The theme.
     * @param string $made  The make that made it, such as `make:component (#122)`.
     *
     * @throws WorkflowRefusal When the template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(string $file, string $class, string $kebab, string $theme, string $made): string
    {
        return $this->makeTemplates->render('component', $file, [
            'class' => $class,
            'kebab' => $kebab,
            'made' => $made,
            'theme' => $theme,
            'variable' => lcfirst($class),
        ]);
    }
}
