<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\LayoutSource;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Exception\WorkflowRefusal;

use function preg_match;
use function str_replace;
use function strlen;
use function ucwords;

/**
 * A theme's named layouts for the makes (#157), by the build's rules (#154): a name is lower case words joined by
 * single hyphens, its `ThemeLayoutName` case is the words in PascalCase, and it ships as `page--<name>.phtml`,
 * `html--<name>.phtml` or both in the theme's `layouts/`.
 */
final readonly class MakeLayouts
{
    /**
     * @param MakeTheme    $makeTheme    The theme the layout goes in.
     * @param LayoutSource $layoutSource Writes its page data and page template.
     */
    public function __construct(
        private MakeTheme $makeTheme,
        private LayoutSource $layoutSource = new LayoutSource(),
    ) {
    }

    /**
     * @param string $name A layout's name.
     *
     * @throws WorkflowRefusal When it is not lower case words joined by single hyphens, is `class`, or passes 32 bytes.
     *
     * @return string It.
     */
    public function name(string $name): string
    {
        if (preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*\\z/', $name) !== 1 || $name === 'class' || strlen($name) > 32) {
            throw new WorkflowRefusal(
                'the layout "' . $name . '" must be lower case words joined by single hyphens, at most 32 bytes, not "class"',
                ConsoleArguments::USAGE,
            );
        }

        return $name;
    }

    /**
     * @param string $name A layout's name.
     *
     * @return string Its `ThemeLayoutName` case: `two-col` gives `TwoCol`.
     */
    public function case(string $name): string
    {
        return str_replace('-', '', ucwords($name, '-'));
    }

    /**
     * @param string $layout What `--layout` gave; empty when the page keeps the theme's own layout.
     *
     * @throws WorkflowRefusal When the name breaks a rule, or the app names no theme.
     *
     * @return array{string, list<string>} Its `ThemeLayoutName` case and the files that ship it, of which one must be
     *                                     there; empty for none.
     */
    public function pick(string $layout): array
    {
        return $layout === ''
            ? ['', []]
            : [$this->case($this->name($layout)), $this->shipped($layout)];
    }

    /**
     * @param string $name The layout's name.
     *
     * @throws WorkflowRefusal When the app names no theme.
     *
     * @return list<MadeFile> Its page data, then its page template.
     */
    public function files(string $name): array
    {
        $case = $this->case($name);
        $classes = 'themes/' . $this->makeTheme->name() . '/src/Layouts/';

        return [
            new MadeFile($classes . $case . 'Page.php', $this->layoutSource->data($name, $case)),
            new MadeFile($this->folder() . 'page--' . $name . '.phtml', $this->layoutSource->page($name, $case)),
        ];
    }

    /**
     * @param string $name The layout's name.
     *
     * @throws WorkflowRefusal When the app names no theme.
     *
     * @return list<string> The files that ship it, of which one must be there.
     */
    private function shipped(string $name): array
    {
        $folder = $this->folder();

        return [$folder . 'page--' . $name . '.phtml', $folder . 'html--' . $name . '.phtml'];
    }

    /**
     * @throws WorkflowRefusal When the app names no theme.
     *
     * @return string The theme's `layouts/`, relative to the project, with its last `/`.
     */
    private function folder(): string
    {
        return 'themes/' . $this->makeTheme->name() . '/layouts/';
    }
}
