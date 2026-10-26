<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function lcfirst;
use function strtr;

/**
 * A named page layout (`make:layout`, #157): its page data, with the content region only, and the page template that
 * prints it. The document falls back to the theme's `html.tpl.php`.
 */
final readonly class LayoutSource
{
    /**
     * The page data, each `{name}` standing for the value of the parameter it names.
     */
    private const string DATA = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Theme\Layouts;

        use AIC\Component\Contracts\Component;
        use AIC\Component\Contracts\Region;

        /**
         * The {name} layout's page data, made by bin/aic make:layout (#157): the content region only.
         */
        final readonly class {case}Page implements Component
        {
            /**
             * @param Region $content The page's primary content and its stable target.
             */
            public function __construct(
                public Region $content,
            ) {
            }
        }
        PHP;

    /**
     * The page template: the content region, once.
     */
    private const string PAGE = <<<'PHP'
        <?php /** @var \App\Theme\Layouts\{case}Page ${variable} */ ?>
        <main class="theme-page theme-{name}"><?= ${variable}->content ?></main>
        PHP;

    /**
     * @param string $name The layout's name.
     * @param string $case Its `ThemeLayoutName` case.
     *
     * @return string The page data class's bytes.
     */
    public function data(string $name, string $case): string
    {
        return strtr(self::DATA, ['{case}' => $case, '{name}' => $name]) . "\n";
    }

    /**
     * @param string $name The layout's name.
     * @param string $case Its `ThemeLayoutName` case.
     *
     * @return string The page template's bytes.
     */
    public function page(string $name, string $case): string
    {
        return strtr(self::PAGE, ['{case}' => $case, '{name}' => $name, '{variable}' => lcfirst($case) . 'Page']) . "\n";
    }
}
