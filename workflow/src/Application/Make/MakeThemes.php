<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Make\Source\ThemeLayoutSource;
use AIC\Workflow\Application\Make\Source\ThemeSource;
use AIC\Workflow\Domain\Make\MadeFile;

/** Plan every authored file for one first-theme make before any write occurs. */
final readonly class MakeThemes
{
    /**
     * @param ThemeSource       $themeSource       Writes the page data, the layouts and the CSS.
     * @param ThemeLayoutSource $themeLayoutSource Writes the site module's layout service.
     */
    public function __construct(
        private ThemeSource $themeSource = new ThemeSource(),
        private ThemeLayoutSource $themeLayoutSource = new ThemeLayoutSource(),
    ) {
    }

    /**
     * @param string $name The theme's folder name.
     *
     * @return list<MadeFile> The theme's four files and the site's layout service; MakeRun adds the edited site
     *                        manifest last.
     */
    public function files(string $name): array
    {
        $theme = 'themes/' . $name . '/';

        return [
            new MadeFile('modules/site/src/Service/HtmlLayout.php', $this->themeLayoutSource->source()),
            new MadeFile($theme . 'layouts/Page.php', $this->themeSource->data()),
            new MadeFile($theme . 'layouts/html.tpl.php', $this->themeSource->html()),
            new MadeFile($theme . 'layouts/page.tpl.php', $this->themeSource->page()),
            new MadeFile($theme . 'assets/theme.css', $this->themeSource->css()),
        ];
    }
}
