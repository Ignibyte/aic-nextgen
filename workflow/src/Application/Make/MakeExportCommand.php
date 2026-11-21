<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\ExportSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\ExportPlace;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Domain\Make\ManifestCall;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function ctype_digit;
use function sprintf;
use function str_contains;

/**
 * `bin/aic make:export <module> <Name>Export <path> [--days=7] [--format=csv]
 * [--version=<v>] [--dry-run] [--json]` (#323): a queued CSV export. A POST
 * page at the path stores an export and queues the job that writes it to
 * the private files, and answers 303 to its download at `<path>/<id>`; a
 * daily schedule queues the job that deletes each export past its days. The
 * migration makes the exports' table and the view that says which are past
 * them. Formats beyond CSV wait on an interpreted worker (#317).
 */
final readonly class MakeExportCommand implements Command
{
    /**
     * The usage.
     */
    private const string USAGE = 'make:export <module> <Name>Export <path> [--days=7] [--format=csv] [--version=<v>]'
        . ' [--dry-run] [--json]';

    /**
     * When the prune runs, in UTC.
     */
    private const string PRUNE_CRON = '15 4 * * *';

    /**
     * @param MakeRun      $makeRun      The run.
     * @param MakeVersions $makeVersions The migration's version.
     * @param MakeNames    $makeNames    The rules.
     * @param MakeCalls    $makeCalls    The manifest calls.
     * @param ExportSource $exportSource Writes the files.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeVersions $makeVersions,
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private ExportSource $exportSource = new ExportSource(),
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
            new CommandUsage('make:export', self::USAGE, ['dry-run'], ['days', 'format', 'version'], 3),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name, the path, the days or the format breaks a rule.
     *
     * @return MakeRecipe The six files, the routes, the jobs, the schedule and the migration, and what to do by hand.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $this->format($parsedArguments->flag('format'));
        [$module, $namespace, $class] = $this->makeNames->target('Export', $parsedArguments);
        $stem = $this->stem($class);
        $exportPlace = $this->place($parsedArguments, $stem);
        $files = $this->exportSource->files('modules/' . $module . '/src/', $namespace, $stem, $exportPlace);
        $manifestCall = $this->manifestCall($exportPlace, $stem, $namespace);
        $claims = [
            'route POST ' . $exportPlace->path,
            'route GET ' . $exportPlace->download(),
            'job ' . $exportPlace->job,
            'job ' . $exportPlace->prune(),
        ];

        return new MakeRecipe($module, $class, $files, $manifestCall, $claims, lines: self::lines($exportPlace->path));
    }

    /**
     * @param ExportPlace $exportPlace Where the export goes.
     * @param string      $stem        The export's name without `Export`.
     * @param string      $namespace   The module's namespace.
     *
     * @return ManifestCall Both routes, both jobs, the prune's schedule and the migration.
     */
    private function manifestCall(ExportPlace $exportPlace, string $stem, string $namespace): ManifestCall
    {
        $calls = [
            $this->makeCalls->route('Get', $exportPlace->download(), $stem . 'ExportDownload', $namespace),
            $this->makeCalls->named('job', $exportPlace->job, $stem . 'ExportJob', $namespace),
            $this->makeCalls->named('job', $exportPlace->prune(), $stem . 'ExportPruneJob', $namespace),
            $this->makeCalls->schedule($exportPlace->prune(), self::PRUNE_CRON),
            $this->makeCalls->classOnly('migration', 'Migration', 'Create' . $stem . 'ExportsTable', $namespace),
        ];
        $manifestCall = $this->makeCalls->route('Post', $exportPlace->path, $stem . 'ExportPost', $namespace);

        foreach ($calls as $call) {
            $manifestCall = $this->makeCalls->both($manifestCall, $call);
        }

        return $manifestCall;
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     * @param string          $stem            The export's name without `Export`.
     *
     * @throws WorkflowRefusal When the path, the days or the version breaks a rule.
     *
     * @return ExportPlace The path, the jobs, the table and its view, the days and the version.
     */
    private function place(ParsedArguments $parsedArguments, string $stem): ExportPlace
    {
        $snake = $this->makeNames->snake($stem);

        return new ExportPlace(
            $this->exportPath($parsedArguments->positional(2)),
            $this->makeNames->name($this->makeNames->kebab($stem) . '-export'),
            $snake . '_exports',
            $this->makeNames->word($snake . '_export_states', 'table'),
            $this->days($parsedArguments->flag('days')),
            $this->makeVersions->version($parsedArguments, 'create_' . $snake . '_exports_table'),
        );
    }

    /**
     * @param string $class The export's class, ending in `Export`.
     *
     * @throws WorkflowRefusal When it is `Export` alone.
     *
     * @return string Its name without `Export`, which names every file.
     */
    private function stem(string $class): string
    {
        if ($class === 'Export') {
            throw new WorkflowRefusal('the export "Export" needs a name before Export: name what it exports.', ConsoleArguments::USAGE);
        }

        return $this->makeNames->stem($class, 'Export');
    }

    /**
     * @param string $path The path given.
     *
     * @throws WorkflowRefusal When it is not a path, or holds a parameter.
     *
     * @return string The path the export is posted to; its downloads are under it.
     */
    private function exportPath(string $path): string
    {
        if (str_contains($path, '{')) {
            throw new WorkflowRefusal("the path takes no parameters: the download adds the export's id.", ConsoleArguments::USAGE);
        }

        return $this->makeNames->path($path);
    }

    /**
     * @param string $days What `--days` gave.
     *
     * @throws WorkflowRefusal When it is not a whole number from 1 to 365.
     *
     * @return string How many days an export is kept; 7 when not given.
     */
    private function days(string $days): string
    {
        if ($days === '') {
            return '7';
        }

        if (!ctype_digit($days) || (int) $days < 1 || (int) $days > 365) {
            throw new WorkflowRefusal('--days takes a whole number from 1 to 365, how long an export is kept.', ConsoleArguments::USAGE);
        }

        return (string) (int) $days;
    }

    /**
     * @param string $format What `--format` gave.
     *
     * @throws WorkflowRefusal When it is neither empty nor `csv`.
     */
    private function format(string $format): void
    {
        if ($format !== '' && $format !== 'csv') {
            throw new WorkflowRefusal(
                '--format takes csv; another format needs a library with no native path, which goes to an interpreted worker (#317).',
                ConsoleArguments::USAGE,
            );
        }
    }

    /**
     * @param string $path The path the export is posted to.
     *
     * @return list<string> Who may download, and the gate:23 steps to add.
     */
    private static function lines(string $path): array
    {
        return [
            sprintf(
                "An export is anyone's who holds its link: put %s under a protected prefix and give both routes needs: "
                . 'when it holds what not everyone may read.',
                $path,
            ),
            sprintf(
                'Add gate:23 steps to config/native-site.json: POST %s with aic_csrf={csrf} (303, "captures": true), then GET {location} '
                . '(404), GET {location} with "work": true (200), and GET %s/00000000-0000-0000-0000-000000000000 (404).',
                $path,
                $path,
            ),
        ];
    }
}
