<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeShape;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

/**
 * `bin/aic make:enum`, `make:exception` and `make:interface` (#319), one
 * command per shape: the module's enum, exception or interface in the folder
 * its kind decides. None edits a manifest; `MakeShapes` reads each line.
 */
final readonly class MakeShapeCommand implements Command
{
    /**
     * @param MakeRun    $makeRun    The run.
     * @param MadeShape  $madeShape  The shape it makes.
     * @param MakeShapes $makeShapes Reads the line into the file.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MadeShape $madeShape,
        private MakeShapes $makeShapes = new MakeShapes(),
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
        return $this->makeRun->run($commandInput, $this->madeShape->usage(), $this->recipe(...));
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name or an option breaks a rule.
     *
     * @return MakeRecipe The file.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        return $this->makeShapes->recipe($this->madeShape, $parsedArguments);
    }
}
