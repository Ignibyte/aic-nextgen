<?php

declare(strict_types=1);

namespace AIC\Workflow\Parsing;

use AIC\Workflow\Domain\ParsedFile;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

use function str_starts_with;
use function substr;

/**
 * Reads one PHP file into the code index's symbols and edges (#106,
 * droost-engine's `PhpGraphExtractor`): php-parser for the host's PHP, names
 * resolved and parents connected in a pass of their own (a chain's names
 * are read before the walk reaches them, #232), then the declaration, the
 * reference and the call chain visitors over one sink. A file that does not parse
 * gives nothing, and says so.
 */
final readonly class PhpSymbolExtractor
{
    /**
     * @param string $path The file, relative to the project.
     * @param string $area Its area.
     * @param string $code Its content.
     *
     * @return ParsedFile Its symbols and edges.
     */
    public function extract(string $path, string $area, string $code): ParsedFile
    {
        $source = str_starts_with($code, "\xEF\xBB\xBF")
            ? substr($code, 3)
            : $code;

        try {
            $nodes = (new ParserFactory())->createForHostVersion()->parse($source);
        } catch (Error) {
            return new ParsedFile([], [], false);
        }

        $symbolSink = new SymbolSink($path, $area);
        $nameReader = new NameReader();
        $declarationVisitor = new DeclarationVisitor($symbolSink, $nameReader);
        $referenceVisitor = new ReferenceVisitor($symbolSink, $nameReader);
        $callChainVisitor = new CallChainVisitor($symbolSink, $nameReader, new ReceiverTypes($nameReader));
        $resolved = (new NodeTraverser(new NameResolver(), new ParentConnectingVisitor()))->traverse($nodes ?? []);
        (new NodeTraverser($declarationVisitor, $referenceVisitor, $callChainVisitor))->traverse($resolved);

        return new ParsedFile($symbolSink->symbols(), $symbolSink->edges());
    }
}
