<?php

declare(strict_types=1);

namespace Gates\Tables;

use Gates\Application\QuietFileReader;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

/**
 * Reads one module file and walks it with a visitor, names resolved: the
 * parse step gate:26's table rules share (#185, #228). An unreadable or
 * unparsable file is walked as empty.
 */
final readonly class ModuleFileTraverser
{
    /**
     * @param ParserFactory   $parserFactory   Makes the parser.
     * @param QuietFileReader $quietFileReader Reads each file.
     */
    public function __construct(
        private ParserFactory $parserFactory = new ParserFactory(),
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * @param string      $root        The project's root.
     * @param string      $path        The file, relative to the root.
     * @param NodeVisitor $nodeVisitor What reads it.
     */
    public function traverse(string $root, string $path, NodeVisitor $nodeVisitor): void
    {
        $source = $this->quietFileReader->read($root . '/' . $path) ?? '';
        $statements = $this->parserFactory->createForHostVersion()->parse($source);
        (new NodeTraverser(new NameResolver(), $nodeVisitor))->traverse($statements ?? []);
    }
}
