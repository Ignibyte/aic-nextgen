<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Html;
use AIC\Component\Domain\RendersMarkup;
use Gates\Domain\TemplateNode;
use Gates\Domain\TemplateNodeKind;

use function array_diff;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function implode;
use function sort;
use function str_contains;
use function str_starts_with;
use function strrpos;
use function substr;

use const SORT_FLAG_CASE;
use const SORT_STRING;

/**
 * The opening of a generated template class (#36): its namespace and its
 * imports, sorted and grouped as PHPCS wants them. The template's `@use`
 * lines, Html, and what the part methods call.
 */
final readonly class TemplateImports
{
    /**
     * The imports a part method's call needs, by the call.
     */
    private const array NEEDS = [
        'array_map(' => ['function array_map', 'function implode'],
        'self::text(' => [RendersMarkup::class],
    ];

    /**
     * @param list<TemplateNode> $nodes     The template's nodes.
     * @param string             $namespace The class's namespace.
     * @param string             $parts     The part methods' source.
     *
     * @return string The file's opening, through its imports.
     */
    public function head(array $nodes, string $namespace, string $parts): string
    {
        $imports = [Html::class, ...$this->imported($nodes)];

        foreach (self::NEEDS as $call => $names) {
            $imports = str_contains($parts, $call)
                ? [...$imports, ...$names]
                : $imports;
        }

        $functions = array_filter($imports, static fn (string $import): bool => str_starts_with($import, 'function '));
        $classes = array_filter(
            array_diff($imports, $functions),
            static fn (string $import): bool => substr($import, 0, (int) strrpos($import, '\\')) !== $namespace,
        );

        return "<?php\n\ndeclare(strict_types=1);\n\nnamespace " . $namespace . ";\n\n" . $this->uses($classes) . $this->uses($functions);
    }

    /**
     * @param list<TemplateNode> $nodes The template's nodes.
     *
     * @return list<string> The classes it imports: those its generated code names.
     */
    private function imported(array $nodes): array
    {
        $imports = [];

        foreach ($nodes as $node) {
            if ($node->kind !== TemplateNodeKind::Import) {
                continue;
            }

            $imports[] = $node->code;
        }

        return $imports;
    }

    /**
     * @param array<int, string> $names Imported names.
     *
     * @return string Their `use` lines, sorted and unique, and a blank line; nothing for none.
     */
    private function uses(array $names): string
    {
        $unique = array_values(array_unique($names));

        if ($unique === []) {
            return '';
        }

        sort($unique, SORT_STRING | SORT_FLAG_CASE);

        return implode('', array_map(static fn (string $name): string => 'use ' . $name . ";\n", $unique)) . "\n";
    }
}
