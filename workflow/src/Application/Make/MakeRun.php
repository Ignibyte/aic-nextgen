<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Domain\CommandResult;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Domain\Make\ManifestCall;
use AIC\Workflow\Exception\WorkflowRefusal;
use Closure;
use InvalidArgumentException;

use function array_key_exists;
use function array_map;
use function in_array;

/**
 * One make's run (#122), the same for all eight, stopping at the first
 * refusal with nothing written: the line held to its usage, the names
 * (exit 2), the module and its collisions read from the manifests on disk,
 * the plan (every target and the edited manifest; a target there already
 * refuses the whole plan), the guard's word on each target, then the files
 * written whole, those it creates first and the manifest last (a failed write
 * removes the files this make created and writes back those it edited, #328),
 * `code.made` while a run is open, and the answer. `--dry-run`
 * prints the plan and writes and records nothing. A make runs no process.
 */
final readonly class MakeRun
{
    /**
     * @param MakePlanner      $makePlanner      Refuses a collision.
     * @param ManifestEdits    $manifestEdits    Edits the manifest.
     * @param ProjectFiles     $projectFiles     Writes the files.
     * @param MakeScope        $makeScope        The guard's word.
     * @param MakeRecorder     $makeRecorder     Records `code.made`.
     * @param MakeReport       $makeReport       Writes the answer.
     * @param ConsoleArguments $consoleArguments Reads the line.
     */
    public function __construct(
        private MakePlanner $makePlanner,
        private ManifestEdits $manifestEdits,
        private ProjectFiles $projectFiles,
        private MakeScope $makeScope,
        private MakeRecorder $makeRecorder,
        private MakeReport $makeReport,
        private ConsoleArguments $consoleArguments = new ConsoleArguments(),
    ) {
    }

    /**
     * @param CommandInput                         $commandInput The command line.
     * @param CommandUsage                         $commandUsage What the make takes.
     * @param Closure(ParsedArguments): MakeRecipe $recipe       Names the module and builds the files.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResult What it wrote; exit 0, 1 refused, 2 a misread line or a bad name.
     */
    public function run(CommandInput $commandInput, CommandUsage $commandUsage, Closure $recipe): CommandResult
    {
        try {
            return $this->made($commandInput, $commandUsage, $recipe);
        } catch (WorkflowRefusal $workflowRefusal) {
            $json = in_array('--json', $commandInput->arguments(), true);

            return $this->makeReport->refused($commandUsage, $workflowRefusal, $json);
        }
    }

    /**
     * @param CommandInput                         $commandInput The command line.
     * @param CommandUsage                         $commandUsage What the make takes.
     * @param Closure(ParsedArguments): MakeRecipe $recipe       Names the module and builds the files.
     *
     * @throws WorkflowRefusal          At the first thing that keeps it from writing.
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResult What it wrote.
     */
    private function made(CommandInput $commandInput, CommandUsage $commandUsage, Closure $recipe): CommandResult
    {
        $parsedArguments = $this->consoleArguments->parse($commandInput, $commandUsage);
        $makeRecipe = $recipe($parsedArguments);
        $this->makePlanner->check($makeRecipe);
        $call = $makeRecipe->call;
        $files = $call === null
            ? $makeRecipe->ordered()
            : [...$makeRecipe->ordered(), $this->edited($makeRecipe->manifest(), $call)];
        $dry = array_key_exists('dry-run', $parsedArguments->flags);
        $json = array_key_exists('json', $parsedArguments->flags);

        if ($dry) {
            return $this->makeReport->made($commandUsage, $makeRecipe, $files, [], true, $json);
        }

        $paths = array_map(static fn (MadeFile $madeFile): string => $madeFile->path, $files);
        $nudges = $this->makeScope->check($commandUsage->name, $paths);
        $this->write($files);
        $note = $this->makeScope->watched()
            ? $this->makeRecorder->record($commandUsage->name, $parsedArguments, $makeRecipe, $files)
            : '';

        return $this->makeReport->made($commandUsage, $makeRecipe, $files, [...$nudges, ...($note === '' ? [] : [$note])], false, $json);
    }

    /**
     * @param string       $manifest     The module's manifest.
     * @param ManifestCall $manifestCall The call to add.
     *
     * @throws WorkflowRefusal When the manifest is not a shape the editor reads.
     *
     * @return MadeFile The manifest with the call.
     */
    private function edited(string $manifest, ManifestCall $manifestCall): MadeFile
    {
        return new MadeFile(
            $manifest,
            $this->manifestEdits->edit($manifest, $manifestCall),
            $this->projectFiles->read($manifest) ?? '',
        );
    }

    /**
     * @param list<MadeFile> $files A make's files, those it creates first, the manifest last.
     *
     * @throws WorkflowRefusal When a file cannot be written; first each file this make created is removed and each it
     *                         edited gets its bytes back (#328).
     */
    private function write(array $files): void
    {
        $written = [];

        try {
            foreach ($files as $file) {
                $this->projectFiles->write($file->path, $file->contents);
                $written[] = $file;
            }
        } catch (WorkflowRefusal $workflowRefusal) {
            $this->undo($written);

            throw $workflowRefusal;
        }
    }

    /**
     * @param list<MadeFile> $written The files this make wrote before one failed.
     *
     * @throws WorkflowRefusal When one cannot be removed or written back.
     */
    private function undo(array $written): void
    {
        foreach ($written as $file) {
            $original = $file->original;

            if ($original === null) {
                $this->projectFiles->remove($file->path);

                continue;
            }

            $this->projectFiles->write($file->path, $original);
        }
    }
}
