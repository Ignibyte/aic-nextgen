<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function strtr;

/**
 * An API's body (`make:api`): the module that answers (#122).
 */
final readonly class ApiBodySource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace {namespace}\Service;

        use JsonSerializable;
        use Override;

        /**
         * The body of {api}, made by bin/aic make:api (#122).
         */
        final readonly class {class} implements JsonSerializable
        {
            /**
             * @param string $module The module that answers.
             */
            public function __construct(
                private string $module,
            ) {
            }

            /**
             * @return array<string, string> The JSON object.
             */
            #[Override]
            public function jsonSerialize(): array
            {
                return ['module' => $this->module];
            }
        }
        PHP;

    /**
     * @param string $namespace The module's namespace.
     * @param string $class     The body.
     * @param string $api       The API it is the body of.
     *
     * @return string The file's bytes.
     */
    public function source(string $namespace, string $class, string $api): string
    {
        return strtr(self::TEMPLATE, ['{namespace}' => $namespace, '{class}' => $class, '{api}' => $api]) . "\n";
    }
}
