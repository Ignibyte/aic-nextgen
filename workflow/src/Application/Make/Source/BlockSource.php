<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * A block (`make:block`): one paragraph where its placement says (#122).
 */
final readonly class BlockSource
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
     * @param string $class     The block.
     * @param string $region    Its region.
     * @param string $weight    Its weight.
     * @param string $prefix    Its route prefix; empty for every page.
     * @param string $title     Its line.
     * @param string $id        Its paragraph's id.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(
        string $namespace,
        string $class,
        string $region,
        string $weight,
        string $prefix,
        string $title,
        string $id,
    ): string {
        return $this->makeTemplates->render('block', 'block.php.tpl', [
            'class' => $class,
            'id' => $id,
            'namespace' => $namespace,
            'prefix' => $prefix,
            'region' => $region,
            'title' => $title,
            'weight' => $weight,
            'where' => $prefix === '' ? 'on every page' : 'under `' . $prefix . '`',
        ]);
    }
}
