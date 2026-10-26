<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Make\Source\ComponentSource;
use AIC\Workflow\Application\Make\Source\ComponentTemplateSource;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Exception\WorkflowRefusal;

use function lcfirst;

/**
 * A component's two files (#122, #153), for `make:component` and for the view a
 * page makes for itself: the class with one promoted `title`, and its template,
 * whose first line types its variable, together in the theme's
 * `components/<Class>/`.
 */
final readonly class MakeComponents
{
    /**
     * @param MakeTheme               $makeTheme               The theme the component goes in.
     * @param ComponentSource         $componentSource         Writes the class.
     * @param ComponentTemplateSource $componentTemplateSource Writes the template.
     * @param MakeNames               $makeNames               Names the template.
     */
    public function __construct(
        private MakeTheme $makeTheme,
        private ComponentSource $componentSource = new ComponentSource(),
        private ComponentTemplateSource $componentTemplateSource = new ComponentTemplateSource(),
        private MakeNames $makeNames = new MakeNames(),
    ) {
    }

    /**
     * @param string $class The component.
     * @param string $made  The make that made it, for its docblock.
     *
     * @throws WorkflowRefusal When the app names no theme.
     *
     * @return list<MadeFile> The class, then the template.
     */
    public function files(string $class, string $made): array
    {
        return [
            new MadeFile($this->classFolder($class) . $class . '.php', $this->componentSource->source($class, $made)),
            new MadeFile(
                $this->folder($class) . $this->makeNames->kebab($class) . '.phtml',
                $this->componentTemplateSource->source($class, lcfirst($class)),
            ),
        ];
    }

    /**
     * @param string $named What `--component` gave; empty for a view of the page's own.
     * @param string $stem  The page's name without `Page`.
     * @param string $page  The page, for its own view's docblock.
     *
     * @throws WorkflowRefusal When the component name breaks a rule, or the app names no theme.
     *
     * @return array{string, list<MadeFile>, string} The component the page shows, the files of its own view (none when
     *                                               it names one), and the named component's file, which must be there
     *                                               (empty for its own view).
     */
    public function view(string $named, string $stem, string $page): array
    {
        if ($named === '') {
            $component = $stem . 'View';

            return [$component, $this->files($component, 'make:page (#122) for ' . $page), ''];
        }

        $component = $this->makeNames->class($named, '');

        return [$component, [], $this->classFolder($component) . $component . '.php'];
    }

    /**
     * @param string $class The component.
     *
     * @throws WorkflowRefusal When the app names no theme.
     *
     * @return string Its template's folder in the theme, relative to the project, with its last `/`.
     */
    public function folder(string $class): string
    {
        return 'themes/' . $this->makeTheme->name() . '/components/' . $class . '/';
    }

    /**
     * @param string $class The component.
     *
     * @throws WorkflowRefusal When the app names no theme.
     *
     * @return string Its class folder under the theme's `src/Components/` (#167), relative to the project, with its
     *                last `/`.
     */
    private function classFolder(string $class): string
    {
        return 'themes/' . $this->makeTheme->name() . '/src/Components/' . $class . '/';
    }
}
