<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Import;

use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Application\SurfaceSource;
use AIC\Workflow\Domain\Console\SurfaceView;
use AIC\Workflow\Domain\RegexCaptures;
use AIC\Workflow\Exception\WorkflowRefusal;

use function count;
use function rtrim;
use function str_replace;
use function strlen;
use function substr;

/**
 * The tables the modules' migrations already create (#318), read from each migration `build/surface.json` lists,
 * so an imported foreign key may reference one; and how many migrations a module has, against gate:26's 15 a folder.
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

        foreach ($this->view()->modules as $surfaceModule) {
            foreach ($surfaceModule->migrations as $migration) {
                $short = substr($migration, strlen(rtrim($surfaceModule->namespace, '\\') . '\\'));
                $path = 'modules/' . $surfaceModule->name . '/src/' . str_replace('\\', '/', $short) . '.php';
                $tables = [...$tables, ...self::regexColumn("/->create\\('([a-z0-9_]+)'\\)/", $this->projectFiles->read($path) ?? '', 1)];
            }
        }

        return $tables;
    }

    /**
     * @param string $module A module.
     *
     * @throws WorkflowRefusal When build/surface.json is missing.
     *
     * @return int How many migrations it has.
     */
    public function migrations(string $module): int
    {
        $surfaceModule = $this->view()->module($module);

        return $surfaceModule === null
            ? 0
            : count($surfaceModule->migrations);
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
