<?php

declare(strict_types=1);

namespace Gates\Application;

use AIC\Component\Contracts\Component;
use AIC\Component\Domain\ThemeLayout;
use Gates\Domain\TemplateBinding;
use Gates\Domain\TemplateRole;
use Gates\Domain\ThemeBinding;
use InvalidArgumentException;

use function array_diff;
use function implode;
use function in_array;
use function is_dir;
use function preg_match;

/**
 * Finds the configured theme (#153): `config/app.php` names a folder under `themes/` holding `layouts/` (the
 * document, the page and the page's region data), `components/` and `assets/`. The build reads only that folder;
 * nothing reads `themes/` while serving.
 */
final readonly class ThemeBuild
{
    /** The page region data class every theme declares in `layouts/Page.php`. */
    public const string PAGE = self::LAYOUTS_NAMESPACE . 'Page';

    /** The namespace of a theme's layouts folder. */
    private const string LAYOUTS_NAMESPACE = 'App\\Theme\\Layouts\\';

    /** The entries a theme folder may hold. */
    private const array ENTRIES = ['assets', 'components', 'layouts'];

    /**
     * @param string          $root            The project folder.
     * @param ThemePageData   $themePageData   Reads the page's regions.
     * @param ThemeChecks     $themeChecks     Checks the theme's autoload lines and PHPCS roots.
     * @param QuietFileReader $quietFileReader Reads composer.json and phpcs.xml.dist.
     */
    public function __construct(
        private string $root,
        private ThemePageData $themePageData = new ThemePageData(),
        private ThemeChecks $themeChecks = new ThemeChecks(),
        private QuietFileReader $quietFileReader = new QuietFileReader(),
    ) {
    }

    /**
     * @param string|null $theme The theme `config/app.php` names; null for an app with no HTML.
     *
     * @throws InvalidArgumentException When the theme is absent or a folder, file, class or line breaks its layout.
     *
     * @return ThemeBinding|null The theme, null when the app names none.
     */
    public function read(?string $theme): ?ThemeBinding
    {
        if ($theme === null) {
            return null;
        }

        $themeComponents = new ThemeComponents($this->root);
        $this->folder($theme, $themeComponents);
        $this->lines($theme);

        $page = $this->themePageData->page(self::PAGE);
        $regions = $this->themePageData->regions($page);
        $layouts = $themeComponents->entries('themes/' . $theme . '/layouts');

        return new ThemeBinding(
            $theme,
            $regions,
            $this->layout($theme, 'html.phtml', ThemeLayout::class, TemplateRole::Html),
            $this->layout($theme, 'page.phtml', $page, TemplateRole::Page),
            $themeComponents->bindings($theme),
            (new ThemeLayoutFiles($this->root))->variants($theme, $layouts, $regions),
        );
    }

    /**
     * @param string          $theme           The configured theme.
     * @param ThemeComponents $themeComponents Lists the theme's folders.
     *
     * @throws InvalidArgumentException When the name, the folder or its layouts break the theme layout.
     */
    private function folder(string $theme, ThemeComponents $themeComponents): void
    {
        $folder = 'themes/' . $theme;

        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*\\z/', $theme) !== 1) {
            throw new InvalidArgumentException($theme . ': a theme is named in lowercase letters, digits and single hyphens.');
        }

        if (!is_dir($this->root . '/' . $folder)) {
            throw new InvalidArgumentException('config/app.php names the theme ' . $theme . ', but ' . $folder . '/ does not exist.');
        }

        $entries = $themeComponents->entries($folder);
        $stray = array_diff($entries, self::ENTRIES);

        if ($stray !== [] || !in_array('layouts', $entries, true)) {
            throw new InvalidArgumentException(
                $folder . ': a theme holds layouts/, components/ and assets/ only; found ' . implode(', ', $entries) . '.',
            );
        }

        if (array_diff(ThemeLayoutFiles::DEFAULTS, $themeComponents->entries($folder . '/layouts')) !== []) {
            throw new InvalidArgumentException($folder . '/layouts: holds html.phtml, page.phtml and Page.php.');
        }
    }

    /**
     * @param string $theme The configured theme.
     *
     * @throws InvalidArgumentException When composer.json or phpcs.xml.dist lacks one of the theme's lines.
     */
    private function lines(string $theme): void
    {
        $this->themeChecks->check(
            $theme,
            $this->quietFileReader->read($this->root . '/composer.json') ?? '',
            $this->quietFileReader->read($this->root . '/phpcs.xml.dist') ?? '',
        );
    }

    /**
     * @param string                  $theme        The theme.
     * @param string                  $file         The layout's file in `layouts/`.
     * @param class-string<Component> $component    The data it reads.
     * @param TemplateRole            $templateRole Its transparent rendering role.
     *
     * @return TemplateBinding Its template binding.
     */
    private function layout(string $theme, string $file, string $component, TemplateRole $templateRole): TemplateBinding
    {
        return new TemplateBinding(
            $component,
            'themes/' . $theme . '/layouts/' . $file,
            'generated/templates/themes/' . $theme . '/layouts/' . $file,
            $templateRole,
        );
    }
}
