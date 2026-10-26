<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Make\Source\ThemeSource;
use AIC\Workflow\Domain\Make\MadeFile;

/** Plan every authored file for one first-theme make before any write occurs. */
final readonly class MakeThemes
{
    /** @param ThemeSource $themeSource Writes the page data, the layouts and the CSS. */
    public function __construct(private ThemeSource $themeSource = new ThemeSource())
    {
    }

    /**
     * @param string $name The theme's folder name.
     *
     * @return list<MadeFile> The theme's four files.
     */
    public function files(string $name): array
    {
        $theme = 'themes/' . $name . '/';

        return [
            new MadeFile($theme . 'layouts/Page.php', $this->themeSource->data()),
            new MadeFile($theme . 'layouts/html.phtml', $this->themeSource->html()),
            new MadeFile($theme . 'layouts/page.phtml', $this->themeSource->page()),
            new MadeFile($theme . 'assets/theme.css', $this->themeSource->css()),
        ];
    }
}
