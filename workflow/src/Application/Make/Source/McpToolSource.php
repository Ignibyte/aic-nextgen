<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Exception\WorkflowRefusal;

use function lcfirst;
use function substr;

/**
 * Renders what `make:mcp-tool` writes (#316): the tool, its input and its
 * output, in the module's `src/Mcp/`.
 */
final readonly class McpToolSource
{
    /**
     * @param MakeTemplates $makeTemplates Renders the tool's `*.tpl` files.
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param string $folder     The module's `src/Mcp/`, relative to the project.
     * @param string $namespace  The module's namespace, without the last `\`.
     * @param string $class      The tool's class (`ScaffoldLookupTool`).
     * @param string $name       The tool's name (`scaffold.scaffold-lookup`).
     * @param string $permission The permission it needs.
     *
     * @throws WorkflowRefusal When a template is missing or a placeholder unfilled.
     *
     * @return list<MadeFile> The tool, its input and its output.
     */
    public function files(string $folder, string $namespace, string $class, string $name, string $permission): array
    {
        $stem = substr($class, 0, -4);
        $values = [
            'class' => $class,
            'name' => $name,
            'namespace' => $namespace,
            'permission' => $permission,
            'stem' => $stem,
            'variable' => lcfirst($stem),
        ];

        return [
            new MadeFile($folder . $class . '.php', $this->makeTemplates->render('mcp-tool', 'tool.php.tpl', $values)),
            new MadeFile($folder . $stem . 'Input.php', $this->makeTemplates->render('mcp-tool', 'input.php.tpl', $values)),
            new MadeFile($folder . $stem . 'Output.php', $this->makeTemplates->render('mcp-tool', 'output.php.tpl', $values)),
        ];
    }
}
