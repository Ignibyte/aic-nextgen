<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\PageSource;
use AIC\Workflow\Application\Make\Source\PostSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_key_exists;

/**
 * `bin/aic make:page <module> <Class> <path> [--component=<C>] [--post]`
 * (#122): a GET page (`…Page`) showing `--component` in the site's layout, or
 * a view of its own (`<Stem>View`) made with it; with `--post`, a POST page
 * (`…Post`) that answers 303 to its path. The manifest routes it.
 */
final readonly class MakePageCommand implements Command
{
    /**
     * @param MakeRun        $makeRun        The run.
     * @param MakeComponents $makeComponents Writes a page's own view.
     * @param MakeNames      $makeNames      The rules.
     * @param MakeCalls      $makeCalls      The route call.
     * @param PageSource     $pageSource     Writes a GET page.
     * @param PostSource     $postSource     Writes a POST page.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeComponents $makeComponents = new MakeComponents(),
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private PageSource $pageSource = new PageSource(),
        private PostSource $postSource = new PostSource(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @return CommandResponse What it wrote.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->makeRun->run(
            $commandInput,
            new CommandUsage(
                'make:page',
                'make:page <module> <Class> <path> [--component=<C>] [--post] [--dry-run] [--json]',
                ['post', 'dry-run'],
                ['component'],
                3,
            ),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule, or `--component` comes with `--post`.
     *
     * @return MakeRecipe The page, and its own view when it names no component.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        if (array_key_exists('post', $parsedArguments->flags)) {
            return $this->post($parsedArguments);
        }

        [$module, $namespace, $class] = $this->makeNames->target('Page', $parsedArguments);
        $path = $this->makeNames->path($parsedArguments->positional(2));
        $stem = $this->makeNames->stem($class, 'Page');
        $named = $parsedArguments->flag('component');
        $component = $named === ''
            ? $stem . 'View'
            : $this->makeNames->class($named, '');
        $view = $named === ''
            ? $this->makeComponents->files($module, $namespace, $component, 'make:page (#122) for ' . $class)
            : [];
        $source = $this->pageSource->source($namespace, $class, $path, $component, $this->makeNames->words($stem));
        $madeFile = new MadeFile('modules/' . $module . '/src/Controller/' . $class . '.php', $source);
        $needed = $named === ''
            ? ''
            : 'modules/' . $module . '/src/Component/' . $component . '.php';
        $manifestCall = $this->makeCalls->route('Get', $path, $class, $namespace);

        return new MakeRecipe($module, $class, [...$view, $madeFile], $manifestCall, ['route GET ' . $path], true, $needed);
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule, or `--component` was given.
     *
     * @return MakeRecipe The POST page.
     */
    private function post(ParsedArguments $parsedArguments): MakeRecipe
    {
        if ($parsedArguments->flag('component') !== '') {
            throw new WorkflowRefusal('--component is for a GET page; a POST page shows nothing.', ConsoleArguments::USAGE);
        }

        [$module, $namespace, $class] = $this->makeNames->target('Post', $parsedArguments);
        $path = $this->makeNames->path($parsedArguments->positional(2));
        $source = $this->postSource->source($namespace, $class, $path);
        $madeFile = new MadeFile('modules/' . $module . '/src/Controller/' . $class . '.php', $source);
        $manifestCall = $this->makeCalls->route('Post', $path, $class, $namespace);

        return new MakeRecipe($module, $class, [$madeFile], $manifestCall, ['route POST ' . $path]);
    }
}
