<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Scaffold;

use AIC\Workflow\Application\Make\ManifestEdits;
use AIC\Workflow\Application\Make\ModuleFolders;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Domain\Scaffold\LoadedScaffold;
use AIC\Workflow\Exception\WorkflowRefusal;

use function array_diff_key;
use function array_flip;
use function in_array;
use function preg_match;

/**
 * One make of an app scaffold (#298): the line's module, name and arguments
 * filled into every path pattern, template and manifest edit, as the
 * `MakeRecipe` every built-in make hands the run. The same scaffold and line
 * give the same bytes.
 */
final readonly class ScaffoldRecipe
{
    /**
     * @param ManifestEdits    $manifestEdits    What the module's manifest requires already.
     * @param ModuleFolders    $moduleFolders    The modules on disk.
     * @param ScaffoldValues   $scaffoldValues   The values.
     * @param ScaffoldTemplate $scaffoldTemplate Fills the paths and templates.
     * @param ScaffoldEdits    $scaffoldEdits    Fills the manifest edits.
     */
    public function __construct(
        private ManifestEdits $manifestEdits,
        private ModuleFolders $moduleFolders,
        private ScaffoldValues $scaffoldValues = new ScaffoldValues(),
        private ScaffoldTemplate $scaffoldTemplate = new ScaffoldTemplate(),
        private ScaffoldEdits $scaffoldEdits = new ScaffoldEdits(),
    ) {
    }

    /**
     * @param LoadedScaffold  $loadedScaffold  The scaffold.
     * @param ParsedArguments $parsedArguments The line: the kind, the module, the name, then the arguments.
     *
     * @throws WorkflowRefusal When a value breaks its rule, the module is not there, or a filled path leaves the module.
     *
     * @return MakeRecipe The files and the manifest's calls.
     */
    public function recipe(LoadedScaffold $loadedScaffold, ParsedArguments $parsedArguments): MakeRecipe
    {
        $scaffoldManifest = $loadedScaffold->scaffoldManifest;
        $module = $parsedArguments->positional(1);
        $flags = array_diff_key($parsedArguments->flags, array_flip(['dry-run', 'json']));
        $values = $this->scaffoldValues->values($scaffoldManifest, $module, $parsedArguments->positional(2), $flags);

        if (!in_array($module, $this->moduleFolders->names(), true)) {
            throw new WorkflowRefusal('there is no module ' . $module . '; make it with bin/aic make:module ' . $module . '.');
        }

        $folder = 'modules/' . $module . '/';
        $files = [];

        foreach ($scaffoldManifest->files as $scaffoldFile) {
            $path = $this->scaffoldTemplate->render($scaffoldFile->path, $values);

            if (preg_match('#^(?!.*(^|/)\\.\\.?(/|$))[A-Za-z0-9_./-]+$#', $path) !== 1) {
                throw new WorkflowRefusal('the path ' . $path . ' leaves the module folder or holds a character a path may not.');
            }

            $text = $this->scaffoldTemplate->render($loadedScaffold->template($scaffoldFile->template), $values);
            $files[] = new MadeFile($folder . $path, $text);
        }

        $requires = [$module, ...$this->manifestEdits->facts($folder . $module . '.module.php')->requires];
        [$manifestCall, $claims] = $this->scaffoldEdits->calls($scaffoldManifest->edits, $values, $requires);

        return new MakeRecipe($module, $scaffoldManifest->kind . ' ' . ($values['name'] ?? ''), $files, $manifestCall, $claims);
    }
}
