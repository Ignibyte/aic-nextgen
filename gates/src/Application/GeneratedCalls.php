<?php

declare(strict_types=1);

namespace Gates\Application;

/**
 * What a writer of a generated class with built calls uses (#257): the file
 * layout, the code that builds each object, and the exceptions those calls
 * may raise, written as docblock lines.
 */
final readonly class GeneratedCalls
{
    /**
     * @param PhpClassFile           $phpClassFile           Lays out the file.
     * @param CallSource             $callSource             Writes the code that builds each object.
     * @param ConstructionExceptions $constructionExceptions Follows the calls' exception contracts.
     * @param ExceptionDocs          $exceptionDocs          Writes the generated exception documentation.
     */
    public function __construct(
        public PhpClassFile $phpClassFile = new PhpClassFile(),
        public CallSource $callSource = new CallSource(),
        public ConstructionExceptions $constructionExceptions = new ConstructionExceptions(),
        public ExceptionDocs $exceptionDocs = new ExceptionDocs(),
    ) {
    }
}
