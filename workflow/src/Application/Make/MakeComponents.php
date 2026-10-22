<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Make\Source\ComponentSource;
use AIC\Workflow\Application\Make\Source\ComponentTemplateSource;
use AIC\Workflow\Domain\Make\MadeFile;

use function lcfirst;

/**
 * A component's two files (#122), for `make:component` and for the view a
 * page makes for itself: the class with one promoted `title`, and its
 * `*.html.php`, whose first line types its variable.
 */
final readonly class MakeComponents
{
    /**
     * @param ComponentSource         $componentSource         Writes the class.
     * @param ComponentTemplateSource $componentTemplateSource Writes the template.
     */
    public function __construct(
        private ComponentSource $componentSource = new ComponentSource(),
        private ComponentTemplateSource $componentTemplateSource = new ComponentTemplateSource(),
    ) {
    }

    /**
     * @param string $module    The module.
     * @param string $namespace Its namespace.
     * @param string $class     The component.
     * @param string $made      The make that made it, for its docblock.
     *
     * @return list<MadeFile> The class, then the template.
     */
    public function files(string $module, string $namespace, string $class, string $made): array
    {
        return [
            new MadeFile(
                'modules/' . $module . '/src/Component/' . $class . '.php',
                $this->componentSource->source($namespace, $class, $made),
            ),
            new MadeFile(
                'modules/' . $module . '/templates/components/' . $class . '.html.php',
                $this->componentTemplateSource->source($namespace, $class, lcfirst($class)),
            ),
        ];
    }
}
