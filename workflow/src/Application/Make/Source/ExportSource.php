<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make\Source;

use AIC\Workflow\Application\Make\MakeTemplates;
use AIC\Workflow\Domain\Make\ExportPlace;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Exception\WorkflowRefusal;

use function lcfirst;

/**
 * Renders what `make:export` writes (#323): the POST page that queues an
 * export and its download in `src/Controller/`, the job that writes it and
 * the one that prunes it in `src/Job/`, the repository and the migration of
 * its table and view.
 */
final readonly class ExportSource
{
    /**
     * Each file's folder under `src/`, its template, and its class's prefix and suffix around the name.
     */
    private const array FILES = [
        ['Controller/', 'post', '', 'ExportPost'],
        ['Controller/', 'download', '', 'ExportDownload'],
        ['Job/', 'job', '', 'ExportJob'],
        ['Job/', 'prune', '', 'ExportPruneJob'],
        ['Repository/', 'repository', '', 'ExportRepository'],
        ['Migration/', 'migration', 'Create', 'ExportsTable'],
    ];

    /**
     * @param MakeTemplates $makeTemplates Renders the export's `*.tpl` files.
     * @param DocParams     $docParams     Pads the constructors' `@param` lines.
     */
    public function __construct(
        private MakeTemplates $makeTemplates = new MakeTemplates(),
        private DocParams $docParams = new DocParams(),
    ) {
    }

    /**
     * @param string      $folder      The module's `src/`, relative to the project.
     * @param string      $namespace   The module's namespace, without the last `\`.
     * @param string      $stem        The export's name without `Export` (`ScaffoldShelf`).
     * @param ExportPlace $exportPlace Its path, jobs, table, view, days and version.
     *
     * @throws WorkflowRefusal When a template is missing or a placeholder unfilled.
     *
     * @return list<MadeFile> The two controllers, the two jobs, the repository and the migration.
     */
    public function files(string $folder, string $namespace, string $stem, ExportPlace $exportPlace): array
    {
        $repository = $stem . 'ExportRepository';
        $values = [
            ...$exportPlace->values(),
            'namespace' => $namespace,
            'params' => $this->params($repository),
            'repository' => $repository,
            'repositoryVariable' => lcfirst($repository),
        ];
        $made = [];

        foreach (self::FILES as [$under, $kind, $prefix, $suffix]) {
            $made[] = new MadeFile(
                $folder . $under . $prefix . $stem . $suffix . '.php',
                $this->makeTemplates->render('export', $kind . '.php.tpl', ['class' => $prefix . $stem . $suffix, ...$values]),
            );
        }

        return $made;
    }

    /**
     * @param string $repository The repository's class.
     *
     * @return string The constructor `@param` lines of a class that takes it and the private files, aligned as Pint
     *                aligns them.
     */
    private function params(string $repository): string
    {
        $rows = [
            [$repository, '$' . lcfirst($repository), 'The exports.'],
            ['PrivateFiles', '$privateFiles', 'Where their files are kept.'],
        ];
        return $this->docParams->lines($rows);
    }
}
