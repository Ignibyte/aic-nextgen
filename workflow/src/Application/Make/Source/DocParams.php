<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function array_column;
use function array_map;
use function max;
use function str_pad;

/**
 * A made constructor's `@param` lines, padded into columns as Pint's
 * `phpdoc_align` pads them (#322, #323), for a docblock whose class names
 * follow the made name.
 */
final readonly class DocParams
{
    /**
     * @param non-empty-list<array{string, string, string}> $rows Each parameter's class, variable and description.
     *
     * @return string The lines, each ending in a newline.
     */
    public function lines(array $rows): string
    {
        $type = max(array_map(strlen(...), array_column($rows, 0)));
        $name = max(array_map(strlen(...), array_column($rows, 1)));
        $params = '';

        foreach ($rows as [$class, $variable, $what]) {
            $params .= '     * @param ' . str_pad($class, $type) . ' ' . str_pad($variable, $name) . ' ' . $what . "\n";
        }

        return $params;
    }
}
