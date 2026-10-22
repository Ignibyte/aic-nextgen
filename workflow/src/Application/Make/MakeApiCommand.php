<?php

declare(strict_types=1);

namespace AIC\Workflow\Application\Make;

use AIC\Controller\Contracts\Command;
use AIC\Controller\Contracts\CommandInput;
use AIC\Controller\Contracts\CommandResponse;
use AIC\Workflow\Application\Make\Source\ApiBodySource;
use AIC\Workflow\Application\Make\Source\ApiSource;
use AIC\Workflow\Domain\Console\CommandUsage;
use AIC\Workflow\Domain\Console\ParsedArguments;
use AIC\Workflow\Domain\Make\MadeFile;
use AIC\Workflow\Domain\Make\MakeRecipe;
use AIC\Workflow\Exception\WorkflowRefusal;
use Override;

/**
 * `bin/aic make:api <module> <Class> <path> [--dry-run] [--json]` (#122): a
 * GET API (`…Api`) answering its body (`<Stem>Body`, in `src/Service/`) as
 * JSON. The manifest routes it.
 */
final readonly class MakeApiCommand implements Command
{
    /**
     * @param MakeRun       $makeRun       The run.
     * @param MakeNames     $makeNames     The rules.
     * @param MakeCalls     $makeCalls     The route call.
     * @param ApiSource     $apiSource     Writes the API.
     * @param ApiBodySource $apiBodySource Writes its body.
     */
    public function __construct(
        private MakeRun $makeRun,
        private MakeNames $makeNames = new MakeNames(),
        private MakeCalls $makeCalls = new MakeCalls(),
        private ApiSource $apiSource = new ApiSource(),
        private ApiBodySource $apiBodySource = new ApiBodySource(),
    ) {
    }

    /**
     * @param CommandInput $commandInput The line.
     *
     * @return CommandResponse What it wrote.
     */
    #[Override]
    public function command(CommandInput $commandInput): CommandResponse
    {
        return $this->makeRun->run(
            $commandInput,
            new CommandUsage('make:api', 'make:api <module> <Class> <path> [--dry-run] [--json]', ['dry-run'], [], 3),
            $this->recipe(...),
        );
    }

    /**
     * @param ParsedArguments $parsedArguments The line.
     *
     * @throws WorkflowRefusal When a name breaks a rule.
     *
     * @return MakeRecipe The API and its body.
     */
    private function recipe(ParsedArguments $parsedArguments): MakeRecipe
    {
        [$module, $namespace, $class] = $this->makeNames->target('Api', $parsedArguments);
        $path = $this->makeNames->path($parsedArguments->positional(2));
        $body = $this->makeNames->stem($class, 'Api') . 'Body';
        $folder = 'modules/' . $module . '/src/';
        $files = [
            new MadeFile($folder . 'Controller/' . $class . '.php', $this->apiSource->source($namespace, $class, $path, $body, $module)),
            new MadeFile($folder . 'Service/' . $body . '.php', $this->apiBodySource->source($namespace, $body, $class)),
        ];

        return new MakeRecipe($module, $class, $files, $this->makeCalls->route('Get', $path, $class, $namespace), ['route GET ' . $path]);
    }
}
