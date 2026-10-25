<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function implode;
use function str_replace;
use function strcasecmp;
use function strtr;
use function usort;

/**
 * A GET page (`make:page`): its component in the site's layout (#122).
 */
final readonly class PageSource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names, and
     * `{appUses}` for its two `App\` imports in the order Pint keeps.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace {namespace}\Controller;

        use AIC\Controller\Contracts\Page;
        use AIC\Controller\Contracts\PageResponse;
        {appUses}
        use InvalidArgumentException;
        use Override;
        use Psr\Http\Message\ServerRequestInterface;

        /**
         * GET {path}, made by bin/aic make:page (#122): {component} in the site's layout.
         */
        final readonly class {class} implements Page
        {
            /**
             * @param HtmlLayout $htmlLayout Makes the page.
             */
            public function __construct(
                private HtmlLayout $htmlLayout,
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
                return $this->htmlLayout->content([new {component}('{title}')], 200);
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
        $uses = ['use App\\Theme\\Components\\' . $component . '\\' . $component . ';', 'use App\\Site\\Service\\HtmlLayout;'];
        usort(
            $uses,
            static fn (string $left, string $right): int => strcasecmp(str_replace('\\', ' ', $left), str_replace('\\', ' ', $right)),
        );

        return strtr(self::TEMPLATE, [
            '{appUses}' => implode("\n", $uses),
            '{class}' => $class,
            '{component}' => $component,
            '{namespace}' => $namespace,
            '{path}' => $path,
            '{title}' => $title,
        ]) . "\n";
    }
}
