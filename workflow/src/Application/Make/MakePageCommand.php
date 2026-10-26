<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\Source\PageSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function array_key_exists;

/**
 * `bin/aic make:page <module> <Class> <path> [--component=<C>] [--layout=<name>] [--post]`
 * (#122): a GET page (`…Page`) showing `--component` in the theme's layout, or
 * a view of its own (`<Stem>View`) made with it in the theme (#153); `--layout`
 * names one of the theme's named layouts (#157). With `--post`, a POST page
 * (`…Post`) that answers 303 to its path. The manifest routes it.
 */
final readonly class MakePageCommand implements Command
{
    /**
     * @param MakeRun        $makeRun        The run.
     * @param MakeComponents $makeComponents Writes a page's own view in the theme.
     * @param MakeLayouts    $makeLayouts    Names the layout a page picks.
     * @param MakeNames      $makeNames      The rules.
     * @param MakeCalls      $makeCalls      The route call.
     * @param PageSource     $pageSource     Writes a GET page.
     * @param MakePosts      $makePosts      Writes a POST page.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeComponents $makeComponents,
        private MakeLayouts $makeLayouts,
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private PageSource $pageSource = new PageSource(),
        private MakePosts $makePosts = new MakePosts(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
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
                'make:page <module> <Class> <path> [--component=<C>] [--layout=<name>] [--post] [--dry-run] [--json]',
                ['post', 'dry-run'],
                ['component', 'layout'],
                3,
            ),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule, or `--component` or `--layout` comes with `--post`.
     *
     * @return MakeRecipe The page, and its own view when it names no component.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        if (array_key_exists('post', $parsedArguments->flags)) {
            return $this->makePosts->recipe($parsedArguments);
        }

        [$module, $namespace, $class] = $this->makeNames->target('Page', $parsedArguments);
        $path = $this->makeNames->path($parsedArguments->positional(2));

        return $this->get($parsedArguments, $module, $namespace, $class, $path);
    }

    /**
     * @param ParsedArguments $parsedArguments The GET page options.
     * @param string          $module          Its module.
     * @param string          $namespace       The module namespace.
     * @param string          $class           The controller name.
     * @param string          $path            Its route.
     *
     * @throws WorkflowRefusal When the component or layout name is invalid, or the app names no theme.
     *
     * @return MakeRecipe The page, and its own view in the theme when it names no component.
     */
    private function get(
        ParsedArguments $parsedArguments,
        string $module,
        string $namespace,
        string $class,
        string $path,
    ): MakeRecipe {
        $stem = $this->makeNames->stem($class, 'Page');
        [$component, $view, $needed] = $this->makeComponents->view($parsedArguments->flag('component'), $stem, $class);
        [$case, $layouts] = $this->makeLayouts->pick($parsedArguments->flag('layout'));
        $source = $this->pageSource->source($namespace, $class, $path, $component, $this->makeNames->words($stem), $case);
        $madeFile = new MadeFile('modules/' . $module . '/src/Controller/' . $class . '.php', $source);
        $manifestCall = $this->makeCalls->route('Get', $path, $class, $namespace);
        $claims = ['route GET ' . $path];

        return new MakeRecipe($module, $class, [...$view, $madeFile], $manifestCall, $claims, true, $needed, layouts: $layouts);
    }
}
