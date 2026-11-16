<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Console\ConsoleOutput;
use AIC\Workflow\Application\Make\MakeReport;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Scaffold\CatalogKind;
use AIC\Workflow\Domain\Scaffold\LoadedScaffold;
use AIC\Workflow\Domain\Scaffold\ScaffoldFile;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function array_key_exists;
use function array_map;
use function implode;
use function in_array;
use function json_encode;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * `bin/aic make:catalog [--json]` (#298): every kind an agent may make, from
 * one list: the built-in makes, then each app scaffold with what it makes,
 * its usage, the files it writes and when not to use it. A scaffold that
 * does not load is listed with its refusal, so a broken one is seen.
 */
final readonly class MakeCatalogCommand implements Command
{
    /**
     * The built-in makes (#122), until #301 moves them to the scaffold format: each usage line and what it makes.
     */
    private const array BUILT_IN = [
        'make:api' => ['make:api <module> <Class> <path>', 'an Api answering JSON at a GET route'],
        'make:block' => ['make:block <module> <Class> [--region=content] [--weight=10] [--prefix=/path]', 'a block placed in a region'],
        'make:command' => ['make:command <module> <Class> <name>', 'a console command'],
        'make:component' => ['make:component <Class>', 'a theme component with its template'],
        'make:entity' => [
            'make:entity <module> <Class> <table> --fields=<name>:<type>,…',
            'an entity with its migration, repository and list page',
        ],
        'make:job' => ['make:job <module> <Class> <name>', 'a queued or scheduled job, done at once'],
        'make:layout' => ['make:layout <name>', 'a named page layout in the theme'],
        'make:migration' => ['make:migration <module> <Class> <table>', 'a migration creating one table'],
        'make:module' => ['make:module <module> [--requires=a,b]', 'a module with its manifest'],
        'make:page' => ['make:page <module> <Class> <path> [--component=<C>] [--layout=<name>] [--post]', 'a page at a route'],
        'make:theme' => ['make:theme <name>', 'a theme'],
    ];

    /**
     * @param ScaffoldLoader   $scaffoldLoader   The app's scaffolds.
     * @param MakeReport       $makeReport       Answers a misread line.
     * @param ConsoleArguments $consoleArguments Reads the line.
     * @param ConsoleOutput    $consoleOutput    The envelope.
     */
    public function __construct(
        private ScaffoldLoader $scaffoldLoader,
        private MakeReport $makeReport,
        private ConsoleArguments $consoleArguments = new ConsoleArguments(),
        private ConsoleOutput $consoleOutput = new ConsoleOutput(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse The catalog.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        $commandUsage = new CommandUsage('make:catalog', 'make:catalog [--json]');

        try {
            $json = array_key_exists('json', $this->consoleArguments->parse($commandInput, $commandUsage)->flags);
        } catch (WorkflowRefusal $workflowRefusal) {
            return $this->makeReport->refused($commandUsage, $workflowRefusal, in_array('--json', $commandInput->arguments(), true));
        }

        [$kinds, $broken] = $this->kinds();

        if ($json) {
            $data = json_encode(
                ['broken' => $broken, 'kinds' => array_map(static fn (CatalogKind $catalogKind): array => $catalogKind->data(), $kinds)],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );

            return new CommandResult([$this->consoleOutput->envelope('make:catalog', $data, [])], 0);
        }

        return new CommandResult(self::text($kinds, $broken), 0);
    }

    /**
     * @return array{list<CatalogKind>, list<array{error: string, kind: string}>} Every kind, built-in then the app's,
     *                                                                            and the app's that do not load.
     */
    private function kinds(): array
    {
        $kinds = [];

        foreach (self::BUILT_IN as $name => [$usage, $description]) {
            $kinds[] = new CatalogKind($name, 'built-in', $description, 'bin/aic ' . $usage);
        }

        $broken = [];

        foreach ($this->scaffoldLoader->kinds() as $kind) {
            try {
                $kinds[] = self::row($this->scaffoldLoader->load($kind));
            } catch (WorkflowRefusal $workflowRefusal) {
                $broken[] = ['error' => $workflowRefusal->getMessage(), 'kind' => $kind];
            }
        }

        return [$kinds, $broken];
    }

    /**
     * @param LoadedScaffold $loadedScaffold An app scaffold.
     *
     * @return CatalogKind Its row.
     */
    private static function row(LoadedScaffold $loadedScaffold): CatalogKind
    {
        $scaffoldManifest = $loadedScaffold->scaffoldManifest;

        return new CatalogKind(
            $scaffoldManifest->kind,
            $loadedScaffold->folder,
            $scaffoldManifest->description,
            'bin/aic ' . MakeKindCommand::usage($loadedScaffold)->line,
            $scaffoldManifest->from,
            array_map(static fn (ScaffoldFile $scaffoldFile): string => $scaffoldFile->path, $scaffoldManifest->files),
            $scaffoldManifest->notFor,
            $scaffoldManifest->args,
        );
    }

    /**
     * @param list<CatalogKind>                        $kinds  Every kind.
     * @param list<array{error: string, kind: string}> $broken The app's that do not load.
     *
     * @return list<string> The catalog as text: each kind, its usage, its files and its not-for lines.
     */
    private static function text(array $kinds, array $broken): array
    {
        $lines = [];

        foreach ($kinds as $kind) {
            $lines = [...$lines, ...self::lines($kind)];
        }

        foreach ($broken as $row) {
            $lines[] = $row['kind'] . ' does not load: ' . $row['error'];
        }

        return $lines;
    }

    /**
     * @param CatalogKind $catalogKind A kind.
     *
     * @return list<string> Its line, its usage, its files and its not-for lines.
     */
    private static function lines(CatalogKind $catalogKind): array
    {
        $files = $catalogKind->files === []
            ? []
            : ['    writes: ' . implode(', ', $catalogKind->files)];

        return [
            $catalogKind->kind . ' (' . $catalogKind->source . '): ' . $catalogKind->description,
            '    ' . $catalogKind->usage,
            ...$files,
            ...array_map(static fn (string $notFor): string => '    not for: ' . $notFor, $catalogKind->notFor),
        ];
    }
}
