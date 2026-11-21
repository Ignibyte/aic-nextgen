<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\PageActionSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function rtrim;
use function sprintf;
use function str_contains;

/**
 * `bin/aic make:page-action <module> <Name>Action --on=<path>
 * [--region=content] [--dry-run] [--json]` (#322): a POST page at
 * `<on>/<kebab name>` for a button or form on the page at `--on`, which some
 * manifest must route already. Its step does the one call; done, the action
 * answers 303 to the page, or to a Tessera submit the region in place;
 * refused, its view's form again with the reason, as 422. The make prints
 * where the view goes and the gate:23 steps to add, and edits only the
 * module's manifest.
 */
final readonly class MakePageActionCommand implements Command
{
    /**
     * The usage.
     */
    private const string USAGE = 'make:page-action <module> <Name>Action --on=<path> [--region=content] [--dry-run] [--json]';

    /**
     * @param MakeRun          $makeRun          The run.
     * @param MakeNames        $makeNames        The rules.
     * @param MakeCalls        $makeCalls        The route call.
     * @param PageActionSource $pageActionSource Writes the files.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private PageActionSource $pageActionSource = new PageActionSource(),
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
            new CommandUsage('make:page-action', self::USAGE, ['dry-run'], ['on', 'region'], 2),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name, the page's path or the region breaks a rule.
     *
     * @return MakeRecipe The action, its step and its view, and what to add by hand.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $class] = $this->makeNames->target('Action', $parsedArguments);
        $stem = $this->stem($class);
        $page = $this->pagePath($parsedArguments->flag('on'));
        $region = $parsedArguments->flag('region') === ''
            ? 'content'
            : $this->makeNames->word($parsedArguments->flag('region'), 'region');
        $path = rtrim($page, '/') . '/' . $this->makeNames->kebab($stem);
        $files = $this->pageActionSource->files('modules/' . $module . '/src/', $namespace, $stem, $page, $path, $region);

        return new MakeRecipe(
            $module,
            $class,
            $files,
            $this->makeCalls->route('Post', $path, $class, $namespace),
            ['route POST ' . $path],
            true,
            lines: self::lines($stem, $page, $path, $region),
            routes: ['route GET ' . $page],
        );
    }

    /**
     * @param string $class The action's class, ending in `Action`.
     *
     * @throws WorkflowRefusal When it is `Action` alone.
     *
     * @return string Its name without `Action`, which names the step and the view.
     */
    private function stem(string $class): string
    {
        if ($class === 'Action') {
            throw new WorkflowRefusal('the action "Action" needs a name before Action: name what it does.', ConsoleArguments::USAGE);
        }

        return $this->makeNames->stem($class, 'Action');
    }

    /**
     * @param string $page What `--on` gave.
     *
     * @throws WorkflowRefusal When it is missing, not a path, or holds a parameter.
     *
     * @return string The page's path.
     */
    private function pagePath(string $page): string
    {
        if ($page === '' || str_contains($page, '{')) {
            throw new WorkflowRefusal(
                '--on names the path of the page the action is on, with no parameters, such as --on=/notes.',
                ConsoleArguments::USAGE,
            );
        }

        return $this->makeNames->path($page);
    }

    /**
     * @param string $stem   The action's name without `Action`.
     * @param string $page   The page's path.
     * @param string $path   The action's path.
     * @param string $region The region it answers.
     *
     * @return list<string> Where the view goes and the gate:23 steps to add.
     */
    private static function lines(string $stem, string $page, string $path, string $region): array
    {
        return [
            sprintf("Place %sView::components(\$serverRequest, '') in the %s region of the page at %s.", $stem, $region, $page),
            sprintf(
                'Add gate:23 steps to config/native-site.json: POST %s with value=Ada&aic_csrf={csrf} (303), value= (422), '
                . 'and both as a Tessera submit to %s (200, 422).',
                $path,
                $region,
            ),
        ];
    }
}
