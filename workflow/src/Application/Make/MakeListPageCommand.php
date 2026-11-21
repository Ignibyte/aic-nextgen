<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\ListKitSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\EntityField;
use AIC\Workflow\Domain\Make\ListPagePlan;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function count;
use function ctype_digit;
use function explode;
use function in_array;
use function sprintf;
use function str_contains;
use function strlen;

/**
 * `bin/aic make:list-page <module> <Name>ListPage <table> <path> --fields=<name>:<type>,… --filters=<field>,…
 * [--sort=<field>,…] [--key=id] [--per-page=25] [--dry-run] [--json]` (#324): a GET page listing a table's rows in
 * framework atoms, filtered, sorted and a page at a time as its query string asks, with its query value and reader,
 * its own repository and row, and its view. A filter is a text, choice or boolean field; the first `--sort` field
 * (the first field when none is given) is the default order, and the key ends every sort.
 */
final readonly class MakeListPageCommand implements Command
{
    /**
     * The usage.
     */
    private const string USAGE = 'make:list-page <module> <Name>ListPage <table> <path> --fields=<name>:<type>,… --filters=<field>,…'
        . ' [--sort=<field>,…] [--key=id] [--per-page=25] [--dry-run] [--json]';

    /**
     * The field types a filter may take.
     */
    private const array FILTERED = ['text', 'choice', 'boolean'];

    /**
     * The most fields a list shows, so each made method stays within 20 lines.
     */
    private const int MOST_FIELDS = 16;

    /**
     * The most filters a list takes.
     */
    private const int MOST_FILTERS = 6;

    /**
     * The longest name before `ListPage`.
     */
    private const int LONGEST_NAME = 20;

    /**
     * @param MakeRun       $makeRun       The run.
     * @param MakeNames     $makeNames     The rules.
     * @param MakeFields    $makeFields    Reads `--fields`.
     * @param MakeCalls     $makeCalls     The manifest call.
     * @param ListKitSource $listKitSource Writes the files.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeNames $makeNames = new MakeNames(),
        private MakeFields $makeFields = new MakeFields(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private ListKitSource $listKitSource = new ListKitSource(),
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
            new CommandUsage('make:list-page', self::USAGE, ['dry-run'], ['fields', 'filters', 'key', 'per-page', 'sort'], 4),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name, the table, the path, a field, a filter, a sort or the page size breaks a rule.
     *
     * @return MakeRecipe The six files, the route, and the gate:23 steps to add.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $class] = $this->makeNames->target('ListPage', $parsedArguments);
        $fields = $this->fields($parsedArguments->flag('fields'));
        $sorts = $parsedArguments->flag('sort');
        $listPagePlan = new ListPagePlan(
            $namespace,
            $this->stem($class),
            $this->makeNames->word($parsedArguments->positional(2), 'table'),
            $this->makeNames->word($parsedArguments->flag('key') === '' ? 'id' : $parsedArguments->flag('key'), 'table'),
            $this->listPath($parsedArguments->positional(3)),
            $this->perPage($parsedArguments->flag('per-page')),
            $fields,
            $this->filters($parsedArguments->flag('filters'), $fields),
            $sorts === '' ? [$fields[0]->name] : $this->sorts($sorts, $fields),
        );
        $files = $this->listKitSource->files('modules/' . $module . '/src/', $listPagePlan);
        $manifestCall = $this->makeCalls->route('Get', $listPagePlan->path, $class, $namespace);

        $claims = ['route GET ' . $listPagePlan->path];

        return new MakeRecipe($module, $class, $files, $manifestCall, $claims, lines: self::lines($listPagePlan));
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
            throw new WorkflowRefusal('--fields names more than 16 fields: a list page shows at most 16 columns.', ConsoleArguments::USAGE);
        }

        return $parsed;
    }

    /**
     * @param string $class The page's class, ending in `ListPage`.
     *
     * @throws WorkflowRefusal When it is `ListPage` alone, or its name is longer than 20 characters.
     *
     * @return string Its name without `ListPage`, which names every file.
     */
    private function stem(string $class): string
    {
        if ($class === 'ListPage') {
            throw new WorkflowRefusal('the page "ListPage" needs a name before ListPage: name what it lists.', ConsoleArguments::USAGE);
        }

        $stem = $this->makeNames->stem($class, 'ListPage');

        if (strlen($stem) > self::LONGEST_NAME) {
            throw new WorkflowRefusal(
                'the name before ListPage is longer than 20 characters: the made signatures name it twice and must fit their lines.',
                ConsoleArguments::USAGE,
            );
        }

        return $stem;
    }

    /**
     * @param string $path The path given.
     *
     * @throws WorkflowRefusal When it is not a path, or holds a parameter.
     *
     * @return string The page's path.
     */
    private function listPath(string $path): string
    {
        if (str_contains($path, '{')) {
            throw new WorkflowRefusal(
                'the path takes no parameters: a list reads its state from the query string.',
                ConsoleArguments::USAGE,
            );
        }

        return $this->makeNames->path($path);
    }

    /**
     * @param string $perPage What `--per-page` gave.
     *
     * @throws WorkflowRefusal When it is not a whole number from 1 to 200.
     *
     * @return int How many rows a page shows; 25 when not given.
     */
    private function perPage(string $perPage): int
    {
        if ($perPage === '') {
            return 25;
        }

        if (!ctype_digit($perPage) || (int) $perPage < 1 || (int) $perPage > 200) {
            throw new WorkflowRefusal(
                '--per-page takes a whole number from 1 to 200, how many rows a page shows.',
                ConsoleArguments::USAGE,
            );
        }

        return (int) $perPage;
    }

    /**
     * @param string                      $filters What `--filters` gave.
     * @param non-empty-list<EntityField> $fields  The fields.
     *
     * @throws WorkflowRefusal When it names none, a name that is no field, or a field that is not text, choice or boolean.
     *
     * @return non-empty-list<EntityField> The fields it filters by, in its order.
     */
    private function filters(string $filters, array $fields): array
    {
        if ($filters === '') {
            throw new WorkflowRefusal(
                '--filters names no field: give the text, choice or boolean fields the list filters by; make:entity lists rows unfiltered.',
                ConsoleArguments::USAGE,
            );
        }

        $names = explode(',', $filters);

        if (count($names) > self::MOST_FILTERS) {
            throw new WorkflowRefusal('--filters names more than 6 fields: a list takes at most 6 filters.', ConsoleArguments::USAGE);
        }

        $named = [];

        foreach ($names as $name) {
            $named[] = self::filter($name, $fields);
        }

        return $named;
    }

    /**
     * @param string                      $sorts  What `--sort` gave.
     * @param non-empty-list<EntityField> $fields The fields.
     *
     * @throws WorkflowRefusal When it names a name that is no field.
     *
     * @return non-empty-list<string> The fields it sorts by, the default first.
     */
    private function sorts(string $sorts, array $fields): array
    {
        $named = [];

        foreach (explode(',', $sorts) as $name) {
            $named[] = self::field($name, $fields, '--sort')->name;
        }

        return $named;
    }

    /**
     * @param string                      $name   A filter's name.
     * @param non-empty-list<EntityField> $fields The fields.
     *
     * @throws WorkflowRefusal When it is no field, or a field that is not text, choice or boolean.
     *
     * @return EntityField The field it filters by.
     */
    private static function filter(string $name, array $fields): EntityField
    {
        $entityField = self::field($name, $fields, '--filters');

        if (!in_array($entityField->type, self::FILTERED, true)) {
            throw new WorkflowRefusal(
                '--filters names ' . $name . ', a ' . $entityField->type . ' field; a filter is a text, choice or boolean field.',
                ConsoleArguments::USAGE,
            );
        }

        return $entityField;
    }

    /**
     * @param string                      $name   A field's name.
     * @param non-empty-list<EntityField> $fields The fields.
     * @param string                      $flag   The flag that named it.
     *
     * @throws WorkflowRefusal When none of them has that name.
     *
     * @return EntityField The field.
     */
    private static function field(string $name, array $fields, string $flag): EntityField
    {
        foreach ($fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        throw new WorkflowRefusal($flag . ' names ' . $name . ', which is not one of --fields.', ConsoleArguments::USAGE);
    }

    /**
     * @param ListPagePlan $listPagePlan The page.
     *
     * @return list<string> Who may see it, and the gate:23 steps to add.
     */
    private static function lines(ListPagePlan $listPagePlan): array
    {
        return [
            sprintf(
                "The list is anyone's who opens %s: put it under a protected prefix and give its route needs: "
                . 'when not everyone may read %s.',
                $listPagePlan->path,
                $listPagePlan->table,
            ),
            sprintf(
                'Add gate:23 steps to config/native-site.json: GET %s (200), GET %s?sort=-%s&page=2 (200), and one per filter (200).',
                $listPagePlan->path,
                $listPagePlan->path,
                $listPagePlan->sorts[0],
            ),
        ];
    }
}
