<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\PostSource;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * `make:page … --post` (#122): a POST page (`…Post`) that answers 303 to its path and shows nothing, so it takes
 * neither `--component` nor `--layout` (#157).
 */
final readonly class MakePosts
{
    /**
     * @param MakeNames  $makeNames  The rules.
     * @param MakeCalls  $makeCalls  The route call.
     * @param PostSource $postSource Writes the page.
     */
    public function __construct(
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private PostSource $postSource = new PostSource(),
    ) {
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule, or `--component` or `--layout` was given.
     *
     * @return MakeRecipe The POST page.
     */
    public function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        foreach (['component', 'layout'] as $option) {
            if ($parsedArguments->flag($option) !== '') {
                throw new WorkflowRefusal('--' . $option . ' is for a GET page; a POST page shows nothing.', ConsoleArguments::USAGE);
            }
        }

        [$module, $namespace, $class] = $this->makeNames->target('Post', $parsedArguments);
        $path = $this->makeNames->path($parsedArguments->positional(2));
        $source = $this->postSource->source($namespace, $class, $path);
        $madeFile = new MadeFile('modules/' . $module . '/src/Controller/' . $class . '.php', $source);
        $manifestCall = $this->makeCalls->route('Post', $path, $class, $namespace);

        return new MakeRecipe($module, $class, [$madeFile], $manifestCall, ['route POST ' . $path]);
    }
}
