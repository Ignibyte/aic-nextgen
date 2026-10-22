<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

use function dirname;
use function explode;
use function file_exists;
use function file_get_contents;
use function fileperms;
use function is_dir;
use function is_file;
use function is_link;
use function is_string;
use function rmdir;
use function scandir;
use function unlink;

/**
 * The project's files on disk (#105), relative to its root, every write
 * through AtomicFile so a file is whole or untouched and never a symlink.
 */
final readonly class LocalProjectFiles implements ProjectFiles
{
    /**
     * @param string     $root       The project's root.
     * @param AtomicFile $atomicFile Writes a file whole.
     */
    public function __construct(
        private string $root,
        private AtomicFile $atomicFile = new AtomicFile(),
    ) {
    }

    /**
     * @param string $path A path.
     *
     * @throws WorkflowRefusal When it is a file that cannot be read.
     *
     * @return string|null Its bytes; null when there is no regular file there.
     */
    #[Override]
    public function read(string $path): ?string
    {
        $absolute = $this->root . '/' . $path;

        if (is_link($absolute) || !is_file($absolute)) {
            return null;
        }

        $bytes = file_get_contents($absolute);

        return is_string($bytes)
            ? $bytes
            : throw new WorkflowRefusal($path . ' could not be read.');
    }

    /**
     * @param string $path     A path.
     * @param string $contents Its bytes from now.
     *
     * @throws WorkflowRefusal When it cannot be written whole.
     */
    #[Override]
    public function write(string $path, string $contents): void
    {
        $absolute = $this->root . '/' . $path;
        $mode = is_file($absolute)
            ? fileperms($absolute)
            : false;

        $this->atomicFile->write($absolute, $contents, $mode === false ? 0o644 : $mode & 0o777);
    }

    /**
     * @param string $path A file; its directories go too while they are left empty, up to the root.
     *
     * @throws WorkflowRefusal When it cannot be removed.
     */
    #[Override]
    public function remove(string $path): void
    {
        $absolute = $this->root . '/' . $path;

        if (!unlink($absolute)) {
            throw new WorkflowRefusal($path . ' could not be removed.');
        }

        $directory = dirname($absolute);

        while (
            $directory !== $this->root
            && is_dir($directory)
            && scandir($directory) === ['.', '..']
            && rmdir($directory)
        ) {
            $directory = dirname($directory);
        }
    }

    /**
     * @param string $path A path init would write.
     *
     * @return string What keeps a regular file from it; empty when nothing.
     */
    #[Override]
    public function obstacle(string $path): string
    {
        $absolute = $this->root . '/' . $path;

        return is_link($absolute) || is_dir($absolute)
            ? $path . ' is a symlink or a directory'
            : $this->ancestor($path);
    }

    /**
     * @param string $path A path init would write.
     *
     * @return string Which of its directories is a symlink or a file; empty when none is.
     */
    private function ancestor(string $path): string
    {
        $walked = '';

        foreach (explode('/', dirname($path)) as $part) {
            $walked = $walked === ''
                ? $part
                : $walked . '/' . $part;

            if ($this->blocks($walked)) {
                return $walked . ' is a symlink or a file where init needs a directory';
            }
        }

        return '';
    }

    /**
     * @param string $directory A directory init would write in.
     *
     * @return bool Whether it is a symlink, or a file.
     */
    private function blocks(string $directory): bool
    {
        $absolute = $this->root . '/' . $directory;

        return $directory !== '.' && (is_link($absolute) || (file_exists($absolute) && !is_dir($absolute)));
    }
}
