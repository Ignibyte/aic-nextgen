<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * A GET page (`make:page`): its component in the theme's layout (#122), or in a named one (#157).
 */
final readonly class PageSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The page.
     * @param string $path      Its path.
     * @param string $component The component it shows.
     * @param string $title     The title it gives the component.
     * @param string $case      The `ThemeLayoutName` case of the layout it picks; empty for the theme's own.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(
        string $namespace,
        string $class,
        string $path,
        string $component,
        string $title,
        string $case = '',
    ): string {
        $named = $case !== '';

        return $this->makeTemplates->render('page', 'page.php.tpl', [
            'class' => $class,
            'component' => $component,
            'layoutArgument' => $named
                ? "\n            ThemeLayoutName::" . $case . ','
                : '',
            'layoutName' => $named
                ? $case . ' '
                : '',
            'layoutUse' => $named
                ? "use App\\Generated\\ThemeLayoutName;\n"
                : '',
            'namespace' => $namespace,
            'path' => $path,
            'title' => $title,
        ]);
    }
}
