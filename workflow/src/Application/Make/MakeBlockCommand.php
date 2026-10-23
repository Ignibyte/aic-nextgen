<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\Source\BlockSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic make:block <module> <Class> [--region=content] [--weight=10]
 * [--prefix=]` (#122): a block (`…Block`) placing one paragraph in its region,
 * by weight, under its route prefix (every page when none). The manifest
 * places it.
 */
final readonly class MakeBlockCommand implements Command
{
    /**
     * @param MakeRun     $makeRun     The run.
     * @param MakeNames   $makeNames   The rules.
     * @param MakeCalls   $makeCalls   The block call.
     * @param BlockSource $blockSource Writes the block.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private BlockSource $blockSource = new BlockSource(),
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
                'make:block',
                'make:block <module> <Class> [--region=content] [--weight=10] [--prefix=/path] [--dry-run] [--json]',
                ['dry-run'],
                ['region', 'weight', 'prefix'],
                2,
            ),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule.
     *
     * @return MakeRecipe The block.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $class] = $this->makeNames->target('Block', $parsedArguments);
        $region = $this->makeNames->word($this->given($parsedArguments, 'region', 'content'), 'region');
        $weight = $this->makeNames->weight($this->given($parsedArguments, 'weight', '10'));
        $prefix = $parsedArguments->flag('prefix') === ''
            ? ''
            : $this->makeNames->path($parsedArguments->flag('prefix'));
        $source = $this->blockSource->source(
            $namespace,
            $class,
            $region,
            $weight,
            $prefix,
            $this->makeNames->words($class),
            $this->makeNames->kebab($class),
        );
        $madeFile = new MadeFile('modules/' . $module . '/src/Block/' . $class . '.php', $source);

        return new MakeRecipe($module, $class, [$madeFile], $this->makeCalls->classOnly('block', 'Block', $class, $namespace), [], true);
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     * @param string          $flag            A flag's name.
     * @param string          $default         Its value when it is not given.
     *
     * @return string The flag's value, or the default.
     */
    private function given(ParsedArguments $parsedArguments, string $flag, string $default): string
    {
        $value = $parsedArguments->flag($flag);

        return $value === ''
            ? $default
            : $value;
    }
}
