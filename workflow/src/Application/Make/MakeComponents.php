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
        $folder = $this->folder($class);

        return [
            new MadeFile($folder . $class . '.php', $this->componentSource->source($class, $made)),
            new MadeFile(
                $folder . $this->makeNames->kebab($class) . '.tpl.php',
                $this->componentTemplateSource->source($class, lcfirst($class)),
            ),
        ];
    }

    /**
     * @param string $class The component.
     *
     * @throws WorkflowRefusal When the app names no theme.
     *
     * @return string Its folder in the theme, relative to the project, with its last `/`.
     */
    public function folder(string $class): string
    {
        return 'themes/' . $this->makeTheme->name() . '/components/' . $class . '/';
    }
}
