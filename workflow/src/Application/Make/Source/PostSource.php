<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function strtr;

/**
 * A POST page (`make:page --post`): a 303 back to its path (#122).
 */
final readonly class PostSource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace {namespace}\Controller;

        use AIC\Controller\Contracts\Page;
        use AIC\Controller\Contracts\PageResponse;
        use AIC\Controller\Domain\PageRedirect;
        use InvalidArgumentException;
        use Override;
        use Psr\Http\Message\ServerRequestInterface;

        /**
         * POST {path}, made by bin/aic make:page --post (#122): back to {path}.
         */
        final readonly class {class} implements Page
        {
            /**
             * @param ServerRequestInterface $serverRequest The request.
             *
             * @throws InvalidArgumentException When the redirect location is invalid.
             *
             * @return PageResponse 303 to {path}.
             */
            #[Override]
            public function page(ServerRequestInterface $serverRequest): PageResponse
            {
                return new PageRedirect('{path}');
            }
        }
        PHP;

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The page.
     * @param string $path      Its path.
     *
     * @return string The file's bytes.
     */
    public function source(string $namespace, string $class, string $path): string
    {
        return strtr(self::TEMPLATE, ['{namespace}' => $namespace, '{class}' => $class, '{path}' => $path]) . "\n";
    }
}
