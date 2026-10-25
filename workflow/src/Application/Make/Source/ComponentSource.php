<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function strtr;

/**
 * A component (`make:component`, and a page's own view): one promoted `title` (#122).
 */
final readonly class ComponentSource
{
    /**
     * The file, each `{name}` standing for the value of the parameter it names.
     */
    private const string TEMPLATE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Theme\Components\{class};

        use AIC\Component\Contracts\Component;

        /**
         * {class}, made by bin/aic {made}: its title, shown by its template.
         */
        final readonly class {class} implements Component
        {
            /**
             * @param string $title What it shows.
             */
            public function __construct(
                public string $title,
            ) {
            }
        }
        PHP;

    /**
     * @param string $class The class.
     * @param string $made  The make that made it, such as `make:component (#122)`.
     *
     * @return string The file's bytes.
     */
    public function source(string $class, string $made): string
    {
        return strtr(self::TEMPLATE, ['{class}' => $class, '{made}' => $made]) . "\n";
    }
}
