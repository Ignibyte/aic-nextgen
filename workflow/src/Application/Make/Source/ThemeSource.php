<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use function lcfirst;
use function strtr;

/** Authored typed region data and restricted templates for the first selected site theme. */
final readonly class ThemeSource
{
    /** The page input has real source types before the first build. */
    private const string DATA = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace App\Site\Component;

        use AIC\Component\Contracts\Component;
        use AIC\Component\Contracts\Region;

        /** The selected theme's authored page data, made by bin/aic make:theme (#148). */
        final readonly class {class} implements Component
        {
            /**
             * @param Region $header  The page header.
             * @param Region $content The primary content and its stable target.
             * @param Region $nav     The navigation and its stable target.
             * @param Region $footer  The page footer.
             */
            public function __construct(
                public Region $header,
                public Region $content,
                public Region $nav,
                public Region $footer,
            ) {
            }
        }
        PHP;

    /** HTML owns document metadata, tracked resources and one transparent page. */
    private const string HTML = <<<'PHP'
        <?php /** @var \AIC\Component\Domain\ThemeLayout $themeLayout */ ?>
        <!doctype html><html lang="en"><head><meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= $themeLayout->name ?></title>
        <meta name="tessera-layout" content="<?= $themeLayout->identity ?>">
        <meta name="tessera-csrf-token" content="<?= $themeLayout->token ?>">
        <meta name="tessera-csrf-header" content="<?= \AIC\Form\Contracts\FormToken::HEADER ?>">
        <?= $themeLayout->trackedAssets ?></head><body>
        <h1><?= $themeLayout->name ?></h1>
        <?= $themeLayout->page ?>
        </body></html>
        PHP;

    /** Every declared Region occurs exactly once. */
    private const string PAGE = <<<'PHP'
        <?php /** @var \App\Site\Component\{class} ${variable} */ ?>
        <header><?= ${variable}->header ?></header>
        <main class="theme-page"><?= ${variable}->content ?></main>
        <nav><?= ${variable}->nav ?></nav>
        <footer><?= ${variable}->footer ?></footer>
        PHP;

    /**
     * @param string $class The page input's short name.
     *
     * @return string Its authored PHP source.
     */
    public function data(string $class): string
    {
        return strtr(self::DATA, ['{class}' => $class]) . "\n";
    }

    /** @return string The authored document template. */
    public function html(): string
    {
        return self::HTML . "\n";
    }

    /**
     * @param string $class The page input's short name.
     *
     * @return string The authored region template.
     */
    public function page(string $class): string
    {
        return strtr(self::PAGE, ['{class}' => $class, '{variable}' => lcfirst($class)]) . "\n";
    }

    /** @return string A real declared stylesheet without a module or favicon assumption. */
    public function css(): string
    {
        return "body { margin: 1rem auto; padding: 0 1rem; max-width: 72rem; font-family: system-ui, sans-serif; }\n"
            . ".theme-page { min-width: 0; }\n";
    }
}
