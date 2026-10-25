<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

/** A usable site layout service over the selected compiled PageLayouts factory. */
final readonly class ThemeLayoutSource
{
    /** No factory, asset catalog or markup is stored in render data. */
    private const string SOURCE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Site\Service;

        use AIC\Component\Contracts\Component;
        use AIC\Component\Contracts\PageLayouts;
        use AIC\Component\Contracts\Region;
        use AIC\Component\Contracts\Regions;
        use AIC\Config\Domain\AppConfig;
        use AIC\Controller\Domain\PageDocument;
        use InvalidArgumentException;

        /** Makes pages through the selected theme, made by bin/aic make:theme (#148). */
        final readonly class HtmlLayout
        {
            /**
             * @param AppConfig   $appConfig   The application settings.
             * @param PageLayouts $pageLayouts The compiled selected-theme factory.
             */
            public function __construct(
                private AppConfig $appConfig,
                private PageLayouts $pageLayouts,
            ) {
            }

            /**
             * @param Regions $regions The page's filled regions.
             * @param int     $status  200, 404 or 422.
             *
             * @throws InvalidArgumentException When the status or regions are invalid.
             *
             * @return PageDocument The document over those same regions.
             */
            public function page(Regions $regions, int $status): PageDocument
            {
                return new PageDocument($this->pageLayouts->create($this->appConfig->name, $regions), $status);
            }

            /**
             * @param list<Component> $components The content region's components, in order.
             * @param int             $status     200, 404 or 422.
             *
             * @throws InvalidArgumentException When the status is invalid.
             *
             * @return PageDocument The document with its content region filled.
             */
            public function content(array $components, int $status): PageDocument
            {
                return $this->page(new Regions([new Region('content', $components)]), $status);
            }
        }
        PHP;

    /** @return string The authored service, ready for ordinary constructor wiring. */
    public function source(): string
    {
        return self::SOURCE . "\n";
    }
}
