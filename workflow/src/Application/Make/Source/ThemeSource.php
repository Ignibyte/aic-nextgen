<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/** Authored typed region data and restricted templates for the first selected site theme. */
final readonly class ThemeSource
{
    /**
     * @param MakeTemplates $makeTemplates The built-in templates (#301).
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The page data class's source.
     */
    public function data(): string
    {
        return $this->makeTemplates->render('theme', 'data.php.tpl', []);
    }

    /**
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The authored document template.
     */
    public function html(): string
    {
        return $this->makeTemplates->render('theme', 'html.phtml.tpl', []);
    }

    /**
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The page template, placing each region.
     */
    public function page(): string
    {
        return $this->makeTemplates->render('theme', 'page.phtml.tpl', []);
    }

    /**
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string A starting stylesheet, with no favicon assumed.
     */
    public function css(): string
    {
        return $this->makeTemplates->render('theme', 'theme.css.tpl', []);
    }
}
