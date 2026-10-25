<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Workflow\Application\ProjectFiles;
use AIC\Workflow\Application\SurfaceSource;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use Closure;

use function array_key_exists;
use function hash;
use function in_array;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * What keeps a make from writing (#122), read from the manifests on disk,
 * never the surface, so a make needs no build first: the module there (or,
 * for `make:module`, not there), a route, command or job some manifest
 * already declares, a command the framework (from the surface) or the
 * workflow already has, a page or block in a module that does not require
 * `site`, a `--component` that is not there, and a target that is there
 * already. Each is a refusal (exit 1), so nothing is written.
 */
final readonly class MakePlanner
{
    /**
     * @param ManifestEdits           $manifestEdits    The manifests.
     * @param ModuleFolders           $moduleFolders    The modules on disk.
     * @param ProjectFiles            $projectFiles     The project's files.
     * @param SurfaceSource           $surfaceSource    The framework's commands.
     * @param Closure(): list<string> $workflowCommands The workflow's command names, read when asked.
     * @param MakePresentation        $makePresentation Checks declared rendering prerequisites.
     */
    public function __construct(
        private ManifestEdits $manifestEdits,
        private ModuleFolders $moduleFolders,
        private ProjectFiles $projectFiles,
        private SurfaceSource $surfaceSource,
        private Closure $workflowCommands,
        private MakePresentation $makePresentation = new MakePresentation(),
    ) {
    }

    /**
     * @param MakeRecipe $makeRecipe The make.
     *
     * @throws WorkflowRefusal When anything keeps it from writing.
     */
    public function check(MakeRecipe $makeRecipe): void
    {
        $this->module($makeRecipe);
        $this->claims($makeRecipe);

        if ($makeRecipe->component !== '' && $this->projectFiles->read($makeRecipe->component) === null) {
            throw new WorkflowRefusal(
                '--component names ' . $makeRecipe->component . ', which is not there; make it with make:component first.',
            );
        }

        foreach ($makeRecipe->files as $madeFile) {
            $this->free($madeFile->path);
        }
    }

    /**
     * @param MakeRecipe $makeRecipe The make.
     *
     * @throws WorkflowRefusal When its module is missing (or, for `make:module`, there), or does not require `site` as it must.
     */
    private function module(MakeRecipe $makeRecipe): void
    {
        $there = in_array($makeRecipe->module, $this->moduleFolders->names(), true);

        if ($makeRecipe->makesModule) {
            $this->absent($makeRecipe, $there);

            return;
        }

        if (!$there) {
            throw new WorkflowRefusal(
                'there is no module ' . $makeRecipe->module . '; make it with bin/aic make:module ' . $makeRecipe->module . '.',
            );
        }

        $requires = $this->manifestEdits->facts($makeRecipe->manifest())->requires;

        if ($makeRecipe->needsSite && $makeRecipe->module !== 'site' && !in_array('site', $requires, true)) {
            throw new WorkflowRefusal(
                $makeRecipe->manifest() . " does not require site, whose layout and regions it uses; add ->requires('site') to it first.",
            );
        }
    }

    /**
     * @param MakeRecipe $makeRecipe A `make:module`.
     * @param bool       $there      Whether the module is on disk.
     *
     * @throws WorkflowRefusal When it is, or its folder is.
     */
    private function absent(MakeRecipe $makeRecipe, bool $there): void
    {
        if ($there || $this->projectFiles->obstacle('modules/' . $makeRecipe->module) !== '') {
            throw new WorkflowRefusal('the module ' . $makeRecipe->module . ' is there already; make:module never writes over one.');
        }
    }

    /**
     * @param MakeRecipe $makeRecipe The make.
     *
     * @throws WorkflowRefusal When a manifest declares what it claims, or the framework or workflow has its command.
     */
    private function claims(MakeRecipe $makeRecipe): void
    {
        if ($makeRecipe->claims === [] && $makeRecipe->component === '') {
            return;
        }

        $declared = $this->declared();
        $this->makePresentation->check($makeRecipe, $this->projectFiles, $declared);

        foreach ($makeRecipe->claims as $claim) {
            $this->claim($this->identity($claim), $declared);
        }
    }

    /**
     * @param string                $claim    A route, command or job the make declares, such as `command notes:prune`.
     * @param array<string, string> $declared Every one the manifests declare, each with its manifest.
     *
     * @throws WorkflowRefusal When a manifest declares it, or the framework or workflow has the command.
     */
    private function claim(string $claim, array $declared): void
    {
        if (str_starts_with($claim, 'command ') && in_array(substr($claim, 8), $this->commands(), true)) {
            throw new WorkflowRefusal('the command ' . substr($claim, 8) . " is the framework's or the workflow's already.");
        }

        if (array_key_exists($claim, $declared)) {
            throw new WorkflowRefusal($declared[$claim] . ' declares the ' . $claim . ' already.');
        }
    }

    /**
     * @throws WorkflowRefusal When a manifest cannot be read, or names another module than its folder.
     *
     * @return array<string, string> Every route, command and job the manifests declare, each with its manifest.
     */
    private function declared(): array
    {
        $declared = [];

        foreach ($this->moduleFolders->names() as $module) {
            $manifest = 'modules/' . $module . '/' . $module . '.module.php';
            $manifestFacts = $this->manifestEdits->facts($manifest);

            if ($manifestFacts->module !== $module) {
                throw new WorkflowRefusal($manifest . ' names its module ' . $manifestFacts->module . ', not ' . $module . '.');
            }

            foreach ($manifestFacts->claims() as $claim) {
                $identity = $this->identity($claim);
                $this->unique($manifest, $identity, $declared);
                $declared[$identity] = $manifest;
            }
        }

        return $declared;
    }

    /**
     * @param string $claim The literal claim retained in inspected facts.
     *
     * @return string Its comparison key; PHP class identities are case insensitive.
     */
    private function identity(string $claim): string
    {
        return str_starts_with($claim, 'template ')
            ? strtolower($claim)
            : $claim;
    }

    /**
     * @param string                $manifest The declaring manifest.
     * @param string                $claim    Its rendering or entry-point identity.
     * @param array<string, string> $declared Earlier actual declarations.
     *
     * @throws WorkflowRefusal When the existing manifests already conflict.
     */
    private function unique(string $manifest, string $claim, array $declared): void
    {
        if (array_key_exists($claim, $declared)) {
            throw new WorkflowRefusal($manifest . ' repeats ' . $claim . ' already declared by ' . $declared[$claim] . '.');
        }
    }

    /**
     * @throws WorkflowRefusal When there is no surface to read the framework's commands from.
     *
     * @return list<string> The command names the framework and the workflow have.
     */
    private function commands(): array
    {
        $surfaceView = $this->surfaceSource->view()
            ?? throw new WorkflowRefusal('build/surface.json is missing: run composer run build.');
        $framework = [];

        foreach ($surfaceView->commands as $command) {
            if ($command['module'] !== 'framework') {
                continue;
            }

            $framework[] = $command['name'];
        }

        return [...$framework, ...($this->workflowCommands)()];
    }

    /**
     * @param string $path A target.
     *
     * @throws WorkflowRefusal When something is there: a make never writes over a file.
     */
    private function free(string $path): void
    {
        $bytes = $this->projectFiles->read($path);
        $obstacle = $this->projectFiles->obstacle($path);

        if ($bytes !== null) {
            throw new WorkflowRefusal(
                $path . ' is there already (xxh128:' . hash('xxh128', $bytes) . '); a make never writes over a file.',
            );
        }

        if ($obstacle !== '') {
            throw new WorkflowRefusal($path . ': ' . $obstacle . '; a make never writes there.');
        }
    }
}
