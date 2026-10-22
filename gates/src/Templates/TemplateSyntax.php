<?php

declare(strict_types=1);

namespace Gates\Templates;

use PhpParser\Error;
use PhpParser\Node\Stmt;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\Token;

use function array_values;

/**
 * The parser's front end for templates (#96): php-parser for the host's PHP,
 * giving the statements and the tokens the checks read. #97's build reuses it.
 */
final readonly class TemplateSyntax
{
    /**
     * The parser.
     */
    private Parser $parser;

    /**
     * Build the parser for the host's PHP version.
     */
    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForHostVersion();
    }

    /**
     * @param string $source A template's source.
     *
     * @throws Error When it is not valid PHP.
     *
     * @return array{list<Stmt>, list<Token>} Its statements and its tokens.
     */
    public function parse(string $source): array
    {
        $stmts = $this->parser->parse($source) ?? [];

        return [array_values($stmts), array_values($this->parser->getTokens())];
    }
}
