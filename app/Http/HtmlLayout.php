<?php

declare(strict_types=1);

namespace App\Http;

use AIC\Config\Domain\AppConfig;

use function htmlspecialchars;

/**
 * The app's HTML document around a page's body, titled with the app's name.
 */
final readonly class HtmlLayout
{
    /**
     * @param AppConfig $appConfig The app's settings.
     */
    public function __construct(
        private AppConfig $appConfig,
    ) {
    }

    /**
     * @param string $body The page's body markup, already escaped.
     *
     * @return string The whole document.
     */
    public function document(string $body): string
    {
        $name = htmlspecialchars($this->appConfig->name);

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . $name . '</title></head>'
            . '<body><h1>' . $name . '</h1>' . $body . '</body></html>';
    }
}
