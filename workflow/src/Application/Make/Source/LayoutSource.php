<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

use function lcfirst;

/**
 * A named page layout (`make:layout`, #157): its page data, with the content region only, and the page template that
 * prints it. The document falls back to the theme's `html.phtml`.
 */
final readonly class LayoutSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $name The layout's name.
     * @param string $case Its `ThemeLayoutName` case.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The page data class's bytes.
     */
    public function data(string $name, string $case): string
    {
        return $this->makeTemplates->render('layout', 'data.php.tpl', ['case' => $case, 'name' => $name]);
    }

    /**
     * @param string $name The layout's name.
     * @param string $case Its `ThemeLayoutName` case.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The page template's bytes.
     */
    public function page(string $name, string $case): string
    {
        return $this->makeTemplates->render(
            'layout',
            'page.phtml.tpl',
            ['case' => $case, 'name' => $name, 'variable' => lcfirst($case) . 'Page'],
        );
    }
}
