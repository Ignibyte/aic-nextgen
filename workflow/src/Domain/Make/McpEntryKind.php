<?php

declare(strict_types=1);

namespace AIC\Workflow\Domain\Make;

use function preg_replace;
use function str_replace;
use function strlen;
use function strtolower;
use function substr;
use function ucfirst;

/**
 * The MCP entries a make writes besides a tool (#334): a resource, read by its
 * uri, and a prompt, got with its arguments. Each names its command, its class
 * suffix, its template and the permission it declares.
 */
enum McpEntryKind: string
{
    case Resource = 'resource';
    case Prompt = 'prompt';

    /**
     * @return string The make's command name: `make:mcp-resource` or `make:mcp-prompt`.
     */
    public function command(): string
    {
        return 'make:mcp-' . $this->value;
    }

    /**
     * @return string The class's suffix: `Resource` or `Prompt`.
     */
    public function suffix(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return string The built-in kind whose `*.tpl` it renders: `mcp-resource` or `mcp-prompt`.
     */
    public function scaffold(): string
    {
        return 'mcp-' . $this->value;
    }

    /**
     * @param string $module The module.
     * @param string $class  The entry's class, with its suffix.
     *
     * @return string A resource's uri, `<module>://<kebab name>`, or a prompt's name, `<module>.<kebab name>`.
     */
    public function named(string $module, string $class): string
    {
        return match ($this) {
            self::Resource => self::prefix($module) . '://' . $this->kebab($class),
            self::Prompt => $this->permission($module, $class),
        };
    }

    /**
     * @param string $module The module.
     * @param string $class  The entry's class, with its suffix.
     *
     * @return string The name a resource lists under, its kebab name; a prompt's, `<module>.<kebab name>`.
     */
    public function listed(string $module, string $class): string
    {
        return match ($this) {
            self::Resource => $this->kebab($class),
            self::Prompt => $this->permission($module, $class),
        };
    }

    /**
     * @param string $module The module.
     * @param string $class  The entry's class, with its suffix.
     *
     * @return string The permission it declares with no `--needs`: `<module>.<kebab name>`.
     */
    public function permission(string $module, string $class): string
    {
        return self::prefix($module) . '.' . $this->kebab($class);
    }

    /**
     * @param string $name The entry's uri or name.
     *
     * @return string What the permission it declares, with no `--needs`, lets a caller do.
     */
    public function permissionText(string $name): string
    {
        return match ($this) {
            self::Resource => 'Read the ' . $name . ' resource over MCP.',
            self::Prompt => 'Get the ' . $name . ' prompt over MCP.',
        };
    }

    /**
     * @param string $class The entry's class, with its suffix.
     *
     * @return string The class without its suffix, in kebab case (`ScaffoldGuideResource` → `scaffold-guide`).
     */
    private function kebab(string $class): string
    {
        $stem = substr($class, 0, -strlen($this->suffix()));

        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $stem));
    }

    /**
     * @param string $module The module.
     *
     * @return string Its name as a uri scheme or name prefix: underscores become dashes.
     */
    private static function prefix(string $module): string
    {
        return str_replace('_', '-', $module);
    }
}
