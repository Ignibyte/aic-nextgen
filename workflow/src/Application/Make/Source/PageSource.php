<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function strtr;

/**
 * A GET page (`make:page`): its component in the site's layout (#122).
 */
final readonly class PageSource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace {namespace}\Controller;

        use AIC\Controller\Application\PageDocuments;
        use AIC\Controller\Contracts\Page;
        use AIC\Controller\Contracts\PageResponse;
        use App\Theme\Components\{component}\{component};
        use InvalidArgumentException;
        use Override;
        use Psr\Http\Message\ServerRequestInterface;

        /**
         * GET {path}, made by bin/aic make:page (#122): {component} in the theme's layout.
         */
        final readonly class {class} implements Page
        {
            /**
             * @param PageDocuments $pageDocuments Makes the page.
             */
            public function __construct(
                private PageDocuments $pageDocuments,
            ) {
            }

            /**
             * @param ServerRequestInterface $serverRequest The request; the page is the same for every one.
             *
             * @throws InvalidArgumentException When the page layout is invalid.
             *
             * @return PageResponse The page.
             */
            #[Override]
            public function page(ServerRequestInterface $serverRequest): PageResponse
            {
                return $this->pageDocuments->content([new {component}('{title}')], 200);
            }
        }
        PHP;

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The page.
     * @param string $path      Its path.
     * @param string $component The component it shows.
     * @param string $title     The title it gives the component.
     *
     * @return string The file's bytes.
     */
    public function source(string $namespace, string $class, string $path, string $component, string $title): string
    {
        return strtr(self::TEMPLATE, [
            '{class}' => $class,
            '{component}' => $component,
            '{namespace}' => $namespace,
            '{path}' => $path,
            '{title}' => $title,
        ]) . "\n";
    }
}
