<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\Scaffold\ScaffoldLock;
use AIC\Workflow\Exception\WorkflowRefusal;
use FilesystemIterator;
use JsonException;
use Override;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use stdClass;

use function file_get_contents;
use function file_put_contents;
use function hash_file;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function ksort;
use function strlen;
use function substr;

use const JSON_FORCE_OBJECT;
use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * The app's scaffolds on disk and their frozen record (#299):
 * `var/workflow/scaffolds.json`, which only the engine writes and the guard
 * walls with the rest of `var/workflow/`.
 */
final readonly class LocalScaffoldLock implements ScaffoldLock
{
    /**
     * The record, relative to the project.
     */
    private const string RECORD = RunStateFile::DIRECTORY . '/scaffolds.json';

    /**
     * Why an unreadable record refuses.
     */
    private const string BROKEN = ' is not the record the engine writes: reset the run, or restore the file.';

    /**
     * @param string $root The project's root.
     */
    public function __construct(
        private string $root,
    ) {
    }

    /**
     * @return array<string, string> Every file under `scaffolds/` by its path in the project, with its sha256, sorted.
     */
    #[Override]
    public function current(): array
    {
        $folder = $this->root . '/scaffolds';
        $hashes = [];

        if (!is_dir($folder)) {
            return [];
        }

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $hashes[substr($file->getPathname(), strlen($this->root) + 1)] = (string) hash_file('sha256', $file->getPathname());
        }

        ksort($hashes);

        return $hashes;
    }

    /**
     * @throws WorkflowRefusal When the record is there and unreadable.
     *
     * @return array<string, string>|null The files as last frozen; null when nothing was frozen yet.
     */
    #[Override]
    public function frozen(): ?array
    {
        $path = $this->root . '/' . self::RECORD;

        if (!is_file($path)) {
            return null;
        }

        try {
            $object = json_decode((string) file_get_contents($path), false, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new WorkflowRefusal(self::RECORD . self::BROKEN, 0, $jsonException);
        }

        if (!$object instanceof stdClass) {
            throw new WorkflowRefusal(self::RECORD . self::BROKEN);
        }

        $jsonReader = new JsonReader($object, self::RECORD);
        $hashes = [];

        foreach ($jsonReader->keys() as $file) {
            $hashes[$file] = $jsonReader->string($file);
        }

        return $hashes;
    }

    /**
     * @param array<string, string> $hashes The files to freeze.
     *
     * @throws WorkflowRefusal When the record cannot be written.
     */
    #[Override]
    public function freeze(array $hashes): void
    {
        $json = json_encode($hashes, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        if (file_put_contents($this->root . '/' . self::RECORD, $json) === false) {
            throw new WorkflowRefusal(self::RECORD . ' could not be written.');
        }
    }
}
