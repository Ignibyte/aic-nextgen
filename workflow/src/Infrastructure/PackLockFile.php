<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\PackLockStore;
use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Domain\InstallAction;
use AIC\Workflow\Domain\InstallLine;
use AIC\Workflow\Domain\PackLock;
use AIC\Workflow\Exception\WorkflowRefusal;
use JsonException;
use Override;
use stdClass;

use function json_decode;
use function json_encode;
use function preg_match;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

/**
 * The selected client's workflow lock (#105), committed with the project, so a fresh
 * clone still knows what init wrote (Droost kept its lock in an ignored
 * directory, and a clone lost it). JSON, `{"schema": ..., "files": {path:
 * "sha256:<hex>"}}`, keys sorted.
 */
final readonly class PackLockFile implements PackLockStore
{
    /**
     * Why a broken lock refuses.
     */
    private const string BROKEN = ' is not a lock init can read, so it cannot tell edits from its own writes: restore it from git.';

    /**
     * @param ProjectFiles $projectFiles The project's files.
     * @param string       $path         The selected client's lock.
     */
    public function __construct(
        private ProjectFiles $projectFiles,
        private string $path = PackManifest::LOCK,
    ) {
    }

    /**
     * @throws WorkflowRefusal When it is there and is not a lock, or cannot take a regular file.
     *
     * @return PackLock The lock; empty when there is none yet.
     */
    #[Override]
    public function load(): PackLock
    {
        $obstacle = $this->projectFiles->obstacle($this->path);

        if ($obstacle !== '') {
            throw new WorkflowRefusal($obstacle . '; init writes only regular files, so move it aside first.');
        }

        $text = $this->projectFiles->read($this->path);

        return $text === null
            ? new PackLock()
            : $this->decode($text);
    }

    /**
     * @param PackLock $packLock The lock init leaves.
     *
     * @throws WorkflowRefusal When it cannot be written.
     *
     * @return InstallLine Wrote when it changed, current when it did not.
     */
    #[Override]
    public function save(PackLock $packLock): InstallLine
    {
        $flags = JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
        $files = new stdClass();

        foreach ($packLock->sorted() as $path => $hash) {
            $files->{$path} = $hash;
        }

        $text = json_encode(['schema' => PackLock::SCHEMA, 'files' => $files], $flags) . "\n";

        if ($this->projectFiles->read($this->path) === $text) {
            return new InstallLine(InstallAction::Current, $this->path);
        }

        $this->projectFiles->write($this->path, $text);

        return new InstallLine(InstallAction::Wrote, $this->path, "commit it: it tells your edits from init's writes");
    }

    /**
     * @param string $text The lock's text.
     *
     * @throws WorkflowRefusal When it is not a lock.
     *
     * @return PackLock The lock.
     */
    private function decode(string $text): PackLock
    {
        try {
            $object = json_decode($text, false, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new WorkflowRefusal($this->path . self::BROKEN, 0, $jsonException);
        }

        if (!$object instanceof stdClass) {
            throw new WorkflowRefusal($this->path . self::BROKEN);
        }

        $jsonReader = new JsonReader($object, $this->path);
        $jsonReader->only(['schema', 'files']);

        if ($jsonReader->string('schema') !== PackLock::SCHEMA) {
            throw new WorkflowRefusal($this->path . self::BROKEN);
        }

        return $this->hashes($jsonReader->object('files'));
    }

    /**
     * @param JsonReader $jsonReader The lock's `files`.
     *
     * @throws WorkflowRefusal When a value is not a hash.
     *
     * @return PackLock The lock.
     */
    private function hashes(JsonReader $jsonReader): PackLock
    {
        $packLock = new PackLock();

        foreach ($jsonReader->keys() as $path) {
            $hash = $jsonReader->string($path);

            if (preg_match('/^sha256:[0-9a-f]{64}$/', $hash) !== 1) {
                throw new WorkflowRefusal($this->path . self::BROKEN);
            }

            $packLock = $packLock->with($path, $hash);
        }

        return $packLock;
    }
}
