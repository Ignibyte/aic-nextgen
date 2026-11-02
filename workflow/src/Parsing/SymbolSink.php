<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use AIC\Workflow\Domain\CodeEdge;
use AIC\Workflow\Domain\CodeSymbol;

use function array_pop;
use function array_values;
use function end;

/**
 * What one file's walk collects, and where the walk is (#106): its symbols,
 * its edges (one per source, target and kind), and the class-like and
 * method it is inside. The two visitors share it; it accumulates as the walk
 * goes, so it is not readonly.
 */
final class SymbolSink
{
    /**
     * @var list<CodeSymbol> The symbols so far.
     */
    private array $symbols = [];

    /**
     * @var array<string, CodeEdge> The edges so far.
     */
    private array $edges = [];

    /**
     * @var list<string> The class-likes the walk is inside, innermost last; empty for an anonymous class.
     */
    private array $classes = [];

    /**
     * The method the walk is inside; empty outside one.
     */
    private string $method = '';

    /**
     * @param string $path The file.
     * @param string $area Its area.
     */
    public function __construct(
        private readonly string $path,
        private readonly string $area,
    ) {
    }

    /**
     * @param string $class A class-like the walk enters; empty for an anonymous one.
     */
    public function enterClass(string $class): void
    {
        $this->classes[] = $class;
    }

    /**
     * The walk leaves a class-like.
     */
    public function leaveClass(): void
    {
        array_pop($this->classes);
    }

    /**
     * @param string $method A method's full name the walk enters; empty when it leaves one.
     */
    public function method(string $method): void
    {
        $this->method = $method;
    }

    /**
     * @return string The class-like the walk is inside; empty outside one or in an anonymous one.
     */
    public function currentClass(): string
    {
        $class = end($this->classes);

        return $class === false
            ? ''
            : $class;
    }

    /**
     * @param string $name    A symbol's full name.
     * @param string $kind    Its kind.
     * @param int    $line    Its line.
     * @param string $returns A method's declared return class; empty otherwise (#232).
     * @param bool   $public  Whether it is a public method (#232).
     */
    public function symbol(string $name, string $kind, int $line, string $returns = '', bool $public = false): void
    {
        if ($name === '') {
            return;
        }

        $this->symbols[] = new CodeSymbol($name, $kind, $this->path, $line, $this->area, '', $returns, $public);
    }

    /**
     * @param string $target What the current method, class or file names.
     * @param string $kind   How.
     */
    public function edge(string $target, string $kind): void
    {
        $class = $this->currentClass();
        $source = $this->method !== ''
            ? $this->method
            : ($class === '' ? $this->path : $class);

        if ($target === '' || $target === $source) {
            return;
        }

        $this->edges[$source . "\0" . $target . "\0" . $kind] = new CodeEdge($source, $target, $kind, $this->path);
    }

    /**
     * @return list<CodeSymbol> The file's symbols.
     */
    public function symbols(): array
    {
        return $this->symbols;
    }

    /**
     * @return list<CodeEdge> The file's edges.
     */
    public function edges(): array
    {
        return array_values($this->edges);
    }
}
