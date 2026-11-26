<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Mcp\Domain\McpCatalog;
use Gates\Domain\McpModuleSpec;
use InvalidArgumentException;

use function array_chunk;
use function array_flip;
use function array_intersect_key;
use function array_keys;
use function count;
use function implode;
use function max;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_repeat;
use function strlen;
use function strtr;

/**
 * Writes a module's MCP catalog (#316) in as many classes as PHPMD's coupling
 * of 15 needs (#334): `App\Generated\<Module>Mcp` takes its entries and builds
 * their adapters; past seven entries it holds the first six and takes
 * `<Module>McpPart2`, which holds the next six and so on, each part's catalog
 * joined after its own with `McpCatalog::with()`. A module's Api takes
 * `<Module>Mcp` whatever its size.
 */
final readonly class McpCatalogParts
{
    /**
     * The most entries one catalog class holds alone: the catalog and an entry and its adapter for each make 15.
     */
    private const int ALONE = 7;

    /**
     * The most entries a class that takes a next part holds: that part is the fifteenth class.
     */
    private const int CHAINED = 6;

    private const string CATALOG = <<<'PHP'
        final readonly class {class}
        {
            /**
        {params}
             */
            public function __construct(
        {properties}
            ) {
            }

            /**
             * @return McpCatalog {what}
             */
            public function catalog(): McpCatalog
            {
                return {open}new McpCatalog(
                    [{tools}],
                    [{resources}],
                    [{prompts}],
                ){close};
            }
        }
        PHP;

    /**
     * @param PhpClassFile $phpClassFile Emits checked generated source.
     * @param McpCode      $mcpCode      The code pieces.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private McpCode $mcpCode = new McpCode(),
    ) {
    }

    /**
     * @param McpModuleSpec $mcpModuleSpec A module's entries.
     * @param string        $from          Its `src/Mcp/`.
     *
     * @throws InvalidArgumentException When imports conflict or a class passes the coupling.
     *
     * @return array<string, string> `<Module>Mcp.php` and, past seven entries, each `<Module>McpPartN.php`.
     */
    public function files(McpModuleSpec $mcpModuleSpec, string $from): array
    {
        $adapters = $this->adapters($mcpModuleSpec);
        $entries = [...$adapters[0], ...$adapters[1], ...$adapters[2]];
        $chunks = count($entries) <= self::ALONE
            ? [array_keys($entries)]
            : array_chunk(array_keys($entries), self::CHAINED);
        $base = $mcpModuleSpec->moduleDefinition->studlyName() . 'Mcp';
        $files = [];

        foreach ($chunks as $index => $chunk) {
            $class = self::name($base, $index);
            $next = $index + 1 < count($chunks)
                ? self::name($base, $index + 1)
                : '';
            $files[$class . '.php'] = $this->part($class, $next, $adapters, $chunk, $from);
        }

        return $files;
    }

    /**
     * @param string                                                                     $class    The part's class.
     * @param string                                                                     $next     The next part, or '' for the last.
     * @param array{array<string, string>, array<string, string>, array<string, string>} $adapters Each tool's, resource's and
     *                                                                                             prompt's adapter, by entry.
     * @param list<string>                                                               $chunk    The entries this part holds.
     * @param string                                                                     $from     The module's `src/Mcp/`.
     *
     * @throws InvalidArgumentException When imports conflict or the class passes the coupling.
     *
     * @return string The part's file.
     */
    private function part(string $class, string $next, array $adapters, array $chunk, string $from): string
    {
        $held = array_flip($chunk);
        $taken = $next === ''
            ? $chunk
            : [...$chunk, 'App\\Generated\\' . $next];
        $source = strtr(self::CATALOG, [
            '{class}' => $class,
            '{close}' => $next === '' ? '' : ')->with($this->' . $this->mcpCode->variable($next) . '->catalog())',
            '{open}' => $next === '' ? '' : '(',
            '{params}' => implode("\n", $this->params($taken)),
            '{prompts}' => $this->listed(array_intersect_key($adapters[2], $held)),
            '{properties}' => implode("\n", $this->properties($taken)),
            '{resources}' => $this->listed(array_intersect_key($adapters[1], $held)),
            '{tools}' => $this->listed(array_intersect_key($adapters[0], $held)),
            '{what}' => $next === ''
                ? "The module's tools, resources and prompts, each in its adapter."
                : "These entries, each in its adapter, then the next part's.",
        ]);
        $classes = [McpCatalog::class, ...$taken, ...$this->built($adapters, $held)];

        return $this->phpClassFile->file('App\\Generated', $classes, [], $this->docblock($class, $from), $source);
    }

    /**
     * @param string $class The part's class.
     * @param string $from  The module's `src/Mcp/`.
     *
     * @return string Its docblock's lines.
     */
    private function docblock(string $class, string $from): string
    {
        $what = preg_match('/McpPart\\d+$/', $class) === 1
            ? "a part of the module's MCP catalog (#334)."
            : "the module's MCP catalog (#316).";

        return $this->phpClassFile->generated($from, $what)
            . "\n * It is API for the module's MCP endpoint, which a module may add after its tools.\n *\n * @api";
    }

    /**
     * @param array{array<string, string>, array<string, string>, array<string, string>} $adapters Each kind's adapters, by entry.
     * @param array<string, int>                                                         $held     The part's entries, as keys.
     *
     * @return list<string> The adapters the part builds, in `App\Generated`, for the coupling count.
     */
    private function built(array $adapters, array $held): array
    {
        $built = [];

        foreach ($adapters as $kind) {
            foreach (array_intersect_key($kind, $held) as $adapter) {
                $built[] = 'App\\Generated\\' . $adapter;
            }
        }

        return $built;
    }

    /**
     * @param McpModuleSpec $mcpModuleSpec A module's entries.
     *
     * @return array{array<string, string>, array<string, string>, array<string, string>} Each tool's, resource's and
     *                                                                                    prompt's adapter class, by the entry's class.
     */
    private function adapters(McpModuleSpec $mcpModuleSpec): array
    {
        $tools = [];
        $resources = [];
        $prompts = [];

        foreach ($mcpModuleSpec->tools as $mcpToolSpec) {
            $tools[$mcpToolSpec->class] = $this->mcpCode->short($mcpToolSpec->class) . 'Call';
        }

        foreach (array_keys($mcpModuleSpec->resources) as $class) {
            $resources[$class] = $this->mcpCode->short($class) . 'Read';
        }

        foreach (array_keys($mcpModuleSpec->prompts) as $class) {
            $prompts[$class] = $this->mcpCode->short($class) . 'Get';
        }

        return [$tools, $resources, $prompts];
    }

    /**
     * @param list<string> $entries The classes the part takes.
     *
     * @return list<string> The constructor's `@param` lines, aligned.
     */
    private function params(array $entries): array
    {
        $width = 0;

        foreach ($entries as $entry) {
            $width = max($width, strlen($this->mcpCode->short($entry)));
        }

        $params = [];

        foreach ($entries as $entry) {
            $short = $this->mcpCode->short($entry);
            $pad = str_repeat(' ', $width - strlen($short));
            $variable = $this->mcpCode->variable($short);
            $what = str_contains($entry, '\\Generated\\')
                ? 'The next part of the catalog.'
                : 'An entry, for its adapter.';
            $params[] = sprintf('     * @param %s%s $%s%s %s', $short, $pad, $variable, $pad, $what);
        }

        return $params;
    }

    /**
     * @param list<string> $entries The classes the part takes.
     *
     * @return list<string> The constructor's promoted properties.
     */
    private function properties(array $entries): array
    {
        $properties = [];

        foreach ($entries as $entry) {
            $properties[] = sprintf('        private %s $%s,', $this->mcpCode->short($entry), $this->mcpCode->variable($entry));
        }

        return $properties;
    }

    /**
     * @param array<string, string> $adapters Each entry's adapter class, by the entry's class.
     *
     * @return string The adapters, each built with its entry on a line of its own, for a list literal.
     */
    private function listed(array $adapters): string
    {
        $built = [];

        foreach ($adapters as $entry => $adapter) {
            $built[] = '                new ' . $adapter . '($this->' . $this->mcpCode->variable($entry) . '),';
        }

        return $built === []
            ? ''
            : "\n" . implode("\n", $built) . "\n            ";
    }

    /**
     * @param string $base  `<Module>Mcp`.
     * @param int    $index A part's place, from 0.
     *
     * @return string Its class: `<Module>Mcp`, `<Module>McpPart2`, ...
     */
    private static function name(string $base, int $index): string
    {
        return $index === 0
            ? $base
            : $base . 'Part' . ($index + 1);
    }
}
