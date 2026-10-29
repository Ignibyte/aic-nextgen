<?php

declare(strict_types=1);

namespace Gates\Application;

use Gates\Domain\Aggregate;
use Gates\Domain\WiringRoots;
use InvalidArgumentException;
use Throwable;

use function array_diff;
use function array_map;
use function array_reverse;
use function count;
use function implode;
use function in_array;
use function max;
use function strtr;

/**
 * Writes a generated aggregator that merges the modules' results (#93): the
 * root and its chained parts (`ChainSource`), each calling its members and the
 * next part and returning `[]`, the one result, or their `array_merge`. A part
 * takes the context only when one of its members, or a later part's, reads it.
 */
final readonly class MergeSource
{
    /**
     * The class: `{class}`, `{constructor}`, `{method}`, `{returnDoc}`, `{what}` and `{return}`.
     */
    private const string TEMPLATE = <<<'PHP'
        final readonly class {class}
        {
        {constructor}    /**
        {throws}     * @return {returnDoc} {what}
             */
            public function {method}(): array
            {
                {return}
            }
        }
        PHP;

    /**
     * The constructor of a part that passes its members a context: `{context}` and `{property}`.
     */
    private const string CONSTRUCTOR = <<<'PHP'
            /**
             * @param {context} ${property} The roots.
             */
            public function __construct(
                private {context} ${property},
            ) {
            }
        PHP;

    /**
     * @param PhpClassFile  $phpClassFile  Lays out each file.
     * @param ChainSource   $chainSource   Splits the aggregator into parts.
     * @param ExceptionDocs $exceptionDocs Reads the members' generated exception contracts.
     */
    public function __construct(
        private PhpClassFile $phpClassFile = new PhpClassFile(),
        private ChainSource $chainSource = new ChainSource(),
        private ExceptionDocs $exceptionDocs = new ExceptionDocs(),
    ) {
    }

    /**
     * @param Aggregate             $aggregate The aggregator.
     * @param list<string>          $members   The modules' generated classes of its kind, in build order.
     * @param list<string>          $rootless  The generated classes that take no context.
     * @param array<string, string> $sources   The freshly generated member classes.
     *
     * @throws InvalidArgumentException Never: each part fits by its size.
     *
     * @return array<string, string> The root and its parts, by file name.
     */
    public function aggregate(Aggregate $aggregate, array $members, array $rootless, array $sources): array
    {
        $files = [];
        $docblock = $this->phpClassFile->generated('the module manifests', $aggregate->what);
        $size = max(1, $aggregate->partSize() - count($this->exceptions($members, $sources)));
        $parts = $this->chainSource->parts($aggregate->root, $members, $size, $size);
        $rooted = $aggregate->roots instanceof WiringRoots
            ? [...$this->rootedParts($aggregate, $parts, $rootless), ...array_diff($members, $rootless)]
            : [];

        foreach (array_reverse($parts) as $part) {
            $files[$part['class'] . '.php'] = $this->part($aggregate, $part, $rooted, $docblock, [...$sources, ...$files]);
        }

        return $files;
    }

    /**
     * @param Aggregate    $aggregate The aggregator.
     * @param list<string> $members   The modules' generated classes of its kind.
     * @param list<string> $rootless  The generated classes that take no context.
     *
     * @return bool Whether its root takes the context: some member reads it.
     */
    public function rooted(Aggregate $aggregate, array $members, array $rootless): bool
    {
        return $aggregate->roots instanceof WiringRoots && array_diff($members, $rootless) !== [];
    }

    /**
     * @param Aggregate                                                       $aggregate The aggregator.
     * @param list<array{class: string, members: list<string>, next: string}> $parts     Its parts, root first.
     * @param list<string>                                                    $rootless  The generated classes that
     *                                                                                   take no context.
     *
     * @return list<string> The parts that take the context: those with a member, or a later part, that reads it.
     */
    private function rootedParts(Aggregate $aggregate, array $parts, array $rootless): array
    {
        $rooted = [];
        $members = [];

        foreach (array_reverse($parts) as $part) {
            $members = [...$members, ...$part['members']];

            if (!$this->rooted($aggregate, $members, $rootless)) {
                continue;
            }

            $rooted[] = $part['class'];
        }

        return $rooted;
    }

    /**
     * @param Aggregate                                                 $aggregate The aggregator.
     * @param array{class: string, members: list<string>, next: string} $part      One of its parts.
     * @param list<string>                                              $rooted    The parts and members that take the
     *                                                                             context.
     * @param string                                                    $docblock  Its class docblock.
     * @param array<string, string>                                     $sources   The members and already generated later parts.
     *
     * @throws InvalidArgumentException Never: each part fits by its size.
     *
     * @return string The part's class file.
     */
    private function part(Aggregate $aggregate, array $part, array $rooted, string $docblock, array $sources): string
    {
        $calls = $this->chainSource->calls($part);
        $exceptions = $this->exceptions($calls, $sources);
        $roots = in_array($part['class'], $rooted, true)
            ? $aggregate->roots
            : null;
        $body = strtr(self::TEMPLATE, [
            '{class}' => $part['class'],
            '{constructor}' => $this->constructor($roots),
            '{method}' => $aggregate->method,
            '{returnDoc}' => $aggregate->returnDoc,
            '{return}' => $this->returnStatement($calls, $aggregate, $rooted),
            '{throws}' => $this->exceptionDocs->block($exceptions),
            '{what}' => $aggregate->what,
        ]);
        $context = $roots instanceof WiringRoots
            ? [$roots->context]
            : [];
        $classes = [...$context, ...$aggregate->docTypes, ...$this->chainSource->qualified($calls), ...$exceptions];

        return $this->phpClassFile->file('App\\Generated', $classes, count($calls) > 1 ? ['array_merge'] : [], $docblock, $body);
    }

    /**
     * @param list<string>          $calls   The called generated classes.
     * @param array<string, string> $sources Their current generated sources.
     *
     * @throws InvalidArgumentException When a generated exception contract is invalid.
     *
     * @return list<class-string<Throwable>> Their combined exception contracts.
     */
    private function exceptions(array $calls, array $sources): array
    {
        $types = [];

        foreach ($calls as $call) {
            $types = [...$types, ...$this->exceptionDocs->source($sources[$call . '.php'] ?? '')];
        }

        return $this->exceptionDocs->normalized($types);
    }

    /**
     * @param WiringRoots|null $wiringRoots The context the part passes on, if any.
     *
     * @return string The constructor holding it; empty when there is none.
     */
    private function constructor(?WiringRoots $wiringRoots): string
    {
        if (!$wiringRoots instanceof WiringRoots) {
            return '';
        }

        $context = $this->phpClassFile->short($wiringRoots->context);

        return strtr(self::CONSTRUCTOR, ['{context}' => $context, '{property}' => $wiringRoots->property]) . "\n\n";
    }

    /**
     * @param list<string> $calls     The classes the part calls: its members, then the next part.
     * @param Aggregate    $aggregate The aggregator.
     * @param list<string> $rooted    The classes that take the context.
     *
     * @return string The method's return: none, the one result, or the results merged.
     */
    private function returnStatement(array $calls, Aggregate $aggregate, array $rooted): string
    {
        $argument = $aggregate->roots instanceof WiringRoots
            ? '$this->' . $aggregate->roots->property
            : '';
        $method = $aggregate->method;
        $results = array_map(
            static fn (string $class): string => '(new ' . $class . '(' . (in_array($class, $rooted, true) ? $argument : '') . '))->'
                . $method . '()',
            $calls,
        );

        return match (count($results)) {
            0 => 'return [];',
            1 => 'return ' . $results[0] . ';',
            default => "return array_merge(\n            " . implode(",\n            ", $results) . ",\n        );",
        };
    }
}
