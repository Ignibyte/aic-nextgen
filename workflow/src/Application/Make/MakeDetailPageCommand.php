<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\DetailKitSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\DetailPagePlan;
use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function array_key_exists;
use function count;
use function str_contains;
use function strlen;

/**
 * `bin/aic make:detail-page <module> <Name>DetailPage <table> <path> --fields=<name>:<type>,…
 * [--tabs=<tab>:<field>+<field>,…] [--key=id] [--uuid] [--dry-run] [--json]` (#341): a GET page at
 * `<path>/{id:int}` (`{id:uuid}` with `--uuid`) showing one row, the first field as its heading and the
 * fields in tabs a Tessera click swaps in place, with its own repository and view. Without `--tabs` one
 * tab, `details`, holds every field.
 */
final readonly class MakeDetailPageCommand implements Command
{
    /**
     * The usage.
     */
    private const string USAGE = 'make:detail-page <module> <Name>DetailPage <table> <path> --fields=<name>:<type>,…'
        . ' [--tabs=<tab>:<field>+<field>,…] [--key=id] [--uuid] [--dry-run] [--json]';

    /**
     * The most fields a page shows.
     */
    private const int MOST_FIELDS = 16;

    /**
     * The longest name before `DetailPage`.
     */
    private const int LONGEST_NAME = 20;

    /**
     * @param MakeRun         $makeRun         The run.
     * @param MakeNames       $makeNames       The rules.
     * @param MakeFields      $makeFields      Reads `--fields`.
     * @param MakeCalls       $makeCalls       The manifest call.
     * @param DetailTabs      $detailTabs      Reads `--tabs`.
     * @param DetailKitSource $detailKitSource Writes the files.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeNames $makeNames = new MakeNames(),
        private MakeFields $makeFields = new MakeFields(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private DetailTabs $detailTabs = new DetailTabs(),
        private DetailKitSource $detailKitSource = new DetailKitSource(),
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
            new CommandUsage('make:detail-page', self::USAGE, ['dry-run', 'uuid'], ['fields', 'key', 'tabs'], 4),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name, the table, the key, the path, a field or a tab breaks a rule.
     *
     * @return MakeRecipe The three files, the route, and the gate:23 steps to add.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $class] = $this->makeNames->target('DetailPage', $parsedArguments);
        $stem = $this->stem($class);
        $fields = $this->fields($parsedArguments->flag('fields'));
        $key = $parsedArguments->flag('key');
        $detailPagePlan = new DetailPagePlan(
            $namespace,
            $stem,
            $this->makeNames->word($parsedArguments->positional(2), 'table'),
            $this->makeNames->word($key === '' ? 'id' : $key, 'table'),
            array_key_exists('uuid', $parsedArguments->flags),
            $this->rowsPath($parsedArguments->positional(3)),
            $fields,
            $this->detailTabs->tabs($parsedArguments->flag('tabs'), $fields),
            $this->makeNames->kebab($stem) . '-tab',
        );
        $files = $this->detailKitSource->files('modules/' . $module . '/src/', $detailPagePlan);
        $manifestCall = $this->makeCalls->route('Get', $detailPagePlan->route(), $class, $namespace);
        $claims = ['route GET ' . $detailPagePlan->route()];

        return new MakeRecipe($module, $class, $files, $manifestCall, $claims, lines: $this->detailTabs->lines($detailPagePlan));
    }

    /**
     * @param string $fields What `--fields` gave.
     *
     * @throws WorkflowRefusal When a field breaks a rule, or there are more than 16.
     *
     * @return non-empty-list<EntityField> The fields, in order.
     */
    private function fields(string $fields): array
    {
        $parsed = $this->makeFields->seeded($fields);

        if (count($parsed) > self::MOST_FIELDS) {
            throw new WorkflowRefusal('--fields names more than 16 fields: a detail page shows at most 16.', ConsoleArguments::USAGE);
        }

        return $parsed;
    }

    /**
     * @param string $class The page's class, ending in `DetailPage`.
     *
     * @throws WorkflowRefusal When it is `DetailPage` alone, or its name is longer than 20 characters.
     *
     * @return string Its name without `DetailPage`, which names every file.
     */
    private function stem(string $class): string
    {
        if ($class === 'DetailPage') {
            throw new WorkflowRefusal('the page "DetailPage" needs a name before DetailPage: name what it shows.', ConsoleArguments::USAGE);
        }

        $stem = $this->makeNames->stem($class, 'DetailPage');

        if (strlen($stem) > self::LONGEST_NAME) {
            throw new WorkflowRefusal(
                'the name before DetailPage is longer than 20 characters: the made lines name it and must fit their width.',
                ConsoleArguments::USAGE,
            );
        }

        return $stem;
    }

    /**
     * @param string $path The path given.
     *
     * @throws WorkflowRefusal When it is not a path, holds a parameter, or is `/`.
     *
     * @return string The rows' path, which the page's `/{id}` follows.
     */
    private function rowsPath(string $path): string
    {
        if (str_contains($path, '{') || $path === '/') {
            throw new WorkflowRefusal(
                'the path is where the rows are, such as /books, with no parameters: the make adds /{id}.',
                ConsoleArguments::USAGE,
            );
        }

        return $this->makeNames->path($path);
    }
}
