<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function strtr;

/**
 * A GET page (`make:page`): its component in the theme's layout (#122), or in a named one (#157).
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
        {layoutUse}use App\Theme\Components\{component}\{component};
        use InvalidArgumentException;
        use Override;
        use Psr\Http\Message\ServerRequestInterface;

        /**
         * GET {path}, made by bin/aic make:page (#122): {component} in the theme's {layoutName}layout.
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
                return $this->pageDocuments->content(
                    [new {component}('{title}')],
                    200,
                    '{title}',{layoutArgument}
                );
            }
        }
        PHP;

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The page.
     * @param string $path      Its path.
     * @param string $component The component it shows.
     * @param string $title     The title it gives the component.
     * @param string $case      The `ThemeLayoutName` case of the layout it picks; empty for the theme's own.
     *
     * @return string The file's bytes.
     */
    public function source(
        string $namespace,
        string $class,
        string $path,
        string $component,
        string $title,
        string $case = '',
    ): string {
        $named = $case !== '';

        return strtr(self::TEMPLATE, [
            '{class}' => $class,
            '{component}' => $component,
            '{layoutArgument}' => $named
                ? "\n            ThemeLayoutName::" . $case . ','
                : '',
            '{layoutName}' => $named
                ? $case . ' '
                : '',
            '{layoutUse}' => $named
                ? "use App\\Generated\\ThemeLayoutName;\n"
                : '',
            '{namespace}' => $namespace,
            '{path}' => $path,
            '{title}' => $title,
        ]) . "\n";
    }
}
