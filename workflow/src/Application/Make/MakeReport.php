<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Console\ConsoleOutput;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Domain\Make\ManifestCall;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;

use function array_map;
use function explode;
use function implode;
use function json_encode;
use function rtrim;
use function str_contains;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * A make's answer (#122): `wrote <path>` per new file and `edited <manifest>:
 * <call>`, for `make:module` the composer.json and phpcs.xml.dist lines they
 * lack, any nudge, the recorder's note, then `next:`; with `--dry-run`, each
 * path with its bytes; with `--json`, one `aic.console/1` envelope.
 */
final readonly class MakeReport
{
    /**
     * @param ProjectFiles  $projectFiles  composer.json and phpcs.xml.dist.
     * @param MakeNames     $makeNames     The lines the build asks for.
     * @param ConsoleOutput $consoleOutput The envelope.
     */
    public function __construct(
        private ProjectFiles $projectFiles,
        private MakeNames $makeNames = new MakeNames(),
        private ConsoleOutput $consoleOutput = new ConsoleOutput(),
    ) {
    }

    /**
     * @param CommandUsage   $commandUsage The make.
     * @param MakeRecipe     $makeRecipe   What it made.
     * @param list<MadeFile> $files        Its files, the manifest last.
     * @param list<string>   $notes        The guard's nudges, then the recorder's note.
     * @param bool           $dry          Whether it only planned.
     * @param bool           $json         Whether `--json` was given.
     *
     * @throws WorkflowRefusal          When composer.json or phpcs.xml.dist cannot be read.
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResult The answer, exit 0.
     */
    public function made(
        CommandUsage $commandUsage,
        MakeRecipe $makeRecipe,
        array $files,
        array $notes,
        bool $dry,
        bool $json,
    ): CommandResult {
        $lines = $this->lines($makeRecipe);
        $next = $makeRecipe->makesModule
            ? ['composer dump-autoload', 'composer run build']
            : ['composer run build'];

        if ($json) {
            $data = $this->data($makeRecipe, $files, $lines, $next, $dry);

            return new CommandResult([$this->consoleOutput->envelope($commandUsage->name, $data, $notes)], 0);
        }

        $report = [...$this->report($makeRecipe, $files, $dry), ...$lines, ...$notes, 'next: ' . implode(', then ', $next)];

        return new CommandResult($report, 0);
    }

    /**
     * @param CommandUsage    $commandUsage    The make.
     * @param WorkflowRefusal $workflowRefusal Why it wrote nothing.
     * @param bool            $json            Whether `--json` was given.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResult The refusal: exit 2 for a misread line or a bad name, 1 otherwise.
     */
    public function refused(CommandUsage $commandUsage, WorkflowRefusal $workflowRefusal, bool $json): CommandResult
    {
        $exit = $workflowRefusal->getCode() === ConsoleArguments::USAGE
            ? 2
            : 1;
        $message = $workflowRefusal->getMessage() . ' Nothing was written.';
        $line = $json
            ? $this->consoleOutput->error($commandUsage->name, $message, $exit)
            : $commandUsage->name . ' — ' . $message;

        return new CommandResult([$line], $exit);
    }

    /**
     * @param MakeRecipe     $makeRecipe What it made.
     * @param list<MadeFile> $files      Its files, the manifest last.
     * @param bool           $dry        Whether it only planned.
     *
     * @return list<string> Each file written (each with its bytes, when planning), then the manifest's edit.
     */
    private function report(MakeRecipe $makeRecipe, array $files, bool $dry): array
    {
        $lines = [];
        $call = $makeRecipe->call;
        $callText = $call instanceof ManifestCall
            ? $call->call
            : '';

        foreach ($files as $file) {
            $edit = $call !== null && $file->path === $makeRecipe->manifest();
            $lines = match (true) {
                $edit => [...$lines, ($dry ? 'would edit ' : 'edited ') . $file->path . ': ' . $callText],
                $dry => [...$lines, 'would write ' . $file->path . ':', ...explode("\n", rtrim($file->contents, "\n"))],
                default => [...$lines, 'wrote ' . $file->path],
            };
        }

        return $lines;
    }

    /**
     * @param MakeRecipe $makeRecipe What it made.
     *
     * @throws WorkflowRefusal When composer.json or phpcs.xml.dist cannot be read.
     *
     * @return list<string> For `make:module`, the composer.json and phpcs.xml.dist lines they lack; then what the
     *                      make asks to do by hand.
     */
    private function lines(MakeRecipe $makeRecipe): array
    {
        if (!$makeRecipe->makesModule) {
            return $makeRecipe->lines;
        }

        $namespace = $this->makeNames->module($makeRecipe->module);
        $autoload = $this->makeNames->autoload($makeRecipe->module, $namespace);
        $root = $this->makeNames->phpcsRoot($makeRecipe->module, $namespace);
        $composer = str_contains($this->projectFiles->read('composer.json') ?? '', $autoload)
            ? []
            : ["add to composer.json's autoload.psr-4: " . $autoload];
        $phpcs = str_contains($this->projectFiles->read('phpcs.xml.dist') ?? '', $root)
            ? []
            : ["add to phpcs.xml.dist's rootNamespaces: " . $root];

        return [...$composer, ...$phpcs];
    }

    /**
     * @param MakeRecipe     $makeRecipe What it made.
     * @param list<MadeFile> $files      Its files.
     * @param list<string>   $lines      The lines it printed.
     * @param list<string>   $next       What to run next.
     * @param bool           $dry        Whether it only planned.
     *
     * @return string The envelope's data, as JSON.
     */
    private function data(MakeRecipe $makeRecipe, array $files, array $lines, array $next, bool $dry): string
    {
        return json_encode([
            'call' => $makeRecipe->call->call ?? '',
            'dry_run' => $dry,
            'files' => array_map(static fn (MadeFile $madeFile): array => ['hash' => $madeFile->hash(), 'path' => $madeFile->path], $files),
            'lines' => $lines,
            'manifest' => $makeRecipe->manifest(),
            'next' => $next,
            'target' => $makeRecipe->target,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
