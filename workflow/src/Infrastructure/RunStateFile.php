<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\RunStore;
use AIC\Workflow\Domain\RunState;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function array_map;
use function basename;
use function copy;
use function file_get_contents;
use function filemtime;
use function glob;
use function is_file;
use function is_link;
use function unlink;
use function usort;

/**
 * The run in `var/workflow/` (#100): `run.json` while it is open,
 * `history/<run_id>.json` with its spec copied beside it once it ends or is
 * reset. The engine is its only writer; a corrupt file is refused, never
 * replaced or deleted.
 */
final readonly class RunStateFile implements RunStore
{
    /**
     * The state directory, relative to the project.
     */
    public const string DIRECTORY = 'var/workflow';

    /**
     * @param string       $root         The project's root.
     * @param RunStateJson $runStateJson The file's format.
     * @param AtomicFile   $atomicFile   Writes it.
     */
    public function __construct(
        private string $root,
        private RunStateJson $runStateJson = new RunStateJson(),
        private AtomicFile $atomicFile = new AtomicFile(),
    ) {
    }

    /**
     * @throws WorkflowRefusal When `run.json` is a symlink, unreadable or not version 1's shape.
     *
     * @return RunState|null The open run; none when there is no `run.json`.
     */
    #[Override]
    public function load(): ?RunState
    {
        $path = $this->path('run.json');

        if (!is_file($path) && !is_link($path)) {
            return null;
        }

        return $this->read($path, self::DIRECTORY . '/run.json');
    }

    /**
     * @param RunState $runState The run.
     *
     * @throws WorkflowRefusal When it cannot be written.
     */
    #[Override]
    public function save(RunState $runState): void
    {
        $this->atomicFile->write($this->path('run.json'), $this->runStateJson->encode($runState), 0o644);
    }

    /**
     * @param RunState $runState The open run.
     * @param string   $text     Its spec, whole, as the operator's amendment left it (#267).
     *
     * @throws WorkflowRefusal When it cannot be written.
     */
    #[Override]
    public function saveSpec(RunState $runState, string $text): void
    {
        $this->atomicFile->write($this->root . '/' . $runState->runStart->specPath, $text, 0o644);
    }

    /**
     * @param RunState $runState The run, ended or abandoned.
     *
     * @throws WorkflowRefusal When it cannot be archived.
     *
     * @return string Its archive, relative to the project.
     */
    #[Override]
    public function archive(RunState $runState): string
    {
        $name = 'history/' . $runState->runStart->runId;
        $this->atomicFile->write($this->path($name . '.json'), $this->runStateJson->encode($runState), 0o644);
        $spec = $this->root . '/' . $runState->runStart->specPath;

        if (is_file($spec) && !is_link($spec) && !copy($spec, $this->path($name . '.spec.md'))) {
            throw new WorkflowRefusal('the spec ' . $runState->runStart->specPath . ' could not be archived.');
        }

        if (is_file($this->path('run.json')) && !unlink($this->path('run.json'))) {
            throw new WorkflowRefusal(self::DIRECTORY . '/run.json could not be removed after archiving.');
        }

        return self::DIRECTORY . '/' . $name . '.json';
    }

    /**
     * @throws WorkflowRefusal When the newest archive is unreadable.
     *
     * @return RunState|null The most recently archived run; none when there is none.
     */
    #[Override]
    public function lastArchived(): ?RunState
    {
        $runs = $this->archives();

        return $runs === []
            ? null
            : $this->read($runs[0], self::DIRECTORY . '/history/' . basename($runs[0]));
    }

    /**
     * @return list<string> The archived runs' files, newest first; their spec copies end in `.md` and are not among them.
     */
    private function archives(): array
    {
        $archives = glob($this->path('history/run-*.json'));
        $runs = [];

        foreach ($archives === false ? [] : $archives as $archive) {
            $runs[] = [(int) filemtime($archive), $archive];
        }

        usort($runs, self::newest(...));

        return array_map(self::archivePath(...), $runs);
    }

    /**
     * @param string $path A file, absolute.
     * @param string $what It, as the project names it.
     *
     * @throws WorkflowRefusal When it is a symlink, unreadable or not version 1's shape.
     *
     * @return RunState The run.
     */
    private function read(string $path, string $what): RunState
    {
        if (is_link($path)) {
            throw new WorkflowRefusal($what . ' is a symlink; it was left as it is.');
        }

        $json = file_get_contents($path);

        if ($json === false) {
            throw new WorkflowRefusal($what . ' could not be read.');
        }

        return $this->runStateJson->decode($json, $what);
    }

    /**
     * @param string $name A file under the state directory.
     *
     * @return string Its absolute path.
     */
    private function path(string $name): string
    {
        return $this->root . '/' . self::DIRECTORY . '/' . $name;
    }

    /**
     * @param array{int, string} $one   A timestamp and path.
     * @param array{int, string} $other Another timestamp and path.
     *
     * @return int The newest-first order, descending path for equal timestamps.
     */
    private static function newest(array $one, array $other): int
    {
        return [$other[0], $other[1]] <=> [$one[0], $one[1]];
    }

    /**
     * @param array{int, string} $run A timestamp and path.
     *
     * @return string The archived file's path.
     */
    private static function archivePath(array $run): string
    {
        return $run[1];
    }
}
