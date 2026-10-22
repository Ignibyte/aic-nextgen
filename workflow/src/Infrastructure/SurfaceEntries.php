<?php

declare(strict_types=1);

namespace AIC\Workflow\Infrastructure;

use AIC\Workflow\Application\RunHost;
use AIC\Workflow\Application\SurfaceSource;
use AIC\Workflow\Domain\CodeModule;
use AIC\Workflow\Domain\Console\SurfaceView;
use AIC\Workflow\Domain\SurfaceEntry;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;
use stdClass;

use function array_any;
use function file_get_contents;
use function filemtime;
use function glob;
use function is_file;
use function json_decode;

/**
 * Reads `build/surface.json` (#101), the manifest `composer run build` writes
 * (#26): routes keyed by method and path, commands and jobs by name, each
 * with its kind. The file at a commit comes from git.
 */
final readonly class SurfaceEntries implements SurfaceSource
{
    /**
     * The manifest's path in the project.
     */
    private const string PATH = 'build/surface.json';

    /**
     * @param string  $root    The project's root.
     * @param RunHost $runHost The file at a commit.
     */
    public function __construct(
        private string $root,
        private RunHost $runHost,
    ) {
    }

    /**
     * @param string|null $baseCommit A commit; null for the file now.
     *
     * @throws WorkflowRefusal When the file is not a surface manifest.
     *
     * @return array<string, SurfaceEntry> Every entry point, by key; empty when there is no file.
     */
    #[Override]
    public function entries(?string $baseCommit): array
    {
        $text = $baseCommit === null
            ? $this->now()
            : $this->runHost->fileAt($baseCommit, self::PATH);

        if ($text === null) {
            return [];
        }

        $document = json_decode($text);

        if (!$document instanceof stdClass) {
            throw new WorkflowRefusal(self::PATH . ' is not a JSON object.');
        }

        return $this->read(new JsonReader($document, self::PATH));
    }

    /**
     * @throws WorkflowRefusal When the file is there and is not a surface manifest.
     *
     * @return list<CodeModule> Its modules as the last build wrote them (#106); none when there is no file.
     */
    public function modules(): array
    {
        $text = $this->now();
        $modules = [];

        foreach ($text === null ? [] : $this->manifestReader($text)->objects('modules') as $module) {
            $modules[] = new CodeModule(
                $module->string('name'),
                $module->string('namespace'),
                $module->strings('requires'),
                $module->strings('exports'),
            );
        }

        return $modules;
    }

    /**
     * @throws WorkflowRefusal When the file is there and is not a surface manifest of this version.
     *
     * @return SurfaceView|null The surface as the inspect commands read it (#109), stale when a module manifest is newer;
     *                          null when there is no file.
     */
    #[Override]
    public function view(): ?SurfaceView
    {
        $text = $this->now();

        if ($text === null) {
            return null;
        }

        $built = (int) filemtime($this->root . '/' . self::PATH);
        $manifests = glob($this->root . '/modules/*/*.module.php');
        $stale = array_any(
            $manifests === false ? [] : $manifests,
            static fn (string $manifest): bool => (int) filemtime($manifest) > $built,
        );

        return (new SurfaceViewJson())->view($this->manifestReader($text), $stale);
    }

    /**
     * @param string $text The manifest's text.
     *
     * @throws WorkflowRefusal When it is not a JSON object.
     *
     * @return JsonReader A reader over it.
     */
    private function manifestReader(string $text): JsonReader
    {
        $document = json_decode($text);

        return $document instanceof stdClass
            ? new JsonReader($document, self::PATH)
            : throw new WorkflowRefusal(self::PATH . ' is not a JSON object.');
    }

    /**
     * @return string|null The manifest as the last build wrote it; null when there is none.
     */
    private function now(): ?string
    {
        $file = $this->root . '/' . self::PATH;

        return is_file($file)
            ? (string) file_get_contents($file)
            : null;
    }

    /**
     * @param JsonReader $jsonReader The manifest.
     *
     * @throws WorkflowRefusal When a row is not the manifest's shape.
     *
     * @return array<string, SurfaceEntry> Its entry points, by key.
     */
    private function read(JsonReader $jsonReader): array
    {
        $entries = [];

        foreach ($jsonReader->objects('routes') as $route) {
            $key = $route->string('method') . ' ' . $route->string('path');
            $entries[$key] = new SurfaceEntry($key, $route->string('kind'), $route->string('handler'), $route->string('module'));
        }

        foreach ([...$jsonReader->objects('commands'), ...$jsonReader->objects('jobs')] as $named) {
            $entries[$named->string('name')] = new SurfaceEntry(
                $named->string('name'),
                $named->string('kind'),
                $named->string('class'),
                '',
            );
        }

        return $entries;
    }
}
