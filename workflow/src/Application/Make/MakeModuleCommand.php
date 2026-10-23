<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\Source\ManifestSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function array_map;
use function explode;
use function implode;
use function in_array;

/**
 * `bin/aic make:module <module> [--requires=a,b] [--dry-run] [--json]` (#122):
 * the module's manifest, requiring `site` by default when the app has it.
 * It prints the composer.json and phpcs.xml.dist lines the build will ask for.
 */
final readonly class MakeModuleCommand implements Command
{
    /**
     * @param MakeRun        $makeRun        The run.
     * @param ModuleFolders  $moduleFolders  Whether the app has `site`.
     * @param MakeNames      $makeNames      The rules.
     * @param ManifestSource $manifestSource Writes the manifest.
     */
    public function __construct(
        private MakeRun $makeRun,
        private ModuleFolders $moduleFolders,
        private MakeNames $makeNames = new MakeNames(),
        private ManifestSource $manifestSource = new ManifestSource(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @throws InvalidArgumentException When the response exit code is outside 0 to 255.
     *
     * @return CommandResponse What it wrote.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->makeRun->run(
            $commandInput,
            new CommandUsage(
                'make:module',
                'make:module <module> [--requires=a,b] [--dry-run] [--json]',
                ['dry-run'],
                ['requires'],
                1,
            ),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule.
     *
     * @return MakeRecipe The manifest.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        $module = $parsedArguments->positional(0);
        $this->makeNames->module($module);
        $requires = array_map($this->required(...), $this->requires($parsedArguments->flag('requires'), $module));
        $manifest = 'modules/' . $module . '/' . $module . '.module.php';
        $source = $this->manifestSource->source($module, implode('', $requires));

        return new MakeRecipe($module, $module, [new MadeFile($manifest, $source)], null, makesModule: true);
    }

    /**
     * @param string $flag   `--requires`, comma-separated; empty for the default.
     * @param string $module The module.
     *
     * @throws WorkflowRefusal When a required module's name breaks the rule.
     *
     * @return list<string> The modules it requires: those given, or `site` when the app has it.
     */
    private function requires(string $flag, string $module): array
    {
        if ($flag !== '') {
            $names = explode(',', $flag);
            array_map($this->makeNames->module(...), $names);

            return $names;
        }

        return in_array('site', $this->moduleFolders->names(), true) && $module !== 'site'
            ? ['site']
            : [];
    }

    /**
     * @param string $module A module it requires.
     *
     * @return string Its `->requires(...)` line.
     */
    private function required(string $module): string
    {
        return "\n    ->requires('" . $module . "')";
    }
}
