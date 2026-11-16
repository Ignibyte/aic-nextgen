<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * A command (`make:command`): one line, exit 0 (#122).
 */
final readonly class CommandSource
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
     * @param string $class     The command.
     * @param string $name      Its name.
     *
     * @throws WorkflowRefusal When its template is missing.
     *
     * @return string The file's bytes.
     */
    public function source(string $namespace, string $class, string $name): string
    {
        return $this->makeTemplates->render('command', 'command.php.tpl', ['namespace' => $namespace, 'class' => $class, 'name' => $name]);
    }
}
