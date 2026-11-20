<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Mcp\Attribute\PromptInfo;
use Gates\Domain\McpModuleSpec;
use InvalidArgumentException;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function in_array;
use function preg_match;
use function sprintf;
use function str_contains;

/**
 * The rules MCP entries keep across modules (#316): a tool's or prompt's name
 * is lower-case words joined by "." and is the app's only one of that name, a
 * resource's URI has a scheme and is the app's only one, every `needs` is a
 * permission some module declares, and a prompt's arguments are named,
 * described, and its optional ones among them.
 */
final readonly class McpChecks
{
    private const string NAME = '/^[a-z][a-z0-9-]*(\\.[a-z][a-z0-9-]*)+$/';

    private const string ARGUMENT = '/^[a-z][a-z0-9_]*$/';

    /**
     * @param list<McpModuleSpec> $specs       Every module's entries.
     * @param list<string>        $permissions Every declared permission.
     *
     * @throws InvalidArgumentException At the first entry that breaks a rule.
     */
    public function check(array $specs, array $permissions): void
    {
        $seen = [];

        foreach ($specs as $spec) {
            $seen = $this->module($seen, $spec, $permissions);
        }
    }

    /**
     * @param array<string, string> $seen          Each name and URI so far, with its class.
     * @param McpModuleSpec         $mcpModuleSpec A module's entries.
     * @param list<string>          $permissions   Every declared permission.
     *
     * @throws InvalidArgumentException At the first entry that breaks a rule.
     *
     * @return array<string, string> The names and URIs with the module's.
     */
    private function module(array $seen, McpModuleSpec $mcpModuleSpec, array $permissions): array
    {
        foreach ($mcpModuleSpec->tools as $mcpToolSpec) {
            $seen = $this->named($seen, $mcpToolSpec->class, $mcpToolSpec->toolInfo->name, $mcpToolSpec->toolInfo->needs, $permissions);
        }

        foreach ($mcpModuleSpec->resources as $class => $resourceInfo) {
            $uri = str_contains($resourceInfo->uri, '://')
                ? $resourceInfo->uri
                : throw $this->refusal($class, 'its URI needs a scheme (ledger://accounts)');
            $seen = $this->unique($seen, $class, 'uri ' . $uri, $resourceInfo->needs, $permissions);
        }

        foreach ($mcpModuleSpec->prompts as $class => $promptInfo) {
            $this->arguments($class, $promptInfo);
            $seen = $this->named($seen, $class, $promptInfo->name, $promptInfo->needs, $permissions);
        }

        return $seen;
    }

    /**
     * @param array<string, string> $seen        Each name and URI so far, with its class.
     * @param string                $class       A tool or prompt.
     * @param string                $name        Its name.
     * @param string                $needs       Its permission.
     * @param list<string>          $permissions Every declared permission.
     *
     * @throws InvalidArgumentException When the name is misformed or taken, or the permission undeclared.
     *
     * @return array<string, string> The names with this one.
     */
    private function named(array $seen, string $class, string $name, string $needs, array $permissions): array
    {
        if (preg_match(self::NAME, $name) !== 1) {
            throw $this->refusal($class, sprintf('the name "%s" must be lower-case words joined by "." (ledger.split)', $name));
        }

        return $this->unique($seen, $class, 'name ' . $name, $needs, $permissions);
    }

    /**
     * @param array<string, string> $seen        Each name and URI so far, with its class.
     * @param string                $class       An entry.
     * @param string                $key         Its name or URI, with which it is.
     * @param string                $needs       Its permission.
     * @param list<string>          $permissions Every declared permission.
     *
     * @throws InvalidArgumentException When the key is taken or the permission undeclared.
     *
     * @return array<string, string> The keys with this one.
     */
    private function unique(array $seen, string $class, string $key, string $needs, array $permissions): array
    {
        if (array_key_exists($key, $seen)) {
            throw $this->refusal($class, sprintf("its %s is %s's already", $key, $seen[$key]));
        }

        if (!in_array($needs, $permissions, true)) {
            throw $this->refusal($class, sprintf('it needs "%s", which no module declares with ->permission()', $needs));
        }

        $seen[$key] = $class;

        return $seen;
    }

    /**
     * @param string     $class      A prompt.
     * @param PromptInfo $promptInfo Its attribute.
     *
     * @throws InvalidArgumentException When an argument is misnamed or undescribed, or an optional one is not among them.
     */
    private function arguments(string $class, PromptInfo $promptInfo): void
    {
        foreach ($promptInfo->arguments as $name => $description) {
            if (preg_match(self::ARGUMENT, $name) !== 1 || $description === '') {
                throw $this->refusal($class, sprintf('the argument "%s" must be a lower-case name with a description', $name));
            }
        }

        if (array_diff($promptInfo->optional, array_keys($promptInfo->arguments)) !== []) {
            throw $this->refusal($class, 'each optional argument must be one of its arguments');
        }
    }

    /**
     * @param string $class The entry.
     * @param string $why   What is wrong.
     *
     * @return InvalidArgumentException The refusal.
     */
    private function refusal(string $class, string $why): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('%s: %s (#316).', $class, $why));
    }
}
