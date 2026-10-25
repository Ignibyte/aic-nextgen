<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Application\WebPresentation;
use InvalidArgumentException;

/** Generates the small typed factory for the application's compiled presentation catalogs. */
final readonly class WebPresentationSource
{
    /** The catalogs have no user providers or factories and are safe to construct before dynamic assembly. */
    private const string SOURCE = <<<'PHP'
        final readonly class Presentation
        {
            /**
             * @throws InvalidArgumentException When compiled asset metadata is invalid.
             *
             * @return WebPresentation The compiled template and public asset catalogs.
             */
            public function create(): WebPresentation
            {
                return new WebPresentation(new ModuleTemplates(), new Assets());
            }
        }
        PHP;

    /** @param PhpClassFile $phpClassFile Writes the standard generated header and checked imports. */
    public function __construct(private PhpClassFile $phpClassFile = new PhpClassFile())
    {
    }

    /**
     * @throws InvalidArgumentException When the generated class cannot satisfy the structural rules.
     *
     * @return string The complete generated factory source.
     */
    public function file(): string
    {
        $namespace = 'App\\Generated';
        $classes = [WebPresentation::class, InvalidArgumentException::class, $namespace . '\\ModuleTemplates', $namespace . '\\Assets'];
        $docblock = $this->phpClassFile->generated('the module presentation catalogs', 'the inert presentation factory.');

        return $this->phpClassFile->file($namespace, $classes, [], $docblock, self::SOURCE);
    }
}
