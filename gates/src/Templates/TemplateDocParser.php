<?php

declare(strict_types=1);

namespace Gates\Templates;

use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use ReflectionMethod;
use ReflectionProperty;

use function array_find;
use function is_string;

/**
 * Reads a promoted property's docblock type for the build (#97): the
 * constructor's `@param` of the same name, which is where a component, a
 * class of promoted props, writes `list<T>`.
 */
final readonly class TemplateDocParser
{
    /**
     * Reads the docblocks.
     */
    private PhpDocParser $phpDocParser;

    /**
     * Splits a docblock into tokens.
     */
    private Lexer $lexer;

    /**
     * Build the docblock reader.
     */
    public function __construct()
    {
        $parserConfig = new ParserConfig([]);
        $constExprParser = new ConstExprParser($parserConfig);
        $this->phpDocParser = new PhpDocParser($parserConfig, new TypeParser($parserConfig, $constExprParser), $constExprParser);
        $this->lexer = new Lexer($parserConfig);
    }

    /**
     * @param ReflectionProperty $reflectionProperty A property.
     *
     * @return TypeNode|null The promoting constructor's `@param` type; null when it is not promoted or has none.
     */
    public function promotedType(ReflectionProperty $reflectionProperty): ?TypeNode
    {
        $constructor = $reflectionProperty->getDeclaringClass()->getConstructor();
        $docComment = $reflectionProperty->isPromoted() && $constructor instanceof ReflectionMethod
            ? $constructor->getDocComment()
            : false;

        if (!is_string($docComment)) {
            return null;
        }

        $phpDocNode = $this->phpDocParser->parse(new TokenIterator($this->lexer->tokenize($docComment)));
        $parameter = '$' . $reflectionProperty->getName();

        $paramTagValueNode = array_find(
            $phpDocNode->getParamTagValues(),
            static fn (ParamTagValueNode $paramTagValueNode): bool => $paramTagValueNode->parameterName === $parameter,
        );

        return $paramTagValueNode instanceof ParamTagValueNode
            ? $paramTagValueNode->type
            : null;
    }
}
