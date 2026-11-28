<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Exception\WorkflowRefusal;

/**
 * A module's entity types file (#328), read and edited through the same files the make writes, so gate:31's overlay
 * sees each earlier make's edit.
 */
interface TypesEdits
{
    /**
     * @param string $path     The module's types file, relative to the project's root.
     * @param string $file     The whole file, for a module that has none yet.
     * @param string $accessor An entity's public accessor, for a module that has one.
     * @param string $table    Its private `<entity>With()`.
     *
     * @throws WorkflowRefusal When the file is not a types class the make edits, or the methods would pass its caps.
     *
     * @return MadeFile The new file, or the one there with the methods added and its old bytes kept.
     */
    public function entity(string $path, string $file, string $accessor, string $table): MadeFile;

    /**
     * @param string $path The module's types file.
     *
     * @throws WorkflowRefusal When the file is not a types class the make reads.
     *
     * @return array<string, string> Each table's accessor, such as `scaffold_tasks` => `scaffoldTask`; empty when the
     *                               module has no types file.
     */
    public function accessors(string $path): array;

    /**
     * @param string                $path      The module's types file, which is there.
     * @param array<string, string> $relations Each accessor and the relation to add to it.
     * @param string                $accessors The public methods to add, such as a pivot's accessor.
     * @param list<string>          $uses      The classes they name.
     *
     * @throws WorkflowRefusal When the file is not there or not of the shape the make edits, or would pass its caps.
     *
     * @return MadeFile The file with the relations, methods and imports added, and its old bytes kept.
     */
    public function related(string $path, array $relations, string $accessors, array $uses): MadeFile;
}
