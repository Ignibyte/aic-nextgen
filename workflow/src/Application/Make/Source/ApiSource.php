<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function strtr;

/**
 * An API (`make:api`): its body as JSON (#122).
 */
final readonly class ApiSource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace {namespace}\Controller;

        use AIC\Controller\Contracts\Api;
        use AIC\Controller\Contracts\ApiResponse;
        use AIC\Controller\Domain\JsonResponse;
        use {namespace}\Service\{body};
        use Override;
        use Psr\Http\Message\ServerRequestInterface;

        /**
         * GET {path}, made by bin/aic make:api (#122): {body} as JSON.
         */
        final readonly class {class} implements Api
        {
            /**
             * @param ServerRequestInterface $serverRequest The request.
             *
             * @return ApiResponse The body.
             */
            #[Override]
            public function api(ServerRequestInterface $serverRequest): ApiResponse
            {
                return new JsonResponse(new {body}('{module}'));
            }
        }
        PHP;

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The API.
     * @param string $path      Its path.
     * @param string $body      Its body's class.
     * @param string $module    The module.
     *
     * @return string The file's bytes.
     */
    public function source(string $namespace, string $class, string $path, string $body, string $module): string
    {
        return strtr(self::TEMPLATE, [
            '{body}' => $body,
            '{class}' => $class,
            '{module}' => $module,
            '{namespace}' => $namespace,
            '{path}' => $path,
        ]) . "\n";
    }
}
