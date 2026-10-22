<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Exception\WorkflowRefusal;

use function chmod;
use function dirname;
use function file_put_contents;
use function is_dir;
use function is_file;
use function is_link;
use function mkdir;
use function realpath;
use function rename;
use function strlen;
use function tempnam;
use function unlink;

/**
 * Writes a file whole or not at all (#100): a temporary file beside it, then
 * a rename over it, the mode set first. A symlinked file or directory is
 * refused, and a temporary file the system put elsewhere is refused too,
 * since a rename from there is no longer atomic (Droost's `writeAtomically`).
 */
final readonly class AtomicFile
{
    /**
     * @param string $path     The file.
     * @param string $contents What it holds.
     * @param int    $mode     Its mode, such as 0644.
     *
     * @throws WorkflowRefusal When it, or its directory, is a symlink, or it cannot be written.
     */
    public function write(string $path, string $contents, int $mode): void
    {
        $directory = dirname($path);
        $this->directory($directory);

        if (is_link($path)) {
            throw new WorkflowRefusal($path . ' is a symlink; the workflow writes only regular files.');
        }

        $temporary = $this->temporary($directory, $path);

        if (
            file_put_contents($temporary, $contents) !== strlen($contents)
            || !chmod($temporary, $mode)
            || !rename($temporary, $path)
        ) {
            $this->discard($temporary);

            throw new WorkflowRefusal($path . ' could not be written.');
        }
    }

    /**
     * @param string $directory A directory the workflow writes in.
     *
     * @throws WorkflowRefusal When it is a symlink, or cannot be made.
     */
    public function directory(string $directory): void
    {
        if (is_link($directory)) {
            throw new WorkflowRefusal($directory . ' is a symlink; the workflow writes only in real directories.');
        }

        if (!is_dir($directory) && !mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new WorkflowRefusal($directory . ' could not be made.');
        }
    }

    /**
     * @param string $directory The file's directory.
     * @param string $path      The file.
     *
     * @throws WorkflowRefusal When the temporary file would not sit beside it: a rename would not be atomic.
     *
     * @return string A temporary file in the directory.
     */
    private function temporary(string $directory, string $path): string
    {
        $temporary = tempnam($directory, '.aic-');

        if ($temporary === false || realpath(dirname($temporary)) !== realpath($directory)) {
            $this->discard($temporary);

            throw new WorkflowRefusal('no temporary file could be made beside ' . $path . '.');
        }

        return $temporary;
    }

    /**
     * @param string|false $temporary A temporary file, or false for none.
     */
    private function discard(string|false $temporary): void
    {
        if ($temporary === false || !is_file($temporary)) {
            return;
        }

        unlink($temporary);
    }
}
