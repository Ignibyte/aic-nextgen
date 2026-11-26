<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\McpEntryKind;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * Renders what `make:mcp-resource` and `make:mcp-prompt` write (#334): the
 * entry, one class in the module's `src/Mcp/`.
 */
final readonly class McpEntrySource
{
    /**
     * @param MakeTemplates $makeTemplates Renders the entry's `*.tpl` file.
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
    ) {
    }

    /**
     * @param McpEntryKind $mcpEntryKind A resource or a prompt.
     * @param string       $module       The module.
     * @param string       $namespace    The module's namespace, without the last `\`.
     * @param string       $class        The entry's class.
     * @param string       $permission   The permission it needs.
     *
     * @throws WorkflowRefusal When the template is missing or a placeholder unfilled.
     *
     * @return list<MadeFile> The entry, in the module's `src/Mcp/`.
     */
    public function files(
        McpEntryKind $mcpEntryKind,
        string $module,
        string $namespace,
        string $class,
        string $permission,
    ): array {
        $values = [
            'class' => $class,
            'name' => $mcpEntryKind->listed($module, $class),
            'namespace' => $namespace,
            'permission' => $permission,
            'uri' => $mcpEntryKind->named($module, $class),
        ];
        $source = $this->makeTemplates->render($mcpEntryKind->scaffold(), $mcpEntryKind->value . '.php.tpl', $values);

        return [new MadeFile('modules/' . $module . '/src/Mcp/' . $class . '.php', $source)];
    }
}
