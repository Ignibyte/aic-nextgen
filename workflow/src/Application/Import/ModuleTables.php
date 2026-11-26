<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Application\SurfaceSource;
use AIC\Workflow\Domain\Console\SurfaceView;
use AIC\Workflow\Domain\RegexCaptures;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_filter;
use function count;
use function rtrim;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * The tables the modules' migrations already create (#318), read from each migration `build/surface.json` lists,
 * so an imported foreign key may reference one; how many migrations a module has, against gate:26's 15 a folder; and
 * the migration that creates a table and those that alter it (#338).
 */
final readonly class ModuleTables
{
    use RegexCaptures;

    /**
     * @param SurfaceSource $surfaceSource The build's surface.
     * @param ProjectFiles  $projectFiles  The migrations' files.
     */
    public function __construct(
        private SurfaceSource $surfaceSource,
        private ProjectFiles $projectFiles,
    ) {
    }

    /**
     * @throws WorkflowRefusal When build/surface.json is missing.
     *
     * @return list<string> The tables some module's migration creates.
     */
    public function created(): array
    {
        $tables = [];

        foreach ($this->files() as $path) {
            $tables = [...$tables, ...self::regexColumn("/->create\\('([a-z0-9_]+)'\\)/", $this->projectFiles->read($path) ?? '', 1)];
        }

        return $tables;
    }

    /**
     * @param string $table A table.
     *
     * @throws WorkflowRefusal When build/surface.json is missing.
     *
     * @return string The migration that creates it (#338), relative to the project; empty when none does.
     */
    public function creator(string $table): string
    {
        return $this->naming('create', $table)[0] ?? '';
    }

    /**
     * @param string $table A table.
     *
     * @throws WorkflowRefusal When build/surface.json is missing.
     *
     * @return list<string> The migrations that alter it (#338), relative to the project.
     */
    public function alterers(string $table): array
    {
        return $this->naming('alter', $table);
    }

    /**
     * @param string $module A module.
     *
     * @throws WorkflowRefusal When build/surface.json is missing.
     *
     * @return int How many migrations it has: those the surface lists whose files are there, so gate:31's replay,
     *             which reads the finished module's surface, counts what its lines have made so far (#349).
     */
    public function migrations(string $module): int
    {
        $folder = 'modules/' . $module . '/';

        return count(array_filter(
            $this->files(),
            fn (string $path): bool => str_starts_with($path, $folder) && $this->projectFiles->read($path) !== null,
        ));
    }

    /**
     * @param string $verb  `create` or `alter`.
     * @param string $table A table.
     *
     * @throws WorkflowRefusal When build/surface.json is missing.
     *
     * @return list<string> The migrations that call `$schema-><verb>('<table>')`, in the surface's order.
     */
    private function naming(string $verb, string $table): array
    {
        $named = [];

        foreach ($this->files() as $path) {
            if (!(str_contains($this->projectFiles->read($path) ?? '', '->' . $verb . "('" . $table . "')"))) {
                continue;
            }

            $named[] = $path;
        }

        return $named;
    }

    /**
     * @throws WorkflowRefusal When build/surface.json is missing.
     *
     * @return list<string> Every module migration's file, relative to the project, in the surface's order.
     */
    private function files(): array
    {
        $files = [];

        foreach ($this->view()->modules as $surfaceModule) {
            foreach ($surfaceModule->migrations as $migration) {
                $short = substr($migration, strlen(rtrim($surfaceModule->namespace, '\\') . '\\'));
                $files[] = 'modules/' . $surfaceModule->name . '/src/' . str_replace('\\', '/', $short) . '.php';
            }
        }

        return $files;
    }

    /**
     * @throws WorkflowRefusal When build/surface.json is missing.
     *
     * @return SurfaceView The surface.
     */
    private function view(): SurfaceView
    {
        return $this->surfaceSource->view() ?? throw new WorkflowRefusal('build/surface.json is missing: run composer run build.');
    }
}
