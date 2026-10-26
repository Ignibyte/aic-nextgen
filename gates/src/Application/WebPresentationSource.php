<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Module\Application\WebPresentation;
use Gates\Domain\ThemeBinding;
use InvalidArgumentException;

/** Generate the presentation factory: the template lookup, the public assets and, with a theme, its layouts. */
final readonly class WebPresentationSource
{
    /** The catalog factory for an app with no selected theme. */
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
                return new WebPresentation(new ThemeTemplates(), new Assets());
            }
        }
        PHP;

    /** The selected theme shares the same public catalog as early static delivery. */
    private const string THEMED = <<<'PHP'
        final readonly class Presentation
        {
            /**
             * @throws InvalidArgumentException When compiled asset metadata is invalid.
             *
             * @return WebPresentation The compiled template, asset and layout catalogs.
             */
            public function create(): WebPresentation
            {
                $assets = new Assets();

                return new WebPresentation(new ThemeTemplates(), $assets, new ThemeLayouts($assets));
            }
        }
        PHP;

    /** @param PhpClassFile $phpClassFile Emits checked generated source. */
    public function __construct(private PhpClassFile $phpClassFile = new PhpClassFile())
    {
    }

    /**
     * @param ThemeBinding|null $themeBinding The configured theme, absent for an app with no theme.
     *
     * @throws InvalidArgumentException When generated imports or structure conflict.
     *
     * @return string The inert presentation factory.
     */
    public function file(?ThemeBinding $themeBinding = null): string
    {
        $namespace = 'App\\Generated';
        $classes = [WebPresentation::class, InvalidArgumentException::class, $namespace . '\\ThemeTemplates', $namespace . '\\Assets'];
        $source = $themeBinding === null
            ? self::SOURCE
            : self::THEMED;

        return $this->phpClassFile->file(
            $namespace,
            $classes,
            [],
            $this->phpClassFile->generated('the theme presentation catalogs', 'the inert presentation factory.'),
            $source,
        );
    }
}
