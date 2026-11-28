<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Console\ConsoleArguments;
use AIC\Workflow\Application\Make\Source\SocketSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use InvalidArgumentException;
use Override;

use function str_contains;
use function str_replace;
use function strtolower;

/**
 * `bin/aic make:socket <module> <Class> <path> [--dry-run] [--json]` (#32): a
 * Socket (`…Socket`) at a fixed path that listens and publishes on one
 * channel, `<module>.<stem>`, served by `bin/aic socket:serve`. The manifest
 * routes it as a GET.
 */
final readonly class MakeSocketCommand implements Command
{
    /**
     * @param MakeRun      $makeRun      The run.
     * @param MakeNames    $makeNames    The rules.
     * @param MakeCalls    $makeCalls    The route call.
     * @param SocketSource $socketSource Writes the Socket.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private SocketSource $socketSource = new SocketSource(),
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
            new CommandUsage('make:socket', 'make:socket <module> <Class> <path> [--dry-run] [--json]', ['dry-run'], [], 3),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule, or the path holds a parameter.
     *
     * @return MakeRecipe The Socket.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $class] = $this->makeNames->target('Socket', $parsedArguments);
        $path = $this->makeNames->path($parsedArguments->positional(2));

        if (str_contains($path, '{')) {
            throw new WorkflowRefusal('the path "' . $path . '" of a socket must hold no parameter.', ConsoleArguments::USAGE);
        }

        $channel = str_replace('_', '-', $module) . '.' . strtolower($this->makeNames->stem($class, 'Socket'));
        $source = $this->socketSource->source($namespace, $class, $path, $channel);
        $files = [new MadeFile('modules/' . $module . '/src/Controller/' . $class . '.php', $source)];

        return new MakeRecipe($module, $class, $files, $this->makeCalls->route('Get', $path, $class, $namespace), ['socket ' . $path]);
    }
}
